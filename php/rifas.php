<?php
// Asegúrate de que la ruta a tu archivo de conexión sea correcta.
include '../conexion.php'; 

header('Content-Type: application/json');

// Lee el cuerpo de la petición. Si es JSON, aquí estará.
$request_body = file_get_contents("php://input");
// Decodifica el JSON a un array asociativo. Si no es JSON o está vacío, $data será null o un array vacío.
$data = json_decode($request_body, true);

// Determina la acción. Priorizamos el 'accion' que viene en el JSON ($data['accion']).
// Si no viene en JSON (ej. para peticiones GET o DELETE sin JSON body), entonces revisamos $_POST o $_GET.
$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';

// Instanciación de tu conexión a la base de datos
$dbMysql = new DBConnectionLocal();
$pdo = $dbMysql; 
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

switch ($accion) {
    case 'listar':
        $pagina = $_GET['pagina'] ?? 1;
        $limite = $_GET['limite'] ?? 10;
        $busqueda = $_GET['busqueda'] ?? ''; 
        listarRifas($pdo, $pagina, $limite, $busqueda);
        break;
    case 'agregar':
        agregarRifa($pdo, $data); 
        break;
    case 'obtener':
        obtenerRifa($pdo); 
        break;
    case 'editar':
        editarRifa($pdo, $data);
        break;
    case 'eliminar':
        eliminarRifa($pdo);
        break;
    case 'cambiarEstadoActiva': // Nueva acción para el switch
        cambiarEstadoActiva($pdo, $data);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}

// --- FUNCIONES CRUD PARA RIFAS ---

function listarRifas($pdo, $pagina, $limite, $busqueda) {
    try {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT id, nombre, descripcion, precio, fecha, activa FROM rifas";
        $sqlTotal = "SELECT COUNT(*) FROM rifas";
        
        $params = [];        // Parámetros para la consulta principal (incluye búsqueda y paginación)
        $paramsTotal = [];   // Parámetros solo para la consulta de conteo (solo búsqueda)

        if (!empty($busqueda)) {
            $searchPattern = '%' . $busqueda . '%';
            
            // Cláusula WHERE con placeholders descriptivos
            $whereClause = " WHERE nombre LIKE :busqueda_nombre OR 
                               descripcion LIKE :busqueda_descripcion OR 
                               precio LIKE :busqueda_precio"; // El precio debe ser un campo de texto si se busca con LIKE
                                                              // Si precio es numérico, considera CAST(precio AS CHAR) LIKE :busqueda_precio

            $sql .= $whereClause;
            $sqlTotal .= $whereClause;
            
            // Llenar los arrays de parámetros
            $params[':busqueda_nombre'] = $searchPattern;
            $params[':busqueda_descripcion'] = $searchPattern;
            $params[':busqueda_precio'] = $searchPattern;

            // Los parámetros para el conteo son los mismos que los de búsqueda
            $paramsTotal = $params; 
        }
        
        // Añadir orden y paginación a la consulta principal
        $sql .= " ORDER BY id DESC LIMIT :limite OFFSET :offset"; 
        
        // Añadir parámetros de paginación al array de parámetros principal
        $params[':limite'] = $limite;
        $params[':offset'] = $offset;

        // --- Obtener el total de registros ---
        $stmtTotal = $pdo->prepare($sqlTotal);
        // Ejecutar con los parámetros de búsqueda (o vacío si no hay búsqueda)
        $stmtTotal->execute($paramsTotal); 
        $totalRifas = $stmtTotal->fetchColumn();

        // --- Preparar y ejecutar la consulta principal (datos) ---
        $stmt = $pdo->prepare($sql);
        // Ejecutar con todos los parámetros (búsqueda y paginación)
        $stmt->execute($params); 
        
        $rifas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaginas = ceil($totalRifas / $limite);

        echo json_encode([
            'status' => 'success', 
            'data' => $rifas,
            'totalRegistros' => $totalRifas,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $pagina,
            'limite' => $limite
        ]);
    } catch (PDOException $e) {
        // En un entorno de producción, es mejor no exponer e->getMessage() directamente
        error_log('Error al listar rifas: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al listar las rifas.']);
    }
}

function agregarRifa($pdo, $data) {
    $nombre = $data['nombre'] ?? '';
    $descripcion = $data['descripcion'] ?? null;
    $precio = $data['precio'] ?? null;
    $fecha = $data['fecha'] ?? null;
    $activa = isset($data['activa']) ? (int)$data['activa'] : 0; // Convertir a int (0 o 1)

    if (empty($nombre) || is_null($precio) || empty($fecha)) {
        echo json_encode(['status' => 'error', 'message' => 'Campos obligatorios (nombre, precio, fecha) incompletos.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO rifas (nombre, descripcion, precio, fecha, activa) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre, 
            $descripcion, 
            $precio, 
            $fecha, 
            $activa
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Rifa agregada correctamente.', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al agregar rifa: ' . $e->getMessage()]);
    }
}

function obtenerRifa($pdo) {
    $id = $_GET['id'] ?? ''; 

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de rifa no proporcionado para obtener.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nombre, descripcion, precio, fecha, activa FROM rifas WHERE id = ?");
        $stmt->execute([$id]);
        $rifa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rifa) {
            echo json_encode(['status' => 'success', 'data' => $rifa]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Rifa no encontrada.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al obtener rifa: ' . $e->getMessage()]);
    }
}

function editarRifa($pdo, $data) {
    $id = $data['id'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $descripcion = $data['descripcion'] ?? null;
    $precio = $data['precio'] ?? null;
    $fecha = $data['fecha'] ?? null;
    $activa = isset($data['activa']) ? (int)$data['activa'] : 0; // Convertir a int (0 o 1)

    if (empty($id) || empty($nombre) || is_null($precio) || empty($fecha)) {
        echo json_encode(['status' => 'error', 'message' => 'Campos obligatorios (ID, nombre, precio, fecha) incompletos para la edición.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE rifas SET nombre = ?, descripcion = ?, precio = ?, fecha = ?, activa = ? WHERE id = ?");
        $stmt->execute([
            $nombre, 
            $descripcion, 
            $precio, 
            $fecha, 
            $activa, 
            $id
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Rifa actualizada correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar rifa: ' . $e->getMessage()]);
    }
}

function eliminarRifa($pdo) {
    $id = $_POST['id'] ?? ''; 

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de rifa no proporcionado para eliminar.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM rifas WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Rifa eliminada correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar rifa: ' . $e->getMessage()]);
    }
}

function cambiarEstadoActiva($pdo, $data) {
    $id = $data['id'] ?? '';
    $activa = isset($data['activa']) ? (int)$data['activa'] : null;

    if (empty($id) || !isset($activa) || ($activa !== 0 && $activa !== 1)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o inválidos para cambiar estado de activa.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE rifas SET activa = ? WHERE id = ?");
        $stmt->execute([$activa, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Estado de rifa actualizado correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al cambiar estado de rifa: ' . $e->getMessage()]);
    }
}
?>