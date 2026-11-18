<?php
/**
 * Módulo: Vista de Detalles del Pedido y Medidas
 * Archivo: ver_pedido.php
 * Moneda: Pesos Chilenos (CLP)
 * Codificación: UTF-8
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$pedidoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$pedidoId) {
    setFlash('danger', 'ID de pedido inválido.');
    header('Location: index.php');
    exit;
}

try {
    // 1. Obtener información del pedido y cliente
    $stmtPedido = $pdo->prepare("
        SELECT 
            p.*,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,
            c.created_at AS cliente_desde,
            (p.costo_total - p.abono) AS saldo_pendiente
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        WHERE p.id = :id
    ");
    $stmtPedido->execute([':id' => $pedidoId]);
    $pedido = $stmtPedido->fetch();

    if (!$pedido) {
        setFlash('danger', "El pedido #{$pedidoId} no fue encontrado.");
        header('Location: index.php');
        exit;
    }

    // 2. Obtener medidas corporales asociadas
    $stmtMedidas = $pdo->prepare("SELECT * FROM medidas WHERE pedido_id = :pedido_id LIMIT 1");
    $stmtMedidas->execute([':pedido_id' => $pedidoId]);
    $medidas = $stmtMedidas->fetch();

    // 3. Obtener historial cronológico de pagos
    $stmtPagos = $pdo->prepare("SELECT * FROM pagos WHERE pedido_id = :pedido_id ORDER BY fecha_pago ASC");
    $stmtPagos->execute([':pedido_id' => $pedidoId]);
    $pagos = $stmtPagos->fetchAll();

} catch (PDOException $e) {
    die("Error al consultar detalles del pedido: " . e($e->getMessage()));
}

$pageTitle = "Detalle Pedido #{$pedido['id']}";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Encabezado de la Vista -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h3 fw-bold mb-0">Pedido #<?= (int)$pedido['id']; ?></h2>
            <span class="badge badge-status <?= getEstadoBadgeClass($pedido['estado']); ?>">
                <?= e($pedido['estado']); ?>
            </span>
        </div>
        <p class="text-muted mb-0">Registrado el <?= date('d/m/Y H:i', strtotime($pedido['created_at'])); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Listado
        </a>
        <a href="actualizar_estado.php?id=<?= (int)$pedido['id']; ?>" class="btn btn-primary">
            <i class="bi bi-pencil-square me-1"></i> Actualizar Estado / Pagar
        </a>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar">
            <i class="bi bi-trash3 me-1"></i> Eliminar
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Columna Izquierda: Datos del Trabajo y Medidas -->
    <div class="col-lg-8">
        
        <!-- Detalle de la Prenda -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-bold text-dark">
                    <i class="bi bi-scissors text-primary me-2"></i>Especificaciones de la Prenda
                </span>
            </div>
            <div class="card-body">
                <h5 class="card-title fw-bold text-dark mb-3"><?= e($pedido['descripcion_prenda']); ?></h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Fecha Prometida de Entrega:</span>
                        <strong class="fs-6"><?= formatDate($pedido['fecha_entrega']); ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Estado del Trabajo:</span>
                        <strong class="fs-6"><?= e($pedido['estado']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ficha de Medidas Anatómicas -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-rulers text-primary me-2"></i>Ficha Técnica de Medidas Anatómicas
                </span>
                <span class="badge bg-secondary-subtle text-secondary small">Unidad: cm</span>
            </div>
            <div class="card-body">
                <?php if (!$medidas): ?>
                    <p class="text-muted mb-0">No se registraron medidas para este pedido.</p>
                <?php else: ?>
                    <div class="row text-center g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded border">
                                <span class="text-muted small text-uppercase d-block">Busto</span>
                                <span class="fs-4 fw-bold text-dark">
                                    <?= $medidas['busto'] !== null ? e(rtrim(rtrim((string)$medidas['busto'], '0'), '.')) . ' <small class="fs-6 text-muted">cm</small>' : '-'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded border">
                                <span class="text-muted small text-uppercase d-block">Cintura</span>
                                <span class="fs-4 fw-bold text-dark">
                                    <?= $medidas['cintura'] !== null ? e(rtrim(rtrim((string)$medidas['cintura'], '0'), '.')) . ' <small class="fs-6 text-muted">cm</small>' : '-'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded border">
                                <span class="text-muted small text-uppercase d-block">Cadera</span>
                                <span class="fs-4 fw-bold text-dark">
                                    <?= $medidas['cadera'] !== null ? e(rtrim(rtrim((string)$medidas['cadera'], '0'), '.')) . ' <small class="fs-6 text-muted">cm</small>' : '-'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-3 bg-light rounded border">
                                <span class="text-muted small text-uppercase d-block">Largo</span>
                                <span class="fs-4 fw-bold text-dark">
                                    <?= $medidas['largo'] !== null ? e(rtrim(rtrim((string)$medidas['largo'], '0'), '.')) . ' <small class="fs-6 text-muted">cm</small>' : '-'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-semibold text-muted small text-uppercase">Observaciones de Confección:</label>
                        <div class="p-3 bg-light rounded border">
                            <?= !empty($medidas['observaciones']) ? nl2br(e($medidas['observaciones'])) : '<span class="text-muted fst-italic">Sin observaciones adicionales.</span>'; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de Pagos y Abonos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">
                    <i class="bi bi-receipt text-primary me-2"></i>Bitácora de Pagos (<?= count($pagos); ?> transacciones)
                </span>
                <a href="actualizar_estado.php?id=<?= (int)$pedido['id']; ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-plus-lg me-1"></i> Registrar Abono
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"># ID</th>
                            <th scope="col">Fecha y Hora</th>
                            <th scope="col" class="text-end">Monto Abonado (CLP)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pagos)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    No se registran transacciones de pago para este pedido.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pagos as $pago): ?>
                                <tr>
                                    <td class="text-muted">#<?= (int)$pago['id']; ?></td>
                                    <td>
                                        <i class="bi bi-clock me-1 text-muted"></i>
                                        <?= date('d/m/Y - H:i:s', strtotime($pago['fecha_pago'])); ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        <?= formatMoney($pago['monto']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">Total Suma de Pagos:</th>
                            <th class="text-end text-success"><?= formatMoney($pedido['abono']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

    <!-- Columna Derecha: Tarjeta de Cliente y Balance Financiero -->
    <div class="col-lg-4">
        
        <!-- Balance Financiero en Pesos Chilenos -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="bi bi-wallet2 me-2"></i>Estado de Cuenta (CLP)
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Costo Total:</span>
                    <strong class="text-dark"><?= formatMoney($pedido['costo_total']); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Abonos Realizados:</span>
                    <strong class="text-success"><?= formatMoney($pedido['abono']); ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Saldo Pendiente:</span>
                    <span class="fs-4 fw-bold <?= ((int)$pedido['saldo_pendiente'] > 0) ? 'text-danger' : 'text-success'; ?>">
                        <?= ((int)$pedido['saldo_pendiente'] <= 0) ? 'LIQUIDADO' : formatMoney($pedido['saldo_pendiente']); ?>
                    </span>
                </div>
            </div>
            <div class="card-footer bg-white border-top-0 pt-0">
                <a href="actualizar_estado.php?id=<?= (int)$pedido['id']; ?>" class="btn btn-outline-primary w-100 fw-semibold">
                    <i class="bi bi-cash-coin me-1"></i> Administrar Estado / Abonos
                </a>
            </div>
        </div>

        <!-- Ficha del Cliente -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <span class="fw-bold text-dark">
                    <i class="bi bi-person-circle text-primary me-2"></i>Información del Cliente
                </span>
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-2 text-dark"><?= e($pedido['cliente_nombre']); ?></h5>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="bi bi-telephone text-muted me-2"></i>
                        <?= !empty($pedido['cliente_telefono']) ? e($pedido['cliente_telefono']) : '<span class="text-muted">Sin teléfono</span>'; ?>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-envelope text-muted me-2"></i>
                        <?= !empty($pedido['cliente_email']) ? e($pedido['cliente_email']) : '<span class="text-muted">Sin correo</span>'; ?>
                    </li>
                    <li class="small text-muted">
                        <i class="bi bi-calendar-check me-2"></i>
                        Cliente desde <?= formatDate($pedido['cliente_desde']); ?>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Modal de Confirmación de Eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="eliminar_pedido.php" method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="pedido_id" value="<?= (int)$pedido['id']; ?>">
                <p>¿Está seguro de que desea eliminar el <strong>Pedido #<?= (int)$pedido['id']; ?></strong> (<em><?= e($pedido['descripcion_prenda']); ?></em>)?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta acción eliminará de forma segura y transaccional sus medidas asociadas y el registro de pagos correspondiente.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger fw-bold">
                    <i class="bi bi-trash3 me-1"></i> Sí, Eliminar Pedido
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
