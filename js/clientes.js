$(document).ready(function() {
    let paginaActual = 1;
    const limitePorPagina = 10; // Puedes ajustar este valor
    let terminoBusqueda = '';

    // Cargar clientes al iniciar la página
    cargarClientes();

    // Evento para el botón "Buscar"
    $('#btnBuscar').on('click', function() {
        terminoBusqueda = $('#inputBusqueda').val().trim();
        paginaActual = 1; // Reiniciar a la primera página en cada nueva búsqueda
        cargarClientes();
    });

    // Evento para limpiar búsqueda
    $('#btnLimpiarBusqueda').on('click', function() {
        $('#inputBusqueda').val('');
        terminoBusqueda = '';
        paginaActual = 1;
        cargarClientes();
    });

    // Evento para abrir el modal de registro
    $('#btnNuevoCliente').on('click', function() {
        $('#formCliente')[0].reset(); // Limpiar el formulario
        $('#clienteId').val(''); // Asegurar que el ID esté vacío para nuevo registro
        $('#clienteModalLabel').text('Registrar Cliente'); // Cambiar título del modal
        $('#btnGuardarCliente').text('Guardar Cliente').removeClass('btn-warning').addClass('btn-success');
    });

    // Manejar el envío del formulario de cliente (Agregar/Editar)
    $('#formCliente').on('submit', function(e) {
        e.preventDefault(); // Evitar el envío normal del formulario

        const clienteId = $('#clienteId').val();
        const accion = clienteId ? 'editar' : 'agregar'; // Determinar si es edición o nuevo registro

        const formData = {
            id: clienteId,
            nombre: $('#nombre').val(),
            apellidoPaterno: $('#apellidoPaterno').val(),
            apellidoMaterno: $('#apellidoMaterno').val(),
            telefono: $('#telefono').val(),
            correo: $('#correo').val(),
            pais: $('#pais').val(),
            estado: $('#estado').val(),
            ciudad: $('#ciudad').val()
        };

        $.ajax({
            url: 'php/clientes.php',
            type: 'POST',
            contentType: 'application/json', // Importante para enviar JSON
            data: JSON.stringify({ accion: accion, ...formData }), // Combinar acción y datos
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#clienteModal').modal('hide'); // Cerrar el modal
                    cargarClientes(); // Recargar la tabla de clientes
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', status, error, xhr.responseText);
                alert('Ocurrió un error al procesar la solicitud.');
            }
        });
    });

    // Función para cargar clientes en la tabla
    function cargarClientes() {
        $.ajax({
            url: 'php/clientes.php',
            type: 'GET',
            data: { 
                accion: 'listar', 
                pagina: paginaActual, 
                limite: limitePorPagina,
                busqueda: terminoBusqueda 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(function(cliente) {
                            html += `
                                <tr>
                                    <td>${cliente.nombre}</td>
                                    <td>${cliente.apellidoPaterno}</td>
                                    <td>${cliente.apellidoMaterno || ''}</td>
                                    <td>${cliente.telefono}</td>
                                    <td>${cliente.correo}</td>
                                    <td>${cliente.pais || ''}</td>
                                    <td>${cliente.estado || ''}</td>
                                    <td>${cliente.ciudad || ''}</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-editar" data-id="${cliente.id}" data-bs-toggle="modal" data-bs-target="#clienteModal">Editar</button>
                                        <button class="btn btn-sm btn-danger btn-eliminar" data-id="${cliente.id}">Eliminar</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html = `<tr><td colspan="9" class="text-center">No se encontraron clientes.</td></tr>`;
                    }
                    $('#tablaClientes').html(html);

                    // Generar paginación
                    generarPaginacion(response.totalPaginas, response.paginaActual);

                } else {
                    alert('Error al cargar clientes: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cargar clientes:', status, error, xhr.responseText);
                alert('Ocurrió un error al cargar los clientes.');
            }
        });
    }

    // Función para generar los enlaces de paginación
    function generarPaginacion(totalPaginas, paginaActual) {
        let paginacionHtml = '';

        // Botón "Anterior"
        paginacionHtml += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="${paginaActual - 1}">Anterior</a>
                           </li>`;

        // Números de página
        const maxPaginasVisibles = 5; // Mostrar un máximo de 5 páginas a la vez
        let inicio = Math.max(1, paginaActual - Math.floor(maxPaginasVisibles / 2));
        let fin = Math.min(totalPaginas, inicio + maxPaginasVisibles - 1);

        if (fin - inicio + 1 < maxPaginasVisibles) {
            inicio = Math.max(1, fin - maxPaginasVisibles + 1);
        }

        for (let i = inicio; i <= fin; i++) {
            paginacionHtml += `<li class="page-item ${i === paginaActual ? 'active' : ''}">
                                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                               </li>`;
        }

        // Botón "Siguiente"
        paginacionHtml += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="${paginaActual + 1}">Siguiente</a>
                           </li>`;

        $('#paginacionClientes').html(paginacionHtml);
    }

    // Evento para los enlaces de paginación (delegación de eventos)
    $(document).on('click', '#paginacionClientes .page-link', function(e) {
        e.preventDefault();
        const nuevaPagina = parseInt($(this).data('page'));
        if (nuevaPagina > 0 && nuevaPagina <= totalPaginas && nuevaPagina !== paginaActual) {
            paginaActual = nuevaPagina;
            cargarClientes();
        }
    });

    // Evento para el botón "Editar" (delegación de eventos)
    $(document).on('click', '.btn-editar', function() {
        const idCliente = $(this).data('id');
        
        // Limpiar el formulario y establecer el ID
        $('#formCliente')[0].reset();
        $('#clienteId').val(idCliente);
        $('#clienteModalLabel').text('Editar Cliente'); // Cambiar título del modal
        $('#btnGuardarCliente').text('Actualizar Cliente').removeClass('btn-success').addClass('btn-warning');

        $.ajax({
            url: 'php/clientes.php',
            type: 'GET',
            data: { accion: 'obtener', id: idCliente },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const cliente = response.data;
                    $('#nombre').val(cliente.nombre);
                    $('#apellidoPaterno').val(cliente.apellidoPaterno);
                    $('#apellidoMaterno').val(cliente.apellidoMaterno);
                    $('#telefono').val(cliente.telefono);
                    $('#correo').val(cliente.correo);
                    $('#pais').val(cliente.pais);
                    $('#estado').val(cliente.estado);
                    $('#ciudad').val(cliente.ciudad);
                } else {
                    alert('Error al obtener datos del cliente: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al obtener cliente:', status, error, xhr.responseText);
                alert('Ocurrió un error al obtener los datos del cliente.');
            }
        });
    });

    // Evento para el botón "Eliminar" (delegación de eventos)
    $(document).on('click', '.btn-eliminar', function() {
        const idCliente = $(this).data('id');
        if (confirm('¿Estás seguro de que deseas eliminar este cliente?')) {
            $.ajax({
                url: 'php/clientes.php',
                type: 'POST',
                data: { accion: 'eliminar', id: idCliente },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        cargarClientes(); // Recargar la tabla
                    } else {
                        alert('Error al eliminar: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX al eliminar cliente:', status, error, xhr.responseText);
                    alert('Ocurrió un error al eliminar el cliente.');
                }
            });
        }
    });
});