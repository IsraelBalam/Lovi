<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lovi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
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
        <h1 class="mb-4 text-center">Gestión de Rifas</h1>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" id="inputBusquedaRifa" placeholder="Buscar por nombre, descripción, precio...">
                    <button class="btn btn-outline-secondary" type="button" id="btnBuscarRifa">Buscar</button>
                    <button class="btn btn-outline-warning" type="button" id="btnLimpiarBusquedaRifa">Limpiar</button>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rifaModal" id="btnNuevaRifa">
                    Registrar Nueva Rifa
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Precio</th>
                        <th>Fecha</th>
                        <th>Activa</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRifas">
                    </tbody>
            </table>
        </div>

        <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center" id="paginacionRifas">
                </ul>
        </nav>
    </div>

    <div class="modal fade" id="rifaModal" tabindex="-1" aria-labelledby="rifaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rifaModalLabel">Registrar Rifa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formRifa">
                        <input type="hidden" id="rifaId" name="id">
                        <div class="mb-3">
                            <label for="nombreRifa" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombreRifa" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcionRifa" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionRifa" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="precioRifa" class="form-label">Precio</label>
                            <input type="number" step="0.01" class="form-control" id="precioRifa" name="precio" required>
                        </div>
                        <div class="mb-3">
                            <label for="fechaRifa" class="form-label">Fecha</label>
                            <input type="datetime-local" class="form-control" id="fechaRifa" name="fecha" required>
                        </div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activaRifa" name="activa" role="switch">
                            <label class="form-check-label" for="activaRifa">Activa</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-success" id="btnGuardarRifa">Guardar Rifa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>
    <script src="js/rifas.js"></script>
</body>
</html>