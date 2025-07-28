let paginaActualClientesBoleto = 1;
const limitePorPaginaClientesBoleto = 10;
let terminoBusquedaClienteBoleto = '';

$(document).ready(function() {
    // --- Inicialización y Carga Inicial ---
    // Carga los clientes que tienen boletos en rifas activas
    cargarClientesParaTablaBoletos();
    
    // Inicializar Select2 para el selector de clientes en el modal de registro
    $('#selectClienteBoleto').select2({
        dropdownParent: $('#registrarBoletoModal'), // Importante para que funcione bien dentro del modal
        placeholder: 'Buscar y seleccionar un cliente',
        allowClear: true,
        language: { // Configuración del idioma español para Select2
            inputTooShort: function(args) {
                var remainingChars = args.minimum - args.input.length;
                var message = 'Por favor, introduce ' + remainingChars + ' o más caracteres';
                return message;
            },
            noResults: function() {
                return 'No se encontraron resultados';
            },
            searching: function() {
                return 'Buscando...';
            }
        },
        ajax: {
            url: 'php/boletos.php', // API para buscar clientes con filtro de nombre
            dataType: 'json',
            delay: 250, // Retraso en milisegundos antes de que la búsqueda se realice
            data: function(params) {
                return {
                    accion: 'listar_clientes_para_select',
                    q: params.term // Término de búsqueda
                };
            },
            processResults: function(data) {
                return {
                    results: data.results // Select2 espera un objeto con una propiedad 'results'
                };
            },
            cache: true
        },
        minimumInputLength: 2 // Mínimo de caracteres para empezar a buscar
    });

    // Cargar rifas activas al abrir el modal de registro de boletos
    $('#registrarBoletoModal').on('show.bs.modal', function () {
        cargarRifasActivas('#selectRifaBoleto');
    });

    // --- Eventos de Búsqueda y Paginación de Clientes en la Tabla Principal ---
    $('#btnBuscarClienteBoleto').on('click', function() {
        terminoBusquedaClienteBoleto = $('#inputBusquedaClienteBoleto').val().trim();
        paginaActualClientesBoleto = 1;
        cargarClientesParaTablaBoletos();
    });

    $('#inputBusquedaClienteBoleto').on('keypress', function(e) {
        if (e.which === 13) { // Capturar Enter key
            $('#btnBuscarClienteBoleto').click();
        }
    });

    $('#btnLimpiarBusquedaClienteBoleto').on('click', function() {
        $('#inputBusquedaClienteBoleto').val('');
        terminoBusquedaClienteBoleto = '';
        paginaActualClientesBoleto = 1;
        cargarClientesParaTablaBoletos();
    });

    $(document).on('click', '#paginacionClientesBoletos .page-link', function(e) {
        e.preventDefault();
        const nuevaPagina = parseInt($(this).data('page'));
        if (!$(this).parent().hasClass('disabled')) {
            paginaActualClientesBoleto = nuevaPagina;
            cargarClientesParaTablaBoletos();
        }
    });

    // --- Eventos de la Sección de Detalle de Boletos por Cliente/Rifa ---
    $(document).on('click', '.btn-ver-boletos', function() {
        const clienteId = $(this).data('id');
        const clienteNombre = $(this).data('nombre');
        
        $('#clienteIdSeleccionado').val(clienteId);
        $('#nombreClienteSeleccionado').text(clienteNombre);
        $('#seccionDetalleBoletos').show(); // Mostrar la sección de detalle

        // Cargar rifas activas para el dropdown de la sección de detalle
        cargarRifasActivas('#selectRifaActiva');
        // Limpiar la tabla de boletos del cliente
        $('#tablaBoletosClienteRifa').empty();
        $('#seleccionarTodosBoletos').prop('checked', false);
        toggleGenerarEnviarPdfsButton(); // Deshabilitar botón de PDF
    });

    $('#selectRifaActiva').on('change', function() {
        const clienteId = $('#clienteIdSeleccionado').val();
        const rifaId = $(this).val();
        if (clienteId && rifaId) {
            cargarBoletosPorClienteYRifa(clienteId, rifaId);
        } else {
            $('#tablaBoletosClienteRifa').empty();
            $('#seleccionarTodosBoletos').prop('checked', false);
            toggleGenerarEnviarPdfsButton();
        }
    });

    // --- Eventos del Formulario de Registro de Boletos ---
    $('#formRegistrarBoleto').on('submit', function(e) {
        e.preventDefault();

        const clienteId = $('#selectClienteBoleto').val();
        const rifaId = $('#selectRifaBoleto').val();
        const cantidadBoletos = $('#cantidadBoletos').val();

        if (!clienteId || !rifaId || cantidadBoletos < 1) {
            alert('Por favor, selecciona un cliente, una rifa y una cantidad válida.');
            return;
        }

        $.ajax({
            url: 'php/boletos.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                accion: 'dar_alta_boletos', 
                clienteId: clienteId, 
                rifaId: rifaId, 
                cantidadBoletos: cantidadBoletos 
            }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#registrarBoletoModal').modal('hide');
                    // Reiniciar el formulario
                    $('#formRegistrarBoleto')[0].reset();
                    $('#selectClienteBoleto').val(null).trigger('change'); // Limpiar Select2
                    $('#selectRifaBoleto').val('');

                    // Opcional: Recargar la sección de boletos si el cliente/rifa está seleccionado
                    const currentClienteId = $('#clienteIdSeleccionado').val();
                    const currentRifaId = $('#selectRifaActiva').val();
                    if (currentClienteId == clienteId && currentRifaId == rifaId) {
                        cargarBoletosPorClienteYRifa(currentClienteId, currentRifaId);
                    }
                    cargarClientesParaTablaBoletos(); // Recargar la tabla principal de clientes
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al registrar boletos:', status, error, xhr.responseText);
                alert('Ocurrió un error al registrar los boletos. Detalles en consola.');
            }
        });
    });

    // --- Eventos para Generar y Enviar PDFs ---
    $('#seleccionarTodosBoletos').on('change', function() {
        $('.checkbox-boleto').prop('checked', $(this).is(':checked'));
        toggleGenerarEnviarPdfsButton();
    });

    $(document).on('change', '.checkbox-boleto', function() {
        // Si algún checkbox individual se desmarca, desmarcar el "seleccionar todos"
        if (!$(this).is(':checked')) {
            $('#seleccionarTodosBoletos').prop('checked', false);
        }
        // Si todos los checkboxes individuales están marcados, marcar "seleccionar todos"
        else if ($('.checkbox-boleto:checked').length === $('.checkbox-boleto').length && $('.checkbox-boleto').length > 0) {
            $('#seleccionarTodosBoletos').prop('checked', true);
        }
        toggleGenerarEnviarPdfsButton();
    });

    function toggleGenerarEnviarPdfsButton() {
        const checkedCount = $('.checkbox-boleto:checked').length;
        $('#btnGenerarEnviarPdfs').prop('disabled', checkedCount === 0);
    }

    $('#btnGenerarEnviarPdfs').on('click', function() {
        const clienteId = $('#clienteIdSeleccionado').val();
        const boletosIds = [];
        $('.checkbox-boleto:checked').each(function() {
            boletosIds.push($(this).data('id'));
        });

        if (!clienteId || boletosIds.length === 0) {
            alert('Por favor, selecciona un cliente y al menos un boleto para enviar.');
            return;
        }

        if (!confirm('¿Estás seguro de que deseas generar y enviar los PDFs de los boletos seleccionados a este cliente?')) {
            return;
        }

        // Deshabilitar botón y mostrar mensaje de carga
        const originalButtonText = $(this).text();
        $(this).prop('disabled', true).text('Generando y Enviando...');

        $.ajax({
            url: 'php/boletos.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                accion: 'generar_y_enviar_pdfs', 
                clienteId: clienteId, 
                boletosIds: boletosIds 
            }),
            dataType: 'json',
            success: function(response) {
                alert(response.message);
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al generar y enviar PDFs:', status, error, xhr.responseText);
                alert('Ocurrió un error al generar o enviar los PDFs. Detalles en consola.');
            },
            complete: function() {
                // Habilitar botón y restaurar texto
                $('#btnGenerarEnviarPdfs').prop('disabled', false).text(originalButtonText);
            }
        });
    });

    // --- Funciones Auxiliares ---

    /**
     * Carga y muestra los clientes que tienen boletos en rifas activas.
     */
    function cargarClientesParaTablaBoletos() {
        $.ajax({
            url: 'php/boletos.php', // Ahora apunta a boletos.php para la nueva acción
            type: 'GET',
            data: { 
                accion: 'listar_clientes_con_boletos_activos', // <--- Nueva acción
                pagina: paginaActualClientesBoleto, 
                limite: limitePorPaginaClientesBoleto,
                busqueda: terminoBusquedaClienteBoleto 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(function(cliente) {
                            html += `
                                <tr>
                                    <td>${cliente.nombre} ${cliente.apellidoPaterno} ${cliente.apellidoMaterno || ''}</td>
                                    <td>${cliente.correo}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-ver-boletos" 
                                                data-id="${cliente.id}" 
                                                data-nombre="${cliente.nombre} ${cliente.apellidoPaterno} ${cliente.apellidoMaterno || ''}">Ver Boletos</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html = `<tr><td colspan="3" class="text-center">No se encontraron clientes con boletos en rifas activas.</td></tr>`;
                    }
                    $('#tablaClientesBoletos').html(html);
                    generarPaginacionBoletos(response.totalPaginas, response.paginaActual);
                } else {
                    alert('Error al cargar clientes para boletos: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cargar clientes para boletos:', status, error, xhr.responseText);
                alert('Ocurrió un error al cargar los clientes para boletos. Detalles en consola.');
            }
        });
    }

    /**
     * Genera los controles de paginación para la tabla de clientes.
     */
    function generarPaginacionBoletos(totalPaginas, paginaActual) {
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

        $('#paginacionClientesBoletos').html(paginacionHtml);
    }

    /**
     * Carga las opciones de rifas activas en un select dado.
     */
    function cargarRifasActivas(selectId) {
        $.ajax({
            url: 'php/boletos.php',
            type: 'GET',
            data: { accion: 'obtener_rifas_activas' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let optionsHtml = '<option value="">Selecciona una rifa</option>';
                    response.data.forEach(function(rifa) {
                        optionsHtml += `<option value="${rifa.id}">${rifa.text}</option>`;
                    });
                    $(selectId).html(optionsHtml);
                } else {
                    alert('Error al cargar rifas activas: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cargar rifas activas:', status, error, xhr.responseText);
                alert('Ocurrió un error al cargar las rifas activas. Detalles en consola.');
            }
        });
    }

    /**
     * Carga y muestra los boletos de un cliente específico para una rifa determinada.
     */
    function cargarBoletosPorClienteYRifa(clienteId, rifaId) {
        $.ajax({
            url: 'php/boletos.php',
            type: 'GET',
            data: { 
                accion: 'listar_boletos_por_cliente_rifa', 
                clienteId: clienteId, 
                rifaId: rifaId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(function(boleto) {
                            const fechaBoleto = new Date(boleto.fecha).toLocaleString('es-MX', { 
                                year: 'numeric', month: '2-digit', day: '2-digit', 
                                hour: '2-digit', minute: '2-digit' 
                            });
                            html += `
                                <tr>
                                    <td><input type="checkbox" class="checkbox-boleto" data-id="${boleto.id}"></td>
                                    <td>${boleto.id}</td>
                                    <td>${fechaBoleto}</td>
                                    <td>${boleto.rifa_nombre}</td>
                                    <td>$${parseFloat(boleto.rifa_precio).toFixed(2)}</td>
                                </tr>
                            `;
                        });
                    } else {
                        html = `<tr><td colspan="5" class="text-center">No se encontraron boletos para esta rifa.</td></tr>`;
                    }
                    $('#tablaBoletosClienteRifa').html(html);
                    $('#seleccionarTodosBoletos').prop('checked', false); // Desmarcar "seleccionar todos" al cargar
                    toggleGenerarEnviarPdfsButton(); // Actualizar estado del botón de PDF
                } else {
                    alert('Error al cargar boletos: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX al cargar boletos por cliente y rifa:', status, error, xhr.responseText);
                alert('Ocurrió un error al cargar los boletos. Detalles en consola.');
            }
        });
    }
});