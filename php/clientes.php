<?php
include '../conexion.php'; 

header('Content-Type: application/json');

$request_body = file_get_contents("php://input");
$data = json_decode($request_body, true);

$accion = $data['accion'] ?? $_POST['accion'] ?? $_GET['accion'] ?? '';


$dbMysql = new DBConnectionLocal();
$pdo = $dbMysql; // Asumiendo que $dbMysql directamente es tu objeto PDO o es usable como tal.
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ); // Configura el modo de fetch globalmente.

switch ($accion) {
    case 'listar':
        $pagina = $_GET['pagina'] ?? 1;
        $limite = $_GET['limite'] ?? 10; // Cantidad de elementos por página
        $busqueda = $_GET['busqueda'] ?? ''; // Término de búsqueda
        listarClientes($pdo, $pagina, $limite, $busqueda);
        break;
    case 'agregar':
        agregarCliente($pdo, $data); 
        break;
    case 'obtener':
        obtenerCliente($pdo); // El ID se obtiene de $_GET, no de JSON para esta acción
        break;
    case 'editar':
        editarCliente($pdo, $data);
        break;
    case 'eliminar':
        eliminarCliente($pdo);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        break;
}

function listarClientes($pdo, $pagina, $limite, $busqueda) {
    try {
        $pagina = max(1, (int)$pagina);
        $limite = max(1, (int)$limite);
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT id, nombre, apellidoPaterno, apellidoMaterno, telefono, correo, pais, estado, ciudad FROM clientes";
        $sqlTotal = "SELECT COUNT(*) FROM clientes";
        
        $params = []; // Array para almacenar los parámetros de la consulta de datos (incluye paginación)
        $paramsTotal = []; // Array para almacenar solo los parámetros de la consulta de conteo

        // --- Lógica de búsqueda condicional: construimos la cláusula WHERE y añadimos parámetros ---
        if (!empty($busqueda)) {
            $searchPattern = '%' . $busqueda . '%';
            $whereClause = " WHERE nombre LIKE :busqueda_nombre OR 
                               apellidoPaterno LIKE :busqueda_apellido_paterno OR 
                               apellidoMaterno LIKE :busqueda_apellido_materno OR 
                               correo LIKE :busqueda_correo OR 
                               telefono LIKE :busqueda_telefono ";
            
            // Añadimos la cláusula WHERE a ambas consultas
            $sql .= $whereClause;
            $sqlTotal .= $whereClause;
            
            // Añadimos el patrón de búsqueda a los parámetros de ambas consultas
            $params[':busqueda_nombre'] = $searchPattern;
            $params[':busqueda_apellido_paterno'] = $searchPattern;
            $params[':busqueda_apellido_materno'] = $searchPattern;
            $params[':busqueda_correo'] = $searchPattern;
            $params[':busqueda_telefono'] = $searchPattern;

            // Para la consulta de conteo, los parámetros son los mismos
            $paramsTotal = $params; 
        }
        
        // --- Añadimos la paginación a la consulta principal ---
        $sql .= " ORDER BY nombre ASC LIMIT :limite OFFSET :offset";
        
        // Añadimos los parámetros de paginación al array de parámetros de la consulta principal
        $params[':limite'] = $limite;
        $params[':offset'] = $offset;

        // --- Obtener el total de registros ---
        $stmtTotal = $pdo->prepare($sqlTotal);
        // Aquí pasamos los parámetros directamente al execute. PDO los vinculará.
        $stmtTotal->execute($paramsTotal); 
        $totalClientes = $stmtTotal->fetchColumn();

        // --- Preparar y ejecutar la consulta principal (datos) ---
        $stmt = $pdo->prepare($sql);
        // Aquí pasamos TODOS los parámetros (búsqueda y paginación) al execute.
        $stmt->execute($params); 
        
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
        error_log('Error al listar clientes: ' . $e->getMessage()); 
        echo json_encode(['status' => 'error', 'message' => 'Ocurrió un error al listar los clientes.']);
    }
}

function agregarCliente($pdo, $data) {
    $nombre = $data['nombre'] ?? '';
    $apellidoPaterno = $data['apellidoPaterno'] ?? '';
    $apellidoMaterno = $data['apellidoMaterno'] ?? ''; 
    $telefono = $data['telefono'] ?? '';
    $correo = $data['correo'] ?? '';
    $pais = $data['pais'] ?? null;
    $estado = $data['estado'] ?? null;
    $ciudad = $data['ciudad'] ?? null;

    if (empty($nombre) || empty($apellidoPaterno) || empty($telefono) || empty($correo)) {
        echo json_encode(['status' => 'error', 'message' => 'Campos obligatorios (nombre, apellido paterno, teléfono, correo) incompletos.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO clientes (nombre, apellidoPaterno, apellidoMaterno, telefono, correo, pais, estado, ciudad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nombre, 
            $apellidoPaterno, 
            $apellidoMaterno, 
            $telefono, 
            $correo, 
            $pais, 
            $estado, 
            $ciudad
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Cliente agregado correctamente.', 'id' => $pdo->lastInsertId()]); // Opcional: devolver el ID
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al agregar cliente: ' . $e->getMessage()]);
    }
}

function obtenerCliente($pdo) {
    // Para 'obtener', el ID viene de la URL (GET)
    $id = $_GET['id'] ?? ''; 

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de cliente no proporcionado para obtener.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nombre, apellidoPaterno, apellidoMaterno, telefono, correo, pais, estado, ciudad FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC); // Usar FETCH_ASSOC para consistencia con la respuesta JSON

        if ($cliente) {
            echo json_encode(['status' => 'success', 'data' => $cliente]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al obtener cliente: ' . $e->getMessage()]);
    }
}

function editarCliente($pdo, $data) {
    // Validaciones y extracción de datos del array $data
    $id = $data['id'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $apellidoPaterno = $data['apellidoPaterno'] ?? '';
    $apellidoMaterno = $data['apellidoMaterno'] ?? null;
    $telefono = $data['telefono'] ?? '';
    $correo = $data['correo'] ?? '';
    $pais = $data['pais'] ?? null;
    $estado = $data['estado'] ?? null;
    $ciudad = $data['ciudad'] ?? null;

    if (empty($id) || empty($nombre) || empty($apellidoPaterno) || empty($telefono) || empty($correo)) {
        echo json_encode(['status' => 'error', 'message' => 'Campos obligatorios (ID, nombre, apellido paterno, teléfono, correo) incompletos para la edición.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE clientes SET nombre = ?, apellidoPaterno = ?, apellidoMaterno = ?, telefono = ?, correo = ?, pais = ?, estado = ?, ciudad = ? WHERE id = ?");
        $stmt->execute([
            $nombre, 
            $apellidoPaterno, 
            $apellidoMaterno, 
            $telefono, 
            $correo, 
            $pais, 
            $estado, 
            $ciudad, 
            $id
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Cliente actualizado correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar cliente: ' . $e->getMessage()]);
    }
}

function eliminarCliente($pdo) {
    // Para 'eliminar', tu JS está enviando por $_POST (no como JSON con contentType),
    // así que sigue siendo correcto leer de $_POST.
    $id = $_POST['id'] ?? ''; 

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID de cliente no proporcionado para eliminar.']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Cliente eliminado correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar cliente: ' . $e->getMessage()]);
    }
}
?>