<?php
/**
 * Módulo: Crear Pedido con Transacción Atómica
 * Archivo: crear_pedido.php
 * Moneda: Pesos Chilenos (CLP)
 * Codificación: UTF-8
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$error = null;

// Cargar clientes existentes para el selector
try {
    $stmtClientes = $pdo->query("SELECT id, nombre, telefono FROM clientes ORDER BY nombre ASC");
    $clientesExistentes = $stmtClientes->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar lista de clientes: " . e($e->getMessage()));
}

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recibir y sanitizar datos del cliente
    $tipoCliente         = trim($_POST['tipo_cliente'] ?? 'existente');
    $clienteExistenteId  = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
    $nuevoClienteNombre   = trim($_POST['nuevo_nombre'] ?? '');
    $nuevoClienteTelefono = trim($_POST['nuevo_telefono'] ?? '');
    $nuevoClienteEmail    = trim($_POST['nuevo_email'] ?? '');

    // 2. Recibir y sanitizar datos del pedido
    $descripcionPrenda   = trim($_POST['descripcion_prenda'] ?? '');
    $estado              = trim($_POST['estado'] ?? 'Pendiente');
    $fechaEntrega        = trim($_POST['fecha_entrega'] ?? '');
    $costoTotalInput     = str_replace(['.', ','], ['', '.'], trim($_POST['costo_total'] ?? '0'));
    $abonoInput          = str_replace(['.', ','], ['', '.'], trim($_POST['abono'] ?? '0'));

    // 3. Recibir y sanitizar medidas corporales (en cm)
    $bustoInput          = str_replace(',', '.', trim($_POST['busto'] ?? ''));
    $cinturaInput        = str_replace(',', '.', trim($_POST['cintura'] ?? ''));
    $caderaInput         = str_replace(',', '.', trim($_POST['cadera'] ?? ''));
    $largoInput          = str_replace(',', '.', trim($_POST['largo'] ?? ''));
    $observaciones       = trim($_POST['observaciones'] ?? '');

    // 4. Validaciones de negocio
    $estadosValidos = ['Pendiente', 'En Proceso', 'Prueba Lista', 'Entregado', 'Cancelado'];

    if (!in_array($estado, $estadosValidos, true)) {
        $error = "El estado seleccionado no es válido.";
    } elseif ($tipoCliente === 'existente' && empty($clienteExistenteId)) {
        $error = "Debe seleccionar un cliente de la lista o elegir 'Registrar Nuevo Cliente'.";
    } elseif ($tipoCliente === 'nuevo' && empty($nuevoClienteNombre)) {
        $error = "Debe especificar el nombre del nuevo cliente.";
    } elseif (empty($descripcionPrenda)) {
        $error = "Debe ingresar una descripción de la prenda o trabajo.";
    } elseif (empty($fechaEntrega) || !strtotime($fechaEntrega)) {
        $error = "Debe especificar una fecha de entrega válida.";
    } elseif (!is_numeric($costoTotalInput) || (float)$costoTotalInput <= 0) {
        $error = "El costo total debe ser un monto numérico mayor a cero.";
    } elseif (!is_numeric($abonoInput) || (float)$abonoInput < 0) {
        $error = "El abono inicial debe ser un número mayor o igual a cero.";
    } elseif ((float)$abonoInput > (float)$costoTotalInput) {
        $error = "El abono inicial (" . formatMoney($abonoInput) . ") no puede exceder el costo total (" . formatMoney($costoTotalInput) . ").";
    }

    // Si las validaciones pasaron, ejecutamos la transacción atómica
    if (!$error) {
        // En pesos chilenos los montos se manejan en unidades enteras
        $costoTotal = (int)round((float)$costoTotalInput);
        $abono      = (int)round((float)$abonoInput);

        $busto   = ($bustoInput !== '' && is_numeric($bustoInput)) ? round((float)$bustoInput, 2) : null;
        $cintura = ($cinturaInput !== '' && is_numeric($cinturaInput)) ? round((float)$cinturaInput, 2) : null;
        $cadera  = ($caderaInput !== '' && is_numeric($caderaInput)) ? round((float)$caderaInput, 2) : null;
        $largo   = ($largoInput !== '' && is_numeric($largoInput)) ? round((float)$largoInput, 2) : null;

        try {
            // INICIO DE LA TRANSACCIÓN ACID
            $pdo->beginTransaction();

            // PASO A: Gestión del Cliente
            if ($tipoCliente === 'nuevo') {
                $stmtNewCli = $pdo->prepare("
                    INSERT INTO clientes (nombre, telefono, email) 
                    VALUES (:nombre, :telefono, :email)
                ");
                $stmtNewCli->execute([
                    ':nombre'   => $nuevoClienteNombre,
                    ':telefono' => $nuevoClienteTelefono ?: null,
                    ':email'    => $nuevoClienteEmail ?: null
                ]);
                $clienteIdFinal = (int)$pdo->lastInsertId();
            } else {
                $stmtCheck = $pdo->prepare("SELECT id FROM clientes WHERE id = ?");
                $stmtCheck->execute([$clienteExistenteId]);
                if (!$stmtCheck->fetch()) {
                    throw new Exception("El cliente seleccionado con ID {$clienteExistenteId} no existe.");
                }
                $clienteIdFinal = $clienteExistenteId;
            }

            // PASO B: Inserción del Pedido
            $stmtOrder = $pdo->prepare("
                INSERT INTO pedidos (
                    cliente_id, descripcion_prenda, estado, fecha_entrega, costo_total, abono
                ) VALUES (
                    :cliente_id, :descripcion, :estado, :fecha_entrega, :costo_total, :abono
                )
            ");
            $stmtOrder->execute([
                ':cliente_id'   => $clienteIdFinal,
                ':descripcion'  => $descripcionPrenda,
                ':estado'       => $estado,
                ':fecha_entrega'=> $fechaEntrega,
                ':costo_total'  => $costoTotal,
                ':abono'        => $abono
            ]);
            $pedidoIdFinal = (int)$pdo->lastInsertId();

            // PASO C: Inserción de Medidas Anatómicas
            $stmtMedidas = $pdo->prepare("
                INSERT INTO medidas (
                    pedido_id, busto, cintura, cadera, largo, observaciones
                ) VALUES (
                    :pedido_id, :busto, :cintura, :cadera, :largo, :observaciones
                )
            ");
            $stmtMedidas->execute([
                ':pedido_id'     => $pedidoIdFinal,
                ':busto'         => $busto,
                ':cintura'       => $cintura,
                ':cadera'        => $cadera,
                ':largo'         => $largo,
                ':observaciones' => $observaciones ?: null
            ]);

            // PASO D: Si se abonó dinero inicialmente, registrar el pago en la tabla transaccional
            if ($abono > 0) {
                $stmtPago = $pdo->prepare("
                    INSERT INTO pagos (pedido_id, monto) 
                    VALUES (:pedido_id, :monto)
                ");
                $stmtPago->execute([
                    ':pedido_id' => $pedidoIdFinal,
                    ':monto'     => $abono
                ]);
            }

            // CONFIRMACIÓN DE LA TRANSACCIÓN (COMMIT)
            $pdo->commit();

            setFlash('success', "¡Pedido #{$pedidoIdFinal} registrado con éxito con sus medidas y balance en pesos chilenos!");
            header("Location: ver_pedido.php?id={$pedidoIdFinal}");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error durante la transacción: " . $e->getMessage();
        }
    }
}

$pageTitle = "Nuevo Pedido";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1">
                    <i class="bi bi-file-earmark-plus text-primary me-2"></i>Registrar Nuevo Pedido
                </h2>
                <p class="text-muted mb-0">Creación simultánea y atómica de cliente, orden de confección y medidas.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No se pudo completar el registro:</strong> <?= e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="crear_pedido.php" method="POST" id="formPedido" novalidate>
            <!-- 1. SECCIÓN CLIENTE -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-person-badge text-primary me-2"></i>1. Datos del Cliente
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary small">Requerido</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de Selección:</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoExistente" value="existente" 
                                    <?= (!isset($_POST['tipo_cliente']) || $_POST['tipo_cliente'] === 'existente') ? 'checked' : ''; ?>
                                    onchange="toggleClienteModo()">
                                <label class="form-check-label" for="tipoExistente">
                                    Cliente Registrado
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo_cliente" id="tipoNuevo" value="nuevo"
                                    <?= (isset($_POST['tipo_cliente']) && $_POST['tipo_cliente'] === 'nuevo') ? 'checked' : ''; ?>
                                    onchange="toggleClienteModo()">
                                <label class="form-check-label" for="tipoNuevo">
                                    Registrar Nuevo Cliente
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Selector de Cliente Existente -->
                    <div id="seccionClienteExistente" class="mb-3">
                        <label for="cliente_id" class="form-label fw-semibold">Seleccionar Cliente Existente <span class="text-danger">*</span></label>
                        <select class="form-select" name="cliente_id" id="cliente_id">
                            <option value="">-- Seleccione un cliente --</option>
                            <?php foreach ($clientesExistentes as $cli): ?>
                                <option value="<?= (int)$cli['id']; ?>" <?= (isset($_POST['cliente_id']) && (int)$_POST['cliente_id'] === (int)$cli['id']) ? 'selected' : ''; ?>>
                                    <?= e($cli['nombre']); ?> <?= !empty($cli['telefono']) ? '(' . e($cli['telefono']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Si el cliente no figura en el listado, selecciona la opción "Registrar Nuevo Cliente".</div>
                    </div>

                    <!-- Campos para Nuevo Cliente -->
                    <div id="seccionClienteNuevo" class="row g-3 p-3 bg-light rounded border mb-2" style="display: none;">
                        <div class="col-md-5">
                            <label for="nuevo_nombre" class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuevo_nombre" id="nuevo_nombre" 
                                   value="<?= e($_POST['nuevo_nombre'] ?? ''); ?>" placeholder="Ej: Patricia Morales González">
                        </div>
                        <div class="col-md-3">
                            <label for="nuevo_telefono" class="form-label fw-semibold">Teléfono</label>
                            <input type="text" class="form-control" name="nuevo_telefono" id="nuevo_telefono" 
                                   value="<?= e($_POST['nuevo_telefono'] ?? ''); ?>" placeholder="+56 9 8765 4321">
                        </div>
                        <div class="col-md-4">
                            <label for="nuevo_email" class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" class="form-control" name="nuevo_email" id="nuevo_email" 
                                   value="<?= e($_POST['nuevo_email'] ?? ''); ?>" placeholder="cliente@correo.cl">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SECCIÓN PRENDA Y CONDICIONES COMERCIALES (CLP) -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-tag text-primary me-2"></i>2. Detalles de la Prenda y Presupuesto (Pesos Chilenos - CLP)
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary small">Requerido</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descripcion_prenda" class="form-label fw-semibold">Descripción de la Prenda / Confección <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="descripcion_prenda" id="descripcion_prenda" 
                               value="<?= e($_POST['descripcion_prenda'] ?? ''); ?>" 
                               placeholder="Ej: Vestido de fiesta corte sirena con forro o Ajuste de bastas de pantalón" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="estado" class="form-label fw-semibold">Estado Inicial <span class="text-danger">*</span></label>
                            <select class="form-select" name="estado" id="estado">
                                <option value="Pendiente" <?= (isset($_POST['estado']) && $_POST['estado'] === 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="En Proceso" <?= (isset($_POST['estado']) && $_POST['estado'] === 'En Proceso') ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="Prueba Lista" <?= (isset($_POST['estado']) && $_POST['estado'] === 'Prueba Lista') ? 'selected' : ''; ?>>Prueba Lista</option>
                                <option value="Entregado" <?= (isset($_POST['estado']) && $_POST['estado'] === 'Entregado') ? 'selected' : ''; ?>>Entregado</option>
                                <option value="Cancelado" <?= (isset($_POST['estado']) && $_POST['estado'] === 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_entrega" class="form-label fw-semibold">Fecha de Entrega <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_entrega" id="fecha_entrega" 
                                   value="<?= e($_POST['fecha_entrega'] ?? date('Y-m-d', strtotime('+7 days'))); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="costo_total" class="form-label fw-semibold">Costo Total (CLP $) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="1" min="0" class="form-control" name="costo_total" id="costo_total" 
                                       value="<?= e($_POST['costo_total'] ?? ''); ?>" placeholder="Ej: 150000" required>
                            </div>
                            <div class="form-text">Sin centavos (Pesos Chilenos).</div>
                        </div>
                        <div class="col-md-3">
                            <label for="abono" class="form-label fw-semibold">Abono Inicial (CLP $)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="1" min="0" class="form-control" name="abono" id="abono" 
                                       value="<?= e($_POST['abono'] ?? '0'); ?>" placeholder="Ej: 50000">
                            </div>
                            <div class="form-text">Registra automáticamente el primer pago.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. SECCIÓN MEDIDAS ANATÓMICAS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-rulers text-primary me-2"></i>3. Ficha de Medidas Anatómicas (en Centímetros)
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary small">Opcional</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6 col-md-3">
                            <label for="busto" class="form-label fw-semibold">Busto</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" class="form-control" name="busto" id="busto" 
                                       value="<?= e($_POST['busto'] ?? ''); ?>" placeholder="92.5">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="cintura" class="form-label fw-semibold">Cintura</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" class="form-control" name="cintura" id="cintura" 
                                       value="<?= e($_POST['cintura'] ?? ''); ?>" placeholder="68.0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="cadera" class="form-label fw-semibold">Cadera</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" class="form-control" name="cadera" id="cadera" 
                                       value="<?= e($_POST['cadera'] ?? ''); ?>" placeholder="96.0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="largo" class="form-label fw-semibold">Largo</label>
                            <div class="input-group">
                                <input type="number" step="0.1" min="0" class="form-control" name="largo" id="largo" 
                                       value="<?= e($_POST['largo'] ?? ''); ?>" placeholder="145.0">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label for="observaciones" class="form-label fw-semibold">Observaciones de Confección</label>
                        <textarea class="form-control" name="observaciones" id="observaciones" rows="3" 
                                  placeholder="Detalles sobre terminaciones, telas, pinzas, caída o requerimientos para la prueba..."><?= e($_POST['observaciones'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="d-flex justify-content-end gap-2 mb-5">
                <a href="index.php" class="btn btn-outline-secondary px-4">Cancelar</a>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Guardar Pedido (Transacción Segura)
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleClienteModo() {
    const esNuevo = document.getElementById('tipoNuevo').checked;
    const secExistente = document.getElementById('seccionClienteExistente');
    const secNuevo = document.getElementById('seccionClienteNuevo');
    const selCliente = document.getElementById('cliente_id');
    const inputNombre = document.getElementById('nuevo_nombre');

    if (esNuevo) {
        secExistente.style.display = 'none';
        secNuevo.style.display = 'flex';
        selCliente.value = '';
        inputNombre.focus();
    } else {
        secExistente.style.display = 'block';
        secNuevo.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleClienteModo();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
