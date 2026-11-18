<?php
/**
 * Módulo: Actualizar Estado y Registrar Pagos Parciales
 * Archivo: actualizar_estado.php
 * Moneda: Pesos Chilenos (CLP)
 * Codificación: UTF-8
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$pedidoId = filter_var($_GET['id'] ?? $_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT);

if (!$pedidoId) {
    setFlash('danger', 'ID de pedido no especificado o inválido.');
    header('Location: index.php');
    exit;
}

$error = null;

// PROCESAR PETICIÓN POST (Transacción de Actualización)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
    $montoPagoInput = str_replace(['.', ','], ['', '.'], trim($_POST['monto_pago'] ?? '0'));
    $estadosValidos = ['Pendiente', 'En Proceso', 'Prueba Lista', 'Entregado', 'Cancelado'];

    // Validar estado
    if (!empty($nuevoEstado) && !in_array($nuevoEstado, $estadosValidos, true)) {
        $error = "El estado seleccionado no es válido.";
    }

    // Validar monto de pago si se ingresó
    $registrarPago = false;
    $montoPago = 0;
    if ($montoPagoInput !== '' && $montoPagoInput !== '0') {
        if (!is_numeric($montoPagoInput) || (float)$montoPagoInput <= 0) {
            $error = "El monto del pago parcial debe ser un número entero mayor a cero.";
        } else {
            $registrarPago = true;
            $montoPago = (int)round((float)$montoPagoInput);
        }
    }

    if (!$error) {
        try {
            // INICIO DE LA TRANSACCIÓN ACID
            $pdo->beginTransaction();

            // Bloqueo pesimista de fila con FOR UPDATE
            $stmtLock = $pdo->prepare("
                SELECT id, costo_total, abono, estado 
                FROM pedidos 
                WHERE id = :id 
                FOR UPDATE
            ");
            $stmtLock->execute([':id' => $pedidoId]);
            $pedidoActual = $stmtLock->fetch();

            if (!$pedidoActual) {
                throw new Exception("El pedido #{$pedidoId} no fue encontrado.");
            }

            $costoTotal   = (int)$pedidoActual['costo_total'];
            $abonoActual  = (int)$pedidoActual['abono'];
            $saldoActual  = $costoTotal - $abonoActual;
            $cambiosRealizados = [];

            // 1. Lógica de Pago Parcial y Actualización de Abono en CLP
            if ($registrarPago) {
                if ($montoPago > $saldoActual) {
                    throw new Exception("El pago ingresado (" . formatMoney($montoPago) . ") supera el saldo pendiente (" . formatMoney($saldoActual) . ").");
                }

                // Inserción en la bitácora transaccional 'pagos'
                $stmtInsertPago = $pdo->prepare("
                    INSERT INTO pagos (pedido_id, monto) 
                    VALUES (:pedido_id, :monto)
                ");
                $stmtInsertPago->execute([
                    ':pedido_id' => $pedidoId,
                    ':monto'     => $montoPago
                ]);

                // Actualización del campo acumulativo 'abono' en 'pedidos'
                $nuevoAbono = $abonoActual + $montoPago;
                $stmtUpdateAbono = $pdo->prepare("
                    UPDATE pedidos 
                    SET abono = :nuevo_abono 
                    WHERE id = :id
                ");
                $stmtUpdateAbono->execute([
                    ':nuevo_abono' => $nuevoAbono,
                    ':id'          => $pedidoId
                ]);

                $cambiosRealizados[] = "Pago parcial de " . formatMoney($montoPago) . " registrado con éxito";
            }

            // 2. Lógica de Cambio de Estado
            if (!empty($nuevoEstado) && $nuevoEstado !== $pedidoActual['estado']) {
                $stmtUpdateEstado = $pdo->prepare("
                    UPDATE pedidos 
                    SET estado = :nuevo_estado 
                    WHERE id = :id
                ");
                $stmtUpdateEstado->execute([
                    ':nuevo_estado' => $nuevoEstado,
                    ':id'           => $pedidoId
                ]);

                $cambiosRealizados[] = "Estado actualizado a '{$nuevoEstado}'";
            }

            if (empty($cambiosRealizados)) {
                $pdo->rollBack();
                setFlash('info', "No se registraron cambios en el pedido #{$pedidoId}.");
            } else {
                // CONFIRMACIÓN DE LA TRANSACCIÓN (COMMIT)
                $pdo->commit();
                setFlash('success', "Actualización exitosa: " . implode(" y ", $cambiosRealizados) . ".");
            }

            header("Location: ver_pedido.php?id={$pedidoId}");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// OBTENER INFORMACIÓN DEL PEDIDO PARA LA VISTA
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            (p.costo_total - p.abono) AS saldo_pendiente
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $pedidoId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        setFlash('danger', "El pedido #{$pedidoId} no existe.");
        header('Location: index.php');
        exit;
    }

    $stmtPagos = $pdo->prepare("SELECT * FROM pagos WHERE pedido_id = :pedido_id ORDER BY fecha_pago DESC");
    $stmtPagos->execute([':pedido_id' => $pedidoId]);
    $historialPagos = $stmtPagos->fetchAll();

} catch (PDOException $e) {
    die("Error al consultar el pedido: " . e($e->getMessage()));
}

$pageTitle = "Actualizar Pedido #{$pedido['id']}";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1">
                    <i class="bi bi-arrow-repeat text-primary me-2"></i>Actualizar Estado y Abonos
                </h2>
                <p class="text-muted mb-0">Gestión de flujo de confección y registro de abonos en pesos chilenos (CLP).</p>
            </div>
            <div class="btn-group">
                <a href="ver_pedido.php?id=<?= (int)$pedido['id']; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i> Ver Ficha
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-list-ul me-1"></i> Listado
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>No se pudo procesar la actualización:</strong> <?= e($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tarjeta de Resumen del Pedido -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Pedido #<?= (int)$pedido['id']; ?>: <?= e($pedido['descripcion_prenda']); ?></span>
                    <span class="badge badge-status <?= getEstadoBadgeClass($pedido['estado']); ?>">
                        <?= e($pedido['estado']); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small d-block">Cliente:</span>
                        <strong><?= e($pedido['cliente_nombre']); ?></strong>
                        <?php if (!empty($pedido['cliente_telefono'])): ?>
                            <div class="small text-muted"><?= e($pedido['cliente_telefono']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small d-block">Fecha de Entrega:</span>
                        <strong><?= formatDate($pedido['fecha_entrega']); ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small d-block">Costo Total:</span>
                        <strong class="text-dark"><?= formatMoney($pedido['costo_total']); ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-6">
                        <span class="text-muted small d-block">Total Abonado a la Fecha:</span>
                        <strong class="text-success fs-5"><?= formatMoney($pedido['abono']); ?></strong>
                    </div>
                    <div class="col-sm-6 col-md-6">
                        <span class="text-muted small d-block">Saldo Pendiente de Pago:</span>
                        <strong class="fs-5 <?= ((int)$pedido['saldo_pendiente'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                            <?= formatMoney($pedido['saldo_pendiente']); ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de Actualización Transaccional -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light py-3">
                <span class="fw-bold text-dark">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Formulario de Modificación Transaccional
                </span>
            </div>
            <div class="card-body">
                <form action="actualizar_estado.php" method="POST">
                    <input type="hidden" name="pedido_id" value="<?= (int)$pedido['id']; ?>">

                    <!-- 1. Cambio de Estado -->
                    <div class="mb-4">
                        <label for="nuevo_estado" class="form-label fw-semibold">
                            Cambiar Estado del Trabajo:
                        </label>
                        <select class="form-select" name="nuevo_estado" id="nuevo_estado">
                            <?php 
                                $estados = ['Pendiente', 'En Proceso', 'Prueba Lista', 'Entregado', 'Cancelado'];
                                foreach ($estados as $est):
                            ?>
                                <option value="<?= $est; ?>" <?= ($pedido['estado'] === $est) ? 'selected' : ''; ?>>
                                    <?= $est; ?> <?= ($pedido['estado'] === $est) ? '(Actual)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Actualiza la etapa de confección dentro del taller.</div>
                    </div>

                    <hr class="my-4">

                    <!-- 2. Registro de Pago Parcial (CLP) -->
                    <div class="mb-4">
                        <label for="monto_pago" class="form-label fw-semibold">
                            Registrar Nuevo Pago Parcial / Abono (Pesos Chilenos - CLP $):
                        </label>
                        <?php if ((int)$pedido['saldo_pendiente'] <= 0): ?>
                            <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                                <div>Este pedido se encuentra <strong>totalmente pagado</strong> (Saldo $ 0). No es necesario ingresar más abonos.</div>
                            </div>
                        <?php else: ?>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="1" min="1" max="<?= (int)$pedido['saldo_pendiente']; ?>" 
                                       class="form-control" name="monto_pago" id="monto_pago" placeholder="Ej: 50000">
                                <button type="button" class="btn btn-outline-secondary" onclick="liquidarSaldoTotal(<?= (int)$pedido['saldo_pendiente']; ?>)">
                                    Pagar Saldo Total (<?= formatMoney($pedido['saldo_pendiente']); ?>)
                                </button>
                            </div>
                            <div class="form-text">
                                Monto máximo a pagar: <strong><?= formatMoney($pedido['saldo_pendiente']); ?></strong>. 
                                Se registrará en <code>pagos</code> y actualizará el campo <code>abono</code> atómicamente.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="ver_pedido.php?id=<?= (int)$pedido['id']; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Aplicar Cambios (Transacción Segura)
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Historial Rápido de Pagos Registrados -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <span class="fw-bold">
                    <i class="bi bi-cash-stack text-success me-2"></i>Historial de Pagos Registrados (<?= count($historialPagos); ?>)
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"># ID Pago</th>
                            <th scope="col">Fecha y Hora</th>
                            <th scope="col" class="text-end">Monto Abonado (CLP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historialPagos)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-3 text-muted">Aún no se han registrado abonos para este pedido.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historialPagos as $pago): ?>
                                <tr>
                                    <td class="text-muted">#<?= (int)$pago['id']; ?></td>
                                    <td><?= date('d/m/Y H:i:s', strtotime($pago['fecha_pago'])); ?></td>
                                    <td class="text-end fw-bold text-success"><?= formatMoney($pago['monto']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
function liquidarSaldoTotal(saldo) {
    const inputPago = document.getElementById('monto_pago');
    if (inputPago) {
        inputPago.value = Math.round(saldo);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
