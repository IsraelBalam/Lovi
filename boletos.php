<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lovi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .background-div {
            /* Ruta de tu imagen de fondo */
            background-image: url('img/logo.jpg'); 
            /* Para que la imagen cubra todo el div */
            background-size: auto 80vh; 
            /* Centra la imagen */
            background-position: center; 
            /* Evita que la imagen se repita */
            background-repeat: no-repeat; 
            /* Altura mínima para que la imagen sea visible */
            min-height: 90vh;
            /* Color de respaldo si la imagen no carga */
            background-color: #fff; 
            /* Opcional: para centrar el texto "aqui" */
            display: flex; 
            justify-content: center;
            align-items: center;
            color: white; /* Color del texto para que contraste con la imagen */
            font-size: 1em;
        }
        .navbar {
            background-color: #01bbc8 !important; /* Color de fondo de la barra de navegación */
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php">LOVI</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link active" aria-current="page" href="clientes.php">Clientes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="boletos.php">Boletos</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="rifas.php">Rifas</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">Gestión de Boletos</h1>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" id="inputBusquedaClienteBoleto" placeholder="Buscar cliente por nombre o apellido...">
                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarClienteBoleto">Buscar</button>
                    <button class="btn btn-outline-warning" type="button" id="btnLimpiarBusquedaClienteBoleto">Limpiar</button>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registrarBoletoModal" id="btnRegistrarBoleto">
                    Registrar Nuevo Boleto
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Cliente</th>
                        <th>Correo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaClientesBoletos">
                    </tbody>
            </table>
        </div>

        <nav aria-label="Page navigation for clients">
            <ul class="pagination justify-content-center" id="paginacionClientesBoletos">
                </ul>
        </nav>

        <hr class="my-5">

        <div id="seccionDetalleBoletos" style="display: none;">
            <h2 class="mb-3">Boletos de <span id="nombreClienteSeleccionado"></span></h2>
            <input type="hidden" id="clienteIdSeleccionado">

            <div class="mb-3">
                <label for="selectRifaActiva" class="form-label">Seleccionar Rifa Activa:</label>
                <select class="form-select" id="selectRifaActiva">
                    </select>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-bordered table-sm">
                    <thead class="table-info">
                        <tr>
                            <th><input type="checkbox" id="seleccionarTodosBoletos"></th>
                            <th>ID Boleto</th>
                            <th>Fecha Registro</th>
                            <th>Rifa</th>
                            <th>Precio Rifa</th>
                        </tr>
                    </thead>
                    <tbody id="tablaBoletosClienteRifa">
                        </tbody>
                </table>
            </div>
            <button class="btn btn-success mt-3" id="btnGenerarEnviarPdfs" disabled>Generar y Enviar PDFs</button>
        </div>
    </div>

    <div class="modal fade" id="registrarBoletoModal" tabindex="-1" aria-labelledby="registrarBoletoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registrarBoletoModalLabel">Registrar Nuevo Boleto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formRegistrarBoleto">
                        <div class="mb-3">
                            <label for="selectClienteBoleto" class="form-label">Cliente:</label>
                            <select class="form-control" id="selectClienteBoleto" style="width: 100%;" required>
                                </select>
                        </div>
                        <div class="mb-3">
                            <label for="selectRifaBoleto" class="form-label">Rifa Activa:</label>
                            <select class="form-select" id="selectRifaBoleto" required>
                                </select>
                        </div>
                        <div class="mb-3">
                            <label for="cantidadBoletos" class="form-label">Cantidad de Boletos:</label>
                            <input type="number" class="form-control" id="cantidadBoletos" min="1" value="1" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-success">Registrar Boletos</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/boletos.js"></script>
</body>
</html>