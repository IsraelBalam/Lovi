<?php
require_once '../conexion.php'; 
require_once __DIR__ . '/../vendor/autoload.php'; // Incluye el autoload de Composer

use setasign\Fpdi\Tcpdf\Fpdi; // Para FPDI/TCPDF, para editar tu plantilla de PDF
use PHPMailer\PHPMailer\PHPMailer; // Para enviar correos
use PHPMailer\PHPMailer\Exception; // Para manejar excepciones de PHPMailer
use Dompdf\Dompdf; // Para el PDF adicional, si se genera desde HTML simple

header('Content-Type: application/json');

$request_body = file_get_contents("php://input");
$data = json_decode($request_body, true);

$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

$dbMysql = new DBConnectionLocal();
$pdo = $dbMysql; 
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Crucial para depuración

switch ($accion) {
    case 'listar_clientes_para_select': // Para el select con búsqueda de clientes en el modal de registro
        $busqueda = $_GET['q'] ?? ''; 
        listarClientesParaSelect($pdo, $busqueda);
        break;
    case 'obtener_rifas_activas': // Para los selects de rifas activas
        obtenerRifasActivas($pdo);
        break;
    case 'dar_alta_boletos': // Para registrar uno o varios boletos
        $clienteId = $data['clienteId'] ?? null;
        $rifaId = $data['rifaId'] ?? null;
        $cantidadBoletos = $data['cantidadBoletos'] ?? 1;
        darDeAltaBoletos($pdo, $clienteId, $rifaId, $cantidadBoletos);
        break;
    case 'listar_boletos_por_cliente_rifa': // Para ver los boletos de un cliente en una rifa específica
        $clienteId = $_GET['clienteId'] ?? null;
        $rifaId = $_GET['rifaId'] ?? null;
        listarBoletosPorClienteYRifa($pdo, $clienteId, $rifaId);
        break;
    case 'generar_y_enviar_pdfs': // Para generar los PDFs y enviarlos por correo
        $clienteId = $data['clienteId'] ?? null;
        $boletosIds = $data['boletosIds'] ?? []; // Array de IDs de boletos a incluir en el PDF
        generarYEnviarPdfs($pdo, $clienteId, $boletosIds);
        break;
    case 'listar_clientes_con_boletos_activos': // Para la tabla principal de clientes con boletos en rifas activas
        $pagina = $_GET['pagina'] ?? 1;
        $limite = $_GET['limite'] ?? 10;
        $busqueda = $_GET['busqueda'] ?? '';
        listarClientesConBoletosActivos($pdo, $pagina, $limite, $busqueda);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}

// --- FUNCIONES DEL MÓDULO DE BOLETOS ---

/**
 * Lista clientes para el select con búsqueda (Select2)
 */
function listarClientesParaSelect($pdo, $busqueda) {
    try {
        $sql = "SELECT id, nombre, apellidoPaterno, apellidoMaterno FROM clientes";
        $params = [];

        if (!empty($busqueda)) {
            $searchPattern = '%' . $busqueda . '%'; // Definir searchPattern una vez
            $sql .= " WHERE nombre LIKE :busqueda_nombre OR 
                             apellidoPaterno LIKE :busqueda_apellido_paterno OR 
                             apellidoMaterno LIKE :busqueda_apellido_materno";
            
            // Vincular cada placeholder con el mismo patrón de búsqueda
            $params[':busqueda_nombre'] = $searchPattern;
            $params[':busqueda_apellido_paterno'] = $searchPattern;
            $params[':busqueda_apellido_materno'] = $searchPattern;
        }

        $sql .= " ORDER BY nombre ASC LIMIT 20"; // Limitar resultados para el select

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); // Pasar el array de parámetros a execute()
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear para Select2: { id: "value", text: "label" }
        $formattedClientes = array_map(function($cliente) {
            // Concatenar nombre completo, manejando apellidoMaterno opcional
            $nombreCompleto = $cliente['nombre'] . ' ' . $cliente['apellidoPaterno'];
            if (!empty($cliente['apellidoMaterno'])) {
                $nombreCompleto .= ' ' . $cliente['apellidoMaterno'];
            }
            return [
                'id' => $cliente['id'],
                'text' => $nombreCompleto
            ];
        }, $clientes);

        echo json_encode(['results' => $formattedClientes]); // Select2 espera 'results'

    } catch (PDOException $e) {
        error_log('Error al listar clientes para select: ' . $e->getMessage());
        // En un entorno de producción, evita exponer e->getMessage() directamente al cliente
        echo json_encode(['results' => [], 'error' => 'Error al cargar clientes para el selector.']);
    }
}

