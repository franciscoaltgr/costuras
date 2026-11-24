<?php
/**
 * Módulo: Crear Nueva Cotización - Grill Guard
 * Archivo: nueva_cotizacion.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$error = null;

// Cargar clientes existentes para autocompletar
try {
    $stmtClientes = $pdo->query("SELECT id, nombre, telefono, email FROM clientes ORDER BY nombre ASC");
    $clientesExistentes = $stmtClientes->fetchAll();

    // Sugerir siguiente número de cotización
    $stmtUltimo = $pdo->query("SELECT numero FROM cotizaciones ORDER BY id DESC LIMIT 1");
    $ultimoNum = $stmtUltimo->fetchColumn();
    if ($ultimoNum && is_numeric($ultimoNum)) {
        $siguienteNumero = (string)((int)$ultimoNum + 1);
    } else {
        $siguienteNumero = '21127';
    }
} catch (PDOException $e) {
    die("Error al cargar datos base: " . e($e->getMessage()));
}

// Procesar Formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero           = trim($_POST['numero'] ?? $siguienteNumero);
    $tipoCliente      = trim($_POST['tipo_cliente'] ?? 'nuevo');
    $clienteExistente = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
    $clienteNombre    = trim($_POST['cliente_nombre'] ?? '');
    $clienteDireccion = trim($_POST['cliente_direccion'] ?? '');
    $clienteTelefono  = trim($_POST['cliente_telefono'] ?? '');
    $clienteEmail     = trim($_POST['cliente_email'] ?? '');
    $fecha            = trim($_POST['fecha'] ?? date('Y-m-d'));
    // Toggle "Afecto a IVA": si se desactiva, la cotización se emite SIN IVA (venta exenta).
    // Se usa iva = 0 como señal de exento al almacenar. El form envía valor '1' solo si está activo.
    $afectoIva        = isset($_POST['afecto_iva']) && $_POST['afecto_iva'] === '1';
    // Abono: ahora es un monto LIBRE en CLP que el usuario ingresa (no un porcentaje fijo).
    // El porcentaje se recalcula a partir del monto para fines de reporte y del PDF.
    $abonoRaw         = str_replace(['.', ','], ['', '.'], trim($_POST['abono'] ?? '0'));
    $abonoManual      = (int)round((float)$abonoRaw);
    if ($abonoManual < 0) { $abonoManual = 0; }
    $condiciones      = trim($_POST['condiciones'] ?? 'TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA');
    $estado           = trim($_POST['estado'] ?? 'Enviada');

    // Ítems
    $productos = $_POST['item_producto'] ?? [];
    $colores   = $_POST['item_color'] ?? [];
    $cantidades= $_POST['item_cantidad'] ?? [];
    $unitarios = $_POST['item_unitario'] ?? [];

    // Validaciones
    if (empty($numero)) {
        $error = "Debe especificar un número de cotización.";
    } elseif ($tipoCliente === 'existente' && empty($clienteExistente)) {
        $error = "Debe seleccionar un cliente de la lista existente.";
    } elseif ($tipoCliente === 'nuevo' && empty($clienteNombre)) {
        $error = "Debe indicar el nombre del cliente.";
    } elseif (empty($productos) || !is_array($productos)) {
        $error = "Debe incluir al menos un producto o confección en la cotización.";
    }

    // Si seleccionó cliente existente, cargar sus datos
    $clienteIdFinal = null;
    if (!$error && $tipoCliente === 'existente') {
        foreach ($clientesExistentes as $cl) {
            if ($cl['id'] == $clienteExistente) {
                $clienteIdFinal  = $cl['id'];
                $clienteNombre   = $cl['nombre'];
                $clienteTelefono = $cl['telefono'];
                $clienteEmail    = $cl['email'];
                break;
            }
        }
    }

    // Validar y calcular ítems
    $itemsProcesados = [];
    $subtotalCalculado = 0;

    if (!$error) {
        for ($i = 0; $i < count($productos); $i++) {
            $prod = trim($productos[$i] ?? '');
            if (empty($prod)) continue;

            $col  = trim($colores[$i] ?? '');
            $cant = (int)str_replace(['.', ','], '', trim($cantidades[$i] ?? '1'));
            $cant = max(1, $cant);

            $unit = (float)str_replace(['.', ','], ['', '.'], trim($unitarios[$i] ?? '0'));
            $unit = max(0, $unit);

            $linea = (int)round($cant * $unit);
            $subtotalCalculado += $linea;

            $itemsProcesados[] = [
                'producto'        => $prod,
                'color'           => $col,
                'cantidad'        => $cant,
                'precio_unitario' => (int)round($unit),
                'total_linea'     => $linea
            ];
        }

        if (empty($itemsProcesados)) {
            $error = "Debe ingresar al menos una prenda o producto válido con su descripción.";
        }
    }

    if (!$error) {
        // Si la cotización es EXENTA (sin boleta/factura), IVA = 0 y total = subtotal
        $ivaCalculado = $afectoIva ? (int)round($subtotalCalculado * 0.19) : 0;
        $totalGeneral = $subtotalCalculado + $ivaCalculado;

        // Validar que el abono manual no supere el total de la cotización
        if ($abonoManual > $totalGeneral) {
            $error = "El abono ingresado (" . formatMoney($abonoManual) . ") no puede ser mayor al total de la cotización (" . formatMoney($totalGeneral) . ").";
        }
    }

    if (!$error) {
        $abonoCalculado  = $abonoManual;
        $saldoCalculado  = $totalGeneral - $abonoCalculado;
        // Porcentaje derivado del monto real (0-100) para reportes y PDF
        $porcentajeAbono = ($totalGeneral > 0) ? (int)round(($abonoCalculado / $totalGeneral) * 100) : 0;

        try {
            $pdo->beginTransaction();

            // Insertar cotización
            $stmtInsertCot = $pdo->prepare("
                INSERT INTO cotizaciones (
                    numero, cliente_id, cliente_nombre, cliente_direccion, cliente_telefono, 
                    cliente_email, fecha, subtotal, iva, total, abono, saldo, 
                    porcentaje_abono, condiciones, estado
                ) VALUES (
                    :numero, :cliente_id, :cliente_nombre, :cliente_direccion, :cliente_telefono, 
                    :cliente_email, :fecha, :subtotal, :iva, :total, :abono, :saldo, 
                    :porcentaje_abono, :condiciones, :estado
                )
            ");

            $stmtInsertCot->execute([
                ':numero'           => $numero,
                ':cliente_id'       => $clienteIdFinal,
                ':cliente_nombre'   => $clienteNombre,
                ':cliente_direccion'=> $clienteDireccion,
                ':cliente_telefono' => $clienteTelefono,
                ':cliente_email'    => $clienteEmail,
                ':fecha'            => $fecha,
                ':subtotal'         => $subtotalCalculado,
                ':iva'              => $ivaCalculado,
                ':total'            => $totalGeneral,
                ':abono'            => $abonoCalculado,
                ':saldo'            => $saldoCalculado,
                ':porcentaje_abono' => $porcentajeAbono,
                ':condiciones'      => $condiciones,
                ':estado'           => $estado
            ]);

            $nuevaCotId = (int)$pdo->lastInsertId();

            // Insertar ítems
            $stmtInsertItem = $pdo->prepare("
                INSERT INTO cotizacion_items (cotizacion_id, producto, color, cantidad, precio_unitario, total_linea)
                VALUES (:cotizacion_id, :producto, :color, :cantidad, :precio_unitario, :total_linea)
            ");

            foreach ($itemsProcesados as $item) {
                $stmtInsertItem->execute([
                    ':cotizacion_id'   => $nuevaCotId,
                    ':producto'        => $item['producto'],
                    ':color'           => $item['color'],
                    ':cantidad'        => $item['cantidad'],
                    ':precio_unitario' => $item['precio_unitario'],
                    ':total_linea'     => $item['total_linea']
                ]);
            }

            $pdo->commit();

            setFlash('success', "¡Cotización #{$numero} creada exitosamente! Puede descargar el PDF o imprimirla.");
            header("Location: ver_cotizacion.php?id={$nuevaCotId}");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error al guardar la cotización: " . $e->getMessage();
        }
    }
}

$pageTitle = "Nueva Cotización";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Nueva Cotización Grill Guard</h2>
                <p class="text-muted mb-0">Complete los datos para generar el presupuesto oficial imprimible y en PDF.</p>
            </div>
            <a href="cotizaciones.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancelar
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="nueva_cotizacion.php" id="formCotizacion" class="card shadow-sm border-0 p-4">
            
            <!-- SECCIÓN 1: Identificación de Cotización y Taller -->
            <div class="row g-3 mb-4 pb-3 border-bottom">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Número de Cotización <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold">COT-</span>
                        <input type="text" name="numero" class="form-control fw-bold" required value="<?= e($_POST['numero'] ?? $siguienteNumero); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Fecha de Emisión <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= e($_POST['fecha'] ?? date('Y-m-d')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Estado Inicial</label>
                    <select name="estado" class="form-select">
                        <option value="Enviada" selected>Enviada (A cliente)</option>
                        <option value="Borrador">Borrador</option>
                        <option value="Aprobada">Aprobada</option>
                    </select>
                </div>
            </div>

            <!-- SECCIÓN 2: Datos del Cliente -->
            <div class="mb-4 pb-3 border-bottom">
                <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-person-bounding-box text-primary me-2"></i>Datos del Cliente</h5>
                
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoNuevo" value="nuevo" checked onchange="toggleClienteTipo()">
                        <label class="form-check-label fw-semibold" for="tipoNuevo">Nuevo Cliente / Particular</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoExistente" value="existente" onchange="toggleClienteTipo()">
                        <label class="form-check-label fw-semibold" for="tipoExistente">Seleccionar Cliente Existente</label>
                    </div>
                </div>

                <!-- Selector si es existente -->
                <div id="boxClienteExistente" class="mb-3" style="display: none;">
                    <label class="form-label fw-bold">Buscar Cliente Registrado:</label>
                    <select name="cliente_id" id="selClienteExistente" class="form-select" onchange="completarDatosCliente()">
                        <option value="">-- Seleccionar de la lista --</option>
                        <?php foreach ($clientesExistentes as $cli): ?>
                            <option value="<?= (int)$cli['id']; ?>" 
                                    data-nombre="<?= e($cli['nombre']); ?>"
                                    data-telefono="<?= e($cli['telefono']); ?>"
                                    data-email="<?= e($cli['email']); ?>">
                                <?= e($cli['nombre']); ?> (<?= e($cli['telefono'] ?: 'Sin fono'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nombre o Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="cliente_nombre" id="cli_nombre" class="form-control" placeholder="Ej: FACTOR PRINT" required value="<?= e($_POST['cliente_nombre'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Teléfono de Contacto</label>
                        <input type="text" name="cliente_telefono" id="cli_telefono" class="form-control" placeholder="Ej: 56920838625" value="<?= e($_POST['cliente_telefono'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Dirección</label>
                        <input type="text" name="cliente_direccion" id="cli_direccion" class="form-control" placeholder="Ej: Rosas 1339 Santiago Centro" value="<?= e($_POST['cliente_direccion'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Correo Electrónico</label>
                        <input type="email" name="cliente_email" id="cli_email" class="form-control" placeholder="Ej: contacto@factorprint.cl" value="<?= e($_POST['cliente_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: Desglose de Productos / Prendas Cotizadas -->
            <div class="mb-4 pb-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-tag text-primary me-2"></i>Detalle de Confección / Ítems
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" onclick="agregarFilaItem()">
                        <i class="bi bi-plus-lg me-1"></i> Añadir Ítem
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="tablaItems">
                        <thead class="table-light">
                            <tr class="text-center small">
                                <th style="width: 35%;">Descripción / Prenda <span class="text-danger">*</span></th>
                                <th style="width: 18%;">Color</th>
                                <th style="width: 14%;">Cantidad</th>
                                <th style="width: 18%;">Precio Unitario (CLP)</th>
                                <th style="width: 15%;">Total Línea</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItems">
                            <tr>
                                <td>
                                    <input type="text" name="item_producto[]" class="form-control form-control-sm" placeholder="Ej: BOLSAS 38X43 o Pechera Cuero" required>
                                </td>
                                <td>
                                    <input type="text" name="item_color[]" class="form-control form-control-sm text-center" placeholder="Ej: NEGRA">
                                </td>
                                <td>
                                    <input type="number" name="item_cantidad[]" class="form-control form-control-sm text-center item-cant" value="1000" min="1" required oninput="calcularTotales()">
                                </td>
                                <td>
                                    <input type="number" name="item_unitario[]" class="form-control form-control-sm text-end item-unit" value="890" min="0" required oninput="calcularTotales()">
                                </td>
                                <td class="text-end fw-bold item-linea-total bg-light">
                                    $ 890.000
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaItem(this)" title="Quitar fila">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 4: Totales Financieros y Abono (editable) -->
            <?php $afectoIvaDefault = !isset($_POST['afecto_iva']) || $_POST['afecto_iva'] === '1'; ?>
            <div class="row justify-content-end mb-4">
                <div class="col-md-7 col-lg-6">
                    <div class="card bg-light border-0 p-3">

                        <!-- Toggle: afecto a IVA (19%) -->
                        <div class="form-check form-switch p-3 mb-3 rounded border bg-white" style="padding-left: 3.5rem !important;">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="toggleIva"
                                   name="afecto_iva"
                                   value="1"
                                   <?= $afectoIvaDefault ? 'checked' : ''; ?>
                                   onchange="calcularTotales()">
                            <label class="form-check-label fw-bold" for="toggleIva">
                                <i class="bi bi-receipt me-1"></i>
                                Afecto a IVA (19%)
                                <span id="lblEstadoIva" class="badge ms-2 <?= $afectoIvaDefault ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                    <?= $afectoIvaDefault ? 'con boleta / factura' : 'exento — sin boleta'; ?>
                                </span>
                            </label>
                            <small class="d-block text-muted mt-1">
                                Desactívalo si la clienta no desea boleta ni factura (venta exenta de IVA).
                            </small>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold">TOTAL NETO:</span>
                            <span class="fw-bold" id="lblSubtotal">$ 890.000</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2" id="rowIva">
                            <span class="text-muted fw-bold" id="lblIvaLabel">IVA (19%):</span>
                            <span class="fw-bold" id="lblIva">$ 169.100</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                            <span class="fw-bold fs-6 text-dark" id="lblTotalLabel">TOTAL GENERAL (c/IVA):</span>
                            <span class="fw-bold fs-6 text-primary" id="lblTotal">$ 1.059.100</span>
                        </div>

                        <!-- Abono recibido: monto libre en CLP con chips de preselección -->
                        <label class="form-label fw-bold text-success mb-1">
                            <i class="bi bi-cash-coin me-1"></i> Abono inicial recibido
                        </label>
                        <div class="d-flex gap-1 flex-wrap mb-2" role="group" aria-label="Selección rápida de abono">
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="0">Sin abono</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="30">30%</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="50">50%</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="100">Pagó completo</button>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text">$</span>
                            <input type="number"
                                   name="abono"
                                   id="inpAbono"
                                   class="form-control fw-bold"
                                   min="0"
                                   step="1"
                                   value="<?= e($_POST['abono'] ?? 0); ?>"
                                   placeholder="0"
                                   oninput="recalcularAbono()">
                            <span class="input-group-text" id="lblPctAbono" style="min-width: 70px; justify-content: center;">0%</span>
                        </div>
                        <small class="text-muted d-block mb-3">
                            Ingresa el monto exacto que el cliente abonó al confirmar. Puede ser 0 si aún no ha pagado.
                        </small>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold text-danger">SALDO PENDIENTE:</span>
                            <span class="fw-bold text-danger fs-6" id="lblSaldo">$ 1.059.100</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 5: Condiciones de Taller -->
            <div class="mb-4">
                <label class="form-label fw-bold">Condiciones de Taller y Pago</label>
                <input type="text" name="condiciones" class="form-control" value="TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA">
                <small class="text-muted">Aparecerá destacado al pie de la cotización impresa y en el PDF.</small>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="cotizaciones.php" class="btn btn-outline-secondary px-4">Cancelar</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> Guardar y Generar Cotización
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleClienteTipo() {
    const esExistente = document.getElementById('tipoExistente').checked;
    const box = document.getElementById('boxClienteExistente');
    box.style.display = esExistente ? 'block' : 'none';
    if (!esExistente) {
        document.getElementById('selClienteExistente').value = '';
    }
}

function completarDatosCliente() {
    const sel = document.getElementById('selClienteExistente');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('cli_nombre').value = opt.getAttribute('data-nombre') || '';
        document.getElementById('cli_telefono').value = opt.getAttribute('data-telefono') || '';
        document.getElementById('cli_email').value = opt.getAttribute('data-email') || '';
    }
}

function formatCLP(val) {
    return '$ ' + Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Estado único de total vigente, usado tanto por las líneas como por el bloque de abono.
let _totalActual = 0;

function calcularTotales() {
    const tbody = document.getElementById('tbodyItems');
    const rows = tbody.querySelectorAll('tr');
    let subtotal = 0;

    rows.forEach(row => {
        const cantInput = row.querySelector('.item-cant');
        const unitInput = row.querySelector('.item-unit');
        const totalCell = row.querySelector('.item-linea-total');

        if (cantInput && unitInput && totalCell) {
            const c = parseFloat(cantInput.value) || 0;
            const u = parseFloat(unitInput.value) || 0;
            const linea = Math.round(c * u);
            subtotal += linea;
            totalCell.textContent = formatCLP(linea);
        }
    });

    // Respetar el toggle "Afecto a IVA" (si está desactivado → venta exenta → IVA = 0)
    const toggleIva = document.getElementById('toggleIva');
    const afectoIva = toggleIva ? toggleIva.checked : true;
    const iva = afectoIva ? Math.round(subtotal * 0.19) : 0;
    const total = subtotal + iva;
    _totalActual = total;

    document.getElementById('lblSubtotal').textContent = formatCLP(subtotal);
    document.getElementById('lblIva').textContent = afectoIva ? formatCLP(iva) : 'Exento';
    document.getElementById('lblTotal').textContent = formatCLP(total);

    // Feedback visual del estado del IVA
    const lblIvaLabel   = document.getElementById('lblIvaLabel');
    const lblTotalLabel = document.getElementById('lblTotalLabel');
    const rowIva        = document.getElementById('rowIva');
    const lblEstadoIva  = document.getElementById('lblEstadoIva');
    if (lblIvaLabel && lblTotalLabel && rowIva && lblEstadoIva) {
        if (afectoIva) {
            lblIvaLabel.textContent = 'IVA (19%):';
            lblTotalLabel.textContent = 'TOTAL GENERAL (c/IVA):';
            rowIva.classList.remove('text-muted', 'opacity-50');
            lblEstadoIva.textContent = 'con boleta / factura';
            lblEstadoIva.className = 'badge ms-2 bg-success-subtle text-success';
        } else {
            lblIvaLabel.textContent = 'IVA:';
            lblTotalLabel.textContent = 'TOTAL GENERAL (exento):';
            rowIva.classList.add('opacity-50');
            lblEstadoIva.textContent = 'exento — sin boleta';
            lblEstadoIva.className = 'badge ms-2 bg-warning-subtle text-warning';
        }
    }

    // Si el abono supera el nuevo total (porque bajaron ítems), se re-acota
    const inp = document.getElementById('inpAbono');
    if (inp) {
        inp.max = total;
        if (parseFloat(inp.value || 0) > total) {
            inp.value = total;
        }
    }
    recalcularAbono();
}

function recalcularAbono() {
    const inp = document.getElementById('inpAbono');
    let abono = Math.max(0, Math.round(parseFloat(inp.value) || 0));
    if (abono > _totalActual) {
        abono = _totalActual;
        inp.value = abono;
    }
    const saldo = _totalActual - abono;
    const pct = _totalActual > 0 ? Math.round((abono / _totalActual) * 100) : 0;

    document.getElementById('lblSaldo').textContent = formatCLP(saldo);
    document.getElementById('lblPctAbono').textContent = pct + '%';
}

function aplicarAbonoPorcentaje(pct) {
    const monto = Math.round(_totalActual * (pct / 100));
    document.getElementById('inpAbono').value = monto;
    recalcularAbono();
}

function agregarFilaItem() {
    const tbody = document.getElementById('tbodyItems');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text" name="item_producto[]" class="form-control form-control-sm" placeholder="Descripción de prenda o trabajo" required>
        </td>
        <td>
            <input type="text" name="item_color[]" class="form-control form-control-sm text-center" placeholder="Color">
        </td>
        <td>
            <input type="number" name="item_cantidad[]" class="form-control form-control-sm text-center item-cant" value="1" min="1" required oninput="calcularTotales()">
        </td>
        <td>
            <input type="number" name="item_unitario[]" class="form-control form-control-sm text-end item-unit" value="0" min="0" required oninput="calcularTotales()">
        </td>
        <td class="text-end fw-bold item-linea-total bg-light">
            $ 0
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaItem(this)">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    calcularTotales();
}

function eliminarFilaItem(btn) {
    const tbody = document.getElementById('tbodyItems');
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
        calcularTotales();
    } else {
        alert('La cotización debe contener al menos un ítem.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Conectar los chips de preselección (0% / 30% / 50% / 100%)
    document.querySelectorAll('.quick-abono').forEach(btn => {
        btn.addEventListener('click', function() {
            const pct = parseInt(this.dataset.pct, 10) || 0;
            aplicarAbonoPorcentaje(pct);
        });
    });
    calcularTotales();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
