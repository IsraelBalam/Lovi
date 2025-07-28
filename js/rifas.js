let paginaActualRifa = 1;
const limitePorPaginaRifa = 10;
let terminoBusquedaRifa = '';

$(document).ready(function() {
    // Cargar rifas al iniciar la página
    cargarRifas();

    // Evento para el botón "Buscar"
    $('#btnBuscarRifa').on('click', function() {
        terminoBusquedaRifa = $('#inputBusquedaRifa').val().trim();
        paginaActualRifa = 1; // Reiniciar a la primera página en cada nueva búsqueda
        cargarRifas();
    });

    // Evento para limpiar búsqueda
    $('#btnLimpiarBusquedaRifa').on('click', function() {
        $('#inputBusquedaRifa').val('');
        terminoBusquedaRifa = '';
        paginaActualRifa = 1;
        cargarRifas();
    });

    // Evento para el botón "Nueva Rifa"
    $('#btnNuevaRifa').on('click', function() {
        $('#formRifa')[0].reset(); // Limpiar el formulario
        $('#rifaId').val(''); // Asegurar que el ID esté vacío
        $('#rifaModalLabel').text('Registrar Rifa'); // Cambiar título
        $('#btnGuardarRifa').text('Guardar Rifa').removeClass('btn-warning').addClass('btn-success');
        $('#activaRifa').prop('checked', false); // Asegurarse de que el switch esté en off por defecto
    });

    // Manejar el envío del formulario de rifa (Agregar/Editar)
    $('#formRifa').on('submit', function(e) {
        e.preventDefault();

        const rifaId = $('#rifaId').val();
        const accion = rifaId ? 'editar' : 'agregar';

        const formData = {
            id: rifaId,
            nombre: $('#nombreRifa').val(),
            descripcion: $('#descripcionRifa').val(),
            // Convertir el precio a un número flotante
            precio: parseFloat($('#precioRifa').val()), 
            // Formatear la fecha para que MySQL la entienda
            fecha: $('#fechaRifa').val(), 
            // Convertir booleano del switch a 0 o 1
            activa: $('#activaRifa').is(':checked') ? 1 : 0 
        };

        $.ajax({
            url: 'php/rifas.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ accion: accion, ...formData }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#rifaModal').modal('hide');
                    cargarRifas(); // Recargar la tabla
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

    // Función para cargar rifas en la tabla
    function cargarRifas() {
        $.ajax({
            url: 'php/rifas.php',
            type: 'GET',
            data: { 
                accion: 'listar', 
                pagina: paginaActualRifa, 
                limite: limitePorPaginaRifa,
                busqueda: terminoBusquedaRifa 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(function(rifa) {
                            // Formatear la fecha para visualización
                            const fechaFormateada = new Date(rifa.fecha).toLocaleString('es-MX', { 
                                year: 'numeric', month: '2-digit', day: '2-digit', 
                                hour: '2-digit', minute: '2-digit', second: '2-digit' 
                            });

                            // Mostrar el switch en la tabla (solo visual, no interactivo)
                            const switchVisual = rifa.activa == 1 ? 
                                `<div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input switch-rifa-activa" type="checkbox" role="switch" checked data-id="${rifa.id}">
                                </div>` :
                                `<div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input switch-rifa-activa" type="checkbox" role="switch" data-id="${rifa.id}">
                                </div>`;

                            html += `
                                <tr>
                                    <td>${rifa.nombre}</td>
                                    <td>${rifa.descripcion || ''}</td>
                                    <td>$${parseFloat(rifa.precio).toFixed(2)}</td>
                                    <td>${fechaFormateada}</td>
                                    <td>${switchVisual}</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning btn-editar-rifa" data-id="${rifa.id}" data-bs-toggle="modal" data-bs-target="#rifaModal">Editar</button>
                                        <button class="btn btn-sm btn-danger btn-eliminar-rifa" data-id="${rifa.id}">Eliminar</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html = `<tr><td colspan="6" class="text-center">No se encontraron rifas.</td></tr>`;
                    }
                    $('#tablaRifas').html(html);

                    // Generar paginación
                    generarPaginacionRifas(response.totalPaginas, response.paginaActual);

                } else {
                    alert('Error al cargar rifas: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cargar rifas:', status, error, xhr.responseText);
                alert('Ocurrió un error al cargar las rifas.');
            }
        });
    }

    // Función para generar los enlaces de paginación para rifas
    function generarPaginacionRifas(totalPaginas, paginaActual) {
        let paginacionHtml = '';
        paginacionHtml += `<li class="page-item ${paginaActual === 1 ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="${paginaActual - 1}">Anterior</a>
                           </li>`;

        const maxPaginasVisibles = 5;
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

        paginacionHtml += `<li class="page-item ${paginaActual === totalPaginas ? 'disabled' : ''}">
                                <a class="page-link" href="#" data-page="${paginaActual + 1}">Siguiente</a>
                           </li>`;

        $('#paginacionRifas').html(paginacionHtml);
    }

    // Evento para los enlaces de paginación de rifas (delegación de eventos)
    $(document).on('click', '#paginacionRifas .page-link', function(e) {
        e.preventDefault();
        const nuevaPagina = parseInt($(this).data('page'));
        // Necesitamos 'totalPaginas' aquí, que viene en la respuesta de cargarRifas
        // Una opción es hacerla global o recuperar de un elemento oculto.
        // Por simplicidad, asumiré que 'totalPaginas' es accesible o se recalcula.
        // En una app más grande, se guardaría en una variable global o un objeto de estado.
        // Para este ejemplo, haremos una llamada para obtener el total de páginas si no está disponible,
        // o si es un problema, se puede guardar en un data attribute de la #paginacionRifas.
        
        // Asumiendo que response.totalPaginas de la última carga es accesible:
        // Si tienes la respuesta de la última carga de clientes disponible, puedes usarla.
        // Para este ejemplo, re-cargaremos si la página es válida.
        
        // Simplemente validamos que no sea una página 'disabled'
        if (!$(this).parent().hasClass('disabled')) {
             // Si el href="#" no está en un li.disabled, entonces el click es válido
            if (nuevaPagina > 0) { // Check para evitar páginas negativas
                paginaActualRifa = nuevaPagina;
                cargarRifas();
            }
        }
    });

    // Evento para el botón "Editar" rifa (delegación de eventos)
    $(document).on('click', '.btn-editar-rifa', function() {
        const idRifa = $(this).data('id');
        
        $('#formRifa')[0].reset();
        $('#rifaId').val(idRifa);
        $('#rifaModalLabel').text('Editar Rifa');
        $('#btnGuardarRifa').text('Actualizar Rifa').removeClass('btn-success').addClass('btn-warning');

        $.ajax({
            url: 'php/rifas.php',
            type: 'GET',
            data: { accion: 'obtener', id: idRifa },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const rifa = response.data;
                    $('#nombreRifa').val(rifa.nombre);
                    $('#descripcionRifa').val(rifa.descripcion);
                    $('#precioRifa').val(parseFloat(rifa.precio).toFixed(2)); // Mostrar con 2 decimales
                    
                    // Formatear la fecha para el input datetime-local
                    const fecha = new Date(rifa.fecha);
                    const pad = (num) => String(num).padStart(2, '0');
                    const formattedDate = `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}:${pad(fecha.getSeconds())}`;
                    $('#fechaRifa').val(formattedDate);

                    $('#activaRifa').prop('checked', rifa.activa == 1); // Setear el switch
                } else {
                    alert('Error al obtener datos de la rifa: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al obtener rifa:', status, error, xhr.responseText);
                alert('Ocurrió un error al obtener los datos de la rifa.');
            }
        });
    });

    // Evento para el botón "Eliminar" rifa (delegación de eventos)
    $(document).on('click', '.btn-eliminar-rifa', function() {
        const idRifa = $(this).data('id');
        if (confirm('¿Estás seguro de que deseas eliminar esta rifa?')) {
            $.ajax({
                url: 'php/rifas.php',
                type: 'POST',
                data: { accion: 'eliminar', id: idRifa },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        cargarRifas(); // Recargar la tabla
                    } else {
                        alert('Error al eliminar: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX al eliminar rifa:', status, error, xhr.responseText);
                    alert('Ocurrió un error al eliminar la rifa.');
                }
            });
        }
    });

    // Nuevo: Evento para el cambio del switch de Activa/Inactiva directamente en la tabla
    $(document).on('change', '.switch-rifa-activa', function() {
        const idRifa = $(this).data('id');
        const estadoActiva = $(this).is(':checked') ? 1 : 0;

        $.ajax({
            url: 'php/rifas.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                accion: 'cambiarEstadoActiva', 
                id: idRifa, 
                activa: estadoActiva 
            }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // No es necesario recargar toda la tabla, el cambio visual ya se hizo
                    // alert(response.message); // Opcional: mostrar un mensaje breve
                } else {
                    alert('Error al cambiar estado: ' + response.message);
                    // Revertir el switch visualmente si hubo un error
                    $(this).prop('checked', !$(this).is(':checked')); 
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cambiar estado:', status, error, xhr.responseText);
                alert('Ocurrió un error al cambiar el estado de la rifa.');
                // Revertir el switch visualmente si hubo un error de red/servidor
                $(this).prop('checked', !$(this).is(':checked'));
            }
        });
    });

});