/**
 * Obtiene las rifas que están activas (fecha de sorteo futura y estado 'activa').
 */
function obtenerRifasActivas($pdo) {
    try {
        // Asumo 'activa' es una columna en rifas (TINYINT 1 para activa) y 'fecha' es la fecha del sorteo.
        $stmt = $pdo->query("SELECT id, nombre, fecha FROM rifas WHERE activa = 1 AND fecha >= CURRENT_DATE() ORDER BY fecha ASC"); 
        $rifas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formatear para el select de la interfaz
        $formattedRifas = array_map(function($rifa) {
            $fecha_formato = (new DateTime($rifa['fecha']))->format('d/m/Y H:i');
            return [
                'id' => $rifa['id'],
                'text' => $rifa['nombre'] . ' (Sorteo: ' . $fecha_formato . ')'
            ];
        }, $rifas);

        echo json_encode(['status' => 'success', 'data' => $formattedRifas]);
    } catch (PDOException $e) {
        error_log('Error al obtener rifas activas: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error al obtener rifas activas: ' . $e->getMessage()]);
    }
}

/**
 * Registra uno o varios boletos para un cliente y rifa.
 */
function darDeAltaBoletos($pdo, $clienteId, $rifaId, $cantidadBoletos) {
    if (empty($clienteId) || empty($rifaId) || $cantidadBoletos < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para dar de alta boletos.']);
        return;
    }

    $pdo->beginTransaction(); // Iniciar la transacción para atomicidad
    try {
        $stmt = $pdo->prepare("INSERT INTO boletos (clienteId, rifaId, fecha) VALUES (:clienteId, :rifaId, NOW())");
        $boletosGenerados = [];
        for ($i = 0; $i < $cantidadBoletos; $i++) {
            $stmt->execute([':clienteId' => $clienteId, ':rifaId' => $rifaId]);
            $boletosGenerados[] = $pdo->lastInsertId(); // Opcional: guardar los IDs generados
        }
        $pdo->commit(); // Confirmar la transacción
        echo json_encode(['status' => 'success', 'message' => $cantidadBoletos . ' boletos registrados correctamente.', 'boletos_ids' => $boletosGenerados]);
    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir la transacción si hay un error
        error_log('Error al dar de alta boletos: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al registrar los boletos: ' . $e->getMessage()]);
    }
}

/**
 * Lista los boletos de un cliente específico para una rifa determinada.
 */
function listarBoletosPorClienteYRifa($pdo, $clienteId, $rifaId) {
    if (empty($clienteId) || empty($rifaId)) {
        echo json_encode(['status' => 'error', 'message' => 'IDs de cliente o rifa no proporcionados.']);
        return;
    }
    try {
        $stmt = $pdo->prepare("SELECT b.id, b.fecha, c.nombre AS cliente_nombre, c.apellidoPaterno, c.apellidoMaterno, r.nombre AS rifa_nombre, r.fecha AS rifa_fecha_sorteo, r.precio AS rifa_precio FROM boletos b JOIN clientes c ON b.clienteId = c.id JOIN rifas r ON b.rifaId = r.id WHERE b.clienteId = :clienteId AND b.rifaId = :rifaId ORDER BY b.fecha DESC");
        $stmt->execute([':clienteId' => $clienteId, ':rifaId' => $rifaId]);
        $boletos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $boletos]);
    } catch (PDOException $e) {
        error_log('Error al obtener boletos por cliente y rifa: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error al obtener boletos: ' . $e->getMessage()]);
    }
}

