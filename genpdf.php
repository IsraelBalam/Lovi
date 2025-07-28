<?php

require_once 'conexion.php'; // Incluye tu configuración de base de datos

$dbMysql = new DBConnectionLocal();
$dbMysql->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

$cliente = 1;//esto se va a sustituir por el cliente que se quiera generar el pdf

$qry = "SELECT c.*, b.id as folio FROM boletos b
    JOIN clientes c on c.id = b.clienteId
    WHERE c.id = ".$cliente; // Consulta de ejemplo, ajusta según tu tabla

$Consulta = $dbMysql->SQLQuery($qry);
$infoBoleto = $Consulta->fetch(PDO::FETCH_ASSOC);


// //------------------------------


require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

// --- Configuración ---
$pdf_original_path = __DIR__ . '/boleto.pdf'; // ¡Cambia esta ruta a tu PDF existente!
$pdf_destino_path  = __DIR__ . '/documentos/mi_documento_con_texto.pdf'; // Ruta y nombre para el nuevo PDF
$pdf_temp_path  = __DIR__ . '/temp/boletoTemp.pdf'; // Ruta y nombre para achivo temporal

// Asegúrate de que la carpeta 'documentos' exista y sea escribible
if (!is_dir(__DIR__ . '/documentos/')) {
    mkdir(__DIR__ . '/documentos/', 0777, true);
}
// Asegúrate de que la carpeta 'temp' exista y sea escribible
if (!is_dir(__DIR__ . '/temp/')) {
    mkdir(__DIR__ . '/temp/', 0777, true);
}

// Verifica que el PDF original exista
if (!file_exists($pdf_original_path)) {
    die("Error: El archivo PDF original no se encontró en la ruta: " . $pdf_original_path);
}

// --- 1. Crear una nueva instancia de FPDI (basada en TCPDF) ---
$pdf = new Fpdi();

// --- 2. Cargar el PDF original ---
try {
    $pageCount = $pdf->setSourceFile($pdf_original_path);
} catch (\Exception $e) {
    die("Error al cargar el PDF original: " . $e->getMessage());
}

// --- 3. Recorrer y procesar cada página del PDF original ---
for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
    // Importar una página del PDF original
    $templateId = $pdf->importPage($pageNo);

    // Obtener el tamaño de la página original
    $size = $pdf->getTemplateSize($templateId);

    // echo 'height: '.$size['height'];
    // echo 'width: '.$size['width'];

    // Añadir una nueva página al documento de salida con el mismo tamaño y orientación
    if ($size['height'] > $size['width']) {
        // Orientación vertical (Portrait)
        $pdf->AddPage('P', array($size['width'], $size['height']+15));
    } else {
        // Orientación horizontal (Landscape)
        $pdf->AddPage('L', array($size['width'], $size['height']+15));
    }

    // Usar la página importada como plantilla en la nueva página
    $pdf->useTemplate($templateId);

    // nombre
    $pdf->SetFont('helvetica', 'B', 16); // Fuente, estilo (Bold), tamaño
    $pdf->SetTextColor(0, 0, 0);       // Color del texto (RGB: negro)
    $pdf->SetXY(20, 65);// Posicionar el texto (mm desde la izquierda, mm desde arriba)
    $pdf->Write(0, $infoBoleto['nombre'].' '.$infoBoleto['apellidoPaterno'].' '.$infoBoleto['apellidoMaterno']);

    // telefono
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 0, 0); // negro
    $pdf->SetXY(20, 91);
    $pdf->Write(0, $infoBoleto['telefono']);

    // telefono
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 0, 0); // negro
    $pdf->SetXY(20, 117);
    $pdf->Write(0, $infoBoleto['correo']);


    $folio = str_pad($infoBoleto['folio'], 6, '0', STR_PAD_LEFT);

     // telefono
     $pdf->SetFont('helvetica', 'B', 16);
     $pdf->SetTextColor(0, 0, 0); // negro
     $pdf->SetXY(74, 136);
     $pdf->Write(0, $folio);
     
} 

// --- 5. Guardar el nuevo PDF ---
try {
    $pdf->Output($pdf_destino_path, 'F'); // 'F' para guardar en un archivo
    echo "¡PDF editado y guardado exitosamente en: " . realpath($pdf_destino_path) . "!";
} catch (\Exception $e) {
    die("Error al guardar el nuevo PDF: " . $e->getMessage());
}

?>