/**
 * Función principal para generar los PDFs (boleto y adicional) y enviarlos por correo.
 */
function generarYEnviarPdfs($pdo, $clienteId, $boletosIds) {
    if (empty($clienteId) || empty($boletosIds)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos para generar y enviar PDFs.']);
        return;
    }

    try {
        // 1. Obtener datos del cliente para el correo
        $stmtCliente = $pdo->prepare("SELECT nombre, apellidoPaterno, correo FROM clientes WHERE id = :clienteId");
        $stmtCliente->execute([':clienteId' => $clienteId]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente || empty($cliente['correo'])) {
            echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado o sin correo.']);
            return;
        }

        // 2. Obtener datos detallados de los boletos seleccionados para el PDF
        // Aseguramos que los IDs sean enteros para la consulta IN
        $cleanBoletosIds = array_map('intval', $boletosIds); 
        $placeholders = implode(',', array_fill(0, count($cleanBoletosIds), '?'));
        
        $stmtBoletos = $pdo->prepare("SELECT b.id, b.fecha, c.nombre AS cliente_nombre, c.apellidoPaterno, c.apellidoMaterno, c.telefono, c.correo AS cliente_correo, r.nombre AS rifa_nombre, r.fecha AS rifa_fecha_sorteo, r.precio AS rifa_precio FROM boletos b JOIN clientes c ON b.clienteId = c.id JOIN rifas r ON b.rifaId = r.id WHERE b.id IN ($placeholders) ORDER BY b.id ASC");
        $stmtBoletos->execute($cleanBoletosIds);
        $detallesBoletos = $stmtBoletos->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detallesBoletos)) {
            echo json_encode(['status' => 'error', 'message' => 'No se encontraron detalles para los boletos especificados.']);
            return;
        }

        // 3. Generar PDF de los boletos usando tu lógica FPDI/TCPDF
        $pdfPathBoletos = generarPDFBoletosInterno($detallesBoletos); 
        if (!$pdfPathBoletos) {
            echo json_encode(['status' => 'error', 'message' => 'Error al generar el PDF de boletos.']);
            return;
        }

        // 4. Generar el PDF adicional (ej. Bases de la rifa, info general)
        // $pdfPathAdicional = generarPDFAdicionalInterno(); 
        // if (!$pdfPathAdicional) {
        //     // Limpiar el primer PDF si el segundo falla
        //     if (file_exists($pdfPathBoletos)) unlink($pdfPathBoletos);
        //     echo json_encode(['status' => 'error', 'message' => 'Error al generar el PDF adicional.']);
        //     return;
        // }
        $pdfPathAdicional = __DIR__ . '/../documentos/BASES_T_Y_C.pdf';

        // 5. Enviar el correo electrónico
        $asunto_correo = 'Tus Boletos y Detalles de la Rifa - ' . $detallesBoletos[0]['rifa_nombre'];
        $cuerpo_correo = getCorreoTemplate($cliente['nombre'], $detallesBoletos[0]['rifa_nombre']); // Usa tu plantilla de correo
        
        $mailSent = enviarCorreoInterno(
            $cliente['correo'], 
            $asunto_correo, 
            $cuerpo_correo, 
            [$pdfPathBoletos, $pdfPathAdicional]
        );

        // 6. Limpiar archivos PDF temporales después del envío
        if (file_exists($pdfPathBoletos)) unlink($pdfPathBoletos);
        // if (file_exists($pdfPathAdicional)) unlink($pdfPathAdicional);

        if ($mailSent) {
            echo json_encode(['status' => 'success', 'message' => 'PDFs generados y correo enviado exitosamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo.']);
        }

    } catch (PDOException $e) {
        error_log('Error en generarYEnviarPdfs (DB): ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos al generar/enviar PDFs: ' . $e->getMessage()]);
    } catch (Exception $e) { 
        error_log('Error en generarYEnviarPdfs (Librería): ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error en la generación de PDFs o envío de correo: ' . $e->getMessage()]);
    }
}

/**
 * Lista clientes que tienen al menos un boleto asociado a una rifa activa.
 */
function listarClientesConBoletosActivos($pdo, $pagina, $limite, $busqueda) {
    try {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        // Consulta principal para clientes con boletos en rifas activas
        $sql = "SELECT DISTINCT c.id, c.nombre, c.apellidoPaterno, c.apellidoMaterno, c.correo, c.telefono, c.pais, c.estado, c.ciudad
                FROM clientes c
                JOIN boletos b ON c.id = b.clienteId
                JOIN rifas r ON b.rifaId = r.id
                WHERE r.activa = 1 AND r.fecha >= CURRENT_DATE()"; // Condición de rifa activa
        
        // Consulta para el conteo total de esos clientes
        $sqlTotal = "SELECT COUNT(DISTINCT c.id)
                     FROM clientes c
                     JOIN boletos b ON c.id = b.clienteId
                     JOIN rifas r ON b.rifaId = r.id
                     WHERE r.activa = 1 AND r.fecha >= CURRENT_DATE()"; // Misma condición
        
        $params = [];
        
        if (!empty($busqueda)) {
            $searchPattern = '%' . $busqueda . '%';
            $sql .= " AND (c.nombre LIKE :busqueda_nombre OR
                     c.apellidoPaterno LIKE :busqueda_apellido_paterno OR
                     c.apellidoMaterno LIKE :busqueda_apellido_materno OR
                     c.correo LIKE :busqueda_correo OR
                     c.telefono LIKE :busqueda_telefono )";

            $sqlTotal .= " AND (c.nombre LIKE :busqueda_nombre OR
                          c.apellidoPaterno LIKE :busqueda_apellido_paterno OR
                          c.apellidoMaterno LIKE :busqueda_apellido_materno OR
                          c.correo LIKE :busqueda_correo OR
                          c.telefono LIKE :busqueda_telefono )";

            $params[':busqueda_nombre'] = $searchPattern;
            $params[':busqueda_apellido_paterno'] = $searchPattern;
            $params[':busqueda_apellido_materno'] = $searchPattern;
            $params[':busqueda_correo'] = $searchPattern;
            $params[':busqueda_telefono'] = $searchPattern;
        }
        
        $sql .= " ORDER BY c.nombre ASC LIMIT :limite OFFSET :offset";
        
        // Obtener el total de registros
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute($params); 
        $totalClientes = $stmtTotal->fetchColumn();

        // Preparar y ejecutar la consulta principal (datos)
        $stmt = $pdo->prepare($sql);
        
        // Combina los parámetros de búsqueda con los de paginación
        $allParams = array_merge($params, [
            ':limite' => $limite,
            ':offset' => $offset
        ]);

        $stmt->execute($allParams); 
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaginas = ceil($totalClientes / $limite);

        echo json_encode([
            'status' => 'success', 
            'data' => $clientes,
            'totalRegistros' => $totalClientes,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $pagina,
            'limite' => $limite
        ]);
    } catch (PDOException $e) {
        error_log('Error al listar clientes con boletos activos: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al listar los clientes con boletos activos: ' . $e->getMessage()]);
    }
}


// --- FUNCIONES AUXILIARES PARA PDF Y CORREO ---

/**
 * Genera un PDF a partir de tu plantilla, rellenando con los datos de múltiples boletos.
 * Adapta el script PHP que proporcionaste.
 */
function generarPDFBoletosInterno($detallesBoletos) {
    // Asegúrate de que las carpetas existan
    $tempDir = __DIR__ . '/../temp/'; // Ruta relativa a la raíz del proyecto
    // $tempDir = realpath($tempDir);
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $pdf_original_path = __DIR__ . '/../documentos/boleto.pdf'; // Tu plantilla PDF
    // $pdf_original_path = realpath($pdf_original_path);
    
    if (!file_exists($pdf_original_path)) {
        error_log("Error: El archivo PDF original no se encontró en la ruta: " . $pdf_original_path);
        return false;
    }

    $pdf = new Fpdi();
    try {
        $pageCount = $pdf->setSourceFile($pdf_original_path);
    } catch (\Exception $e) {
        error_log("Error al cargar el PDF original: " . $e->getMessage());
        return false;
    }

    // Iterar sobre cada boleto y añadirlo al PDF como una nueva página basada en la plantilla
    foreach ($detallesBoletos as $infoBoleto) {
        // Recorrer y procesar cada página del PDF original para CADA boleto
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            // Añadir una nueva página al documento de salida con el mismo tamaño y orientación
            if ($size['height'] > $size['width']) {
                $pdf->AddPage('P', [$size['width'], $size['height'] + 15]); // Ajuste de altura
            } else {
                $pdf->AddPage('L', [$size['width'], $size['height'] + 15]); // Ajuste de altura
            }
            $pdf->useTemplate($templateId);

            // --- Rellenar datos del boleto actual ($infoBoleto) ---
            // Nombre completo del cliente
            $pdf->SetFont('helvetica', 'B', 16); 
            $pdf->SetTextColor(0, 0, 0); 
            $pdf->SetXY(20, 65);
            $pdf->Write(0, htmlspecialchars($infoBoleto['cliente_nombre'].' '.$infoBoleto['apellidoPaterno'].' '.$infoBoleto['apellidoMaterno']));

            // Teléfono
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(0, 0, 0); 
            $pdf->SetXY(20, 91);
            $pdf->Write(0, htmlspecialchars($infoBoleto['telefono']));

            // Correo
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(0, 0, 0); 
            $pdf->SetXY(20, 117);
            $pdf->Write(0, htmlspecialchars($infoBoleto['cliente_correo']));

            // Folio (ID del boleto), con relleno de ceros
            $folio = str_pad($infoBoleto['id'], 6, '0', STR_PAD_LEFT);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(0, 0, 0); 
            $pdf->SetXY(74, 136); // Revisa tus coordenadas, estas son las de tu script
            $pdf->Write(0, $folio);
            
            // Puedes añadir más campos si los necesitas, como nombre de la rifa, precio, etc.
            // Ejemplo:
            // $pdf->SetXY(20, 145);
            // $pdf->Write(0, 'Rifa: ' . htmlspecialchars($infoBoleto['rifa_nombre']));
            // $pdf->SetXY(20, 155);
            // $pdf->Write(0, 'Precio: $' . number_format($infoBoleto['rifa_precio'], 2));
        }
    } 

    $filename = 'boleto_LOVI_' . $infoBoleto['cliente_nombre'] . '.pdf';
    $filepath = $tempDir . $filename; 

    try {
        $pdf->Output($filepath, 'F'); 
        return $filepath;
    } catch (\Exception $e) {
        error_log("Error al guardar el PDF de boletos: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera el segundo PDF adicional (e.g., con bases de la rifa, términos y condiciones).
 */
// function generarPDFAdicionalInterno() {
//     // Este PDF podría ser un documento estático o generado dinámicamente.
//     // Para este ejemplo, crearemos uno simple con Dompdf.
//     // Si tienes una plantilla para este PDF y quieres usar FPDI, adapta esta función.
    
//     $tempDir = __DIR__ . '/../temp/';
//     if (!is_dir($tempDir)) {
//         mkdir($tempDir, 0777, true);
//     }

//     $dompdf = new Dompdf();
//     $html = '<h1>Información Adicional de la Rifa</h1>';
//     $html .= '<p>Este es un documento adicional con detalles importantes sobre la rifa.</p>';
//     $html .= '<p>¡Gracias por participar!</p>';
//     $html .= '<p>Para más información, visita nuestro sitio web.</p>';

//     $dompdf->loadHtml($html);
//     $dompdf->setPaper('A4', 'portrait');
//     $dompdf->render();

//     $filename = 'info_adicional_' . time() . '.pdf';
//     $filepath = $tempDir . $filename; 

//     try {
//         file_put_contents($filepath, $dompdf->output());
//         return $filepath;
//     } catch (\Exception $e) {
//         error_log("Error al guardar el PDF adicional: " . $e->getMessage());
//         return false;
//     }
// }

/**
 * Plantilla HTML para el cuerpo del correo electrónico.
 */
function getCorreoTemplate($nombreCliente, $nombreRifa) {
    // Puedes personalizar este HTML para tu plantilla de correo
    $template = '
        <p>Hola ' . htmlspecialchars($nombreCliente) . ', antes que todo esperamos que te encuentres muy bien y agradecerte tu confianza en LOVI Agencia de Viajes,</p>
        <p>Bienvenida a la RIFA DE VIAJE A CANCÚN; te compartimos en el PDF adjunto tu boleto de la rifa personalizado con tus datos, y asimismo agregamos en el PDF adjunto las politicas y condiciones de la rifa donde podras encontrar las fechas de venta de boletos, limite de boletos por adquirir y fecha de la rifa que sera el domingo 24 de agosto, el horario esta por definirse y te lo haremos saber por este mismo medio, tu asesor te confirmara y en nuestras redes sociales lo estaremos aunciando.</p>
        <br>
        <p>Por tanto en este mail validamos:</p>
        <br>
        <p>1. Confirmacion de transferencia por XXXXX pesos</p>
        <p>2. Participacion en la rifa</p>
        <p>3. Envio de Bases, Politicas y Condiciones.</p>
        <p>4. Flyer publicitario.</p>
        <p>4. Tus boleto personalizado.</p>
        <br>
        <p>Esperamos seas laXXXX gadorXXXXX de este viaje para 2 personas todo pagado: <span style="color: #01bbc8;">(Vuelos redondos CDMX - Cancun, Transporte redondo Aeropuerto - Hotel y Hospedaje All Inclusive)</span></p>
        <br>
        <p>Te deseamos mucho exito y esperamos recibirte a ti y tu acompañante en las bellas playas de Cancun.</p>
        <br>
        <p style="color: #01bbc8;"><strong>EQUIPO LOVI</strong></p>
        <p style="color: #01bbc8;"><strong>¡NOS VEMOS EN EL LOVI!</strong></p>
    ';
    return $template;
}

/**
 * Envía un correo electrónico con adjuntos usando PHPMailer.
 */
function enviarCorreoInterno($toEmail, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true); 

    // try {
        // Configuración del servidor SMTP de GoDaddy/Microsoft 365
        $mail->isSMTP();
        $mail->Host       = 'smtpout.secureserver.net'; // Servidor SMTP de GoDaddy Workspace
        $mail->SMTPAuth   = true;
        $mail->Username   = 'micotizacion@lovitravel.com'; // << ¡CAMBIA ESTO! Tu dirección de correo completa de GoDaddy Workspace
        $mail->Password   = 'loviav07072025'; // << ¡CAMBIA ESTO! La contraseña de esa cuenta de correo de GoDaddy Workspace
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_SSL;   // o PHPMailer::ENCRYPTION_STARTTLS
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Para puerto 465 (SSL/TLS implícito)
        $mail->Port       = 465;                         // o 587 para STARTTLS                             

        // Remitente
        $mail->setFrom('micotizacion@lovitravel.com', 'LOVI Travel'); // << ¡CAMBIA ESTO! Tu correo y tu nombre

        // Destinatario
        $mail->addAddress($toEmail);
        $mail->addBCC('one_angel94@hotmail.com');

        // Contenido del correo
        $mail->isHTML(true);                                        
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Versión en texto plano
        

        // Adjuntos
        foreach ($attachments as $filePath) {
            if (file_exists($filePath)) {
                $mail->addAttachment($filePath);
            } else {
                error_log("Archivo adjunto no encontrado: " . $filePath);
            }
        }

        $mail->send();
        return true;
    // } catch (Exception $e) {
    //     error_log("Error al enviar correo: {$mail->ErrorInfo}");
    //     return false;
    // }
}
?>