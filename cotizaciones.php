<?php
/**
 * Módulo: Listado de Cotizaciones - Grill Guard
 * Archivo: cotizaciones.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

// Filtro opcional por estado
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : 'todos';
$busqueda     = isset($_GET['q']) ? trim($_GET['q']) : '';

$estadosValidos = ['todos', 'Borrador', 'Enviada', 'Aprobada', 'Rechazada', 'Convertida en Pedido'];
if (!in_array($filtroEstado, $estadosValidos, true)) {
    $filtroEstado = 'todos';
}

$sql = "
    SELECT 
        c.*,
        GROUP_CONCAT(CONCAT(ci.cantidad, 'x ', ci.producto, IF(ci.color != '', CONCAT(' (', ci.color, ')'), '')) SEPARATOR ', ') AS resumen_items
    FROM cotizaciones c
    LEFT JOIN cotizacion_items ci ON c.id = ci.cotizacion_id
";

$where = [];
$params = [];

if ($filtroEstado !== 'todos') {
    $where[] = "c.estado = :estado";
    $params[':estado'] = $filtroEstado;
}

if (!empty($busqueda)) {
    $where[] = "(c.numero LIKE :busqueda OR c.cliente_nombre LIKE :busqueda OR c.cliente_telefono LIKE :busqueda)";
    $params[':busqueda'] = "%{$busqueda}%";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY c.id ORDER BY c.fecha DESC, c.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cotizaciones = $stmt->fetchAll();

    // Estadísticas generales
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) AS total_cotizaciones,
            SUM(CASE WHEN estado = 'Enviada' THEN 1 ELSE 0 END) AS enviadas_count,
            SUM(CASE WHEN estado = 'Aprobada' THEN 1 ELSE 0 END) AS aprobadas_count,
            SUM(CASE WHEN estado = 'Convertida en Pedido' THEN 1 ELSE 0 END) AS convertidas_count,
            COALESCE(SUM(total), 0) AS gran_total_cotizado
        FROM cotizaciones
    ");
    $stats = $stmtStats->fetch();

} catch (PDOException $e) {
    die("Error al consultar cotizaciones: " . e($e->getMessage()));
}

$pageTitle = "Cotizaciones";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h2 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-file-earmark-text text-primary me-2"></i>Cotizaciones Grill Guard
        </h2>
        <p class="text-muted mb-0">Gestión y emisión de presupuestos profesionales con formato oficial y descarga en PDF.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <a href="nueva_cotizacion.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Nueva Cotización
        </a>
    </div>
</div>

<!-- Tarjetas Estadísticas -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Total Emitidas</span>
                <h3 class="fw-bold mb-0 text-dark mt-1"><?= (int)$stats['total_cotizaciones']; ?></h3>
                <small class="text-muted">histórico en sistema</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Enviadas / En Revisión</span>
                <h3 class="fw-bold mb-0 text-primary mt-1"><?= (int)$stats['enviadas_count']; ?></h3>
                <small class="text-muted">esperando confirmación</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Aprobadas / Convertidas</span>
                <h3 class="fw-bold mb-0 text-success mt-1"><?= (int)$stats['aprobadas_count'] + (int)$stats['convertidas_count']; ?></h3>
                <small class="text-muted">confecciones acordadas</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Monto Total Cotizado</span>
                <h3 class="fw-bold mb-0 text-dark mt-1"><?= formatMoney($stats['gran_total_cotizado']); ?></h3>
                <small class="text-muted">IVA incluido (CLP)</small>
            </div>
        </div>
    </div>
</div>

<!-- Barra de Búsqueda y Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" action="cotizaciones.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Buscar por N° Cotización, Cliente o Teléfono..." value="<?= e($busqueda); ?>">
                </div>
            </div>
            <div class="col-md-5">
                <div class="d-flex flex-wrap gap-1 align-items-center">
                    <span class="small text-muted me-1">Estado:</span>
                    <a href="cotizaciones.php?estado=todos<?= !empty($busqueda) ? '&q=' . urlencode($busqueda) : ''; ?>" 
                       class="btn btn-sm <?= ($filtroEstado === 'todos') ? 'btn-secondary fw-bold' : 'btn-outline-secondary'; ?>">Todos</a>
                    <a href="cotizaciones.php?estado=Enviada<?= !empty($busqueda) ? '&q=' . urlencode($busqueda) : ''; ?>" 
                       class="btn btn-sm <?= ($filtroEstado === 'Enviada') ? 'btn-primary fw-bold' : 'btn-outline-primary'; ?>">Enviadas</a>
                    <a href="cotizaciones.php?estado=Aprobada<?= !empty($busqueda) ? '&q=' . urlencode($busqueda) : ''; ?>" 
                       class="btn btn-sm <?= ($filtroEstado === 'Aprobada') ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">Aprobadas</a>
                    <a href="cotizaciones.php?estado=Convertida en Pedido<?= !empty($busqueda) ? '&q=' . urlencode($busqueda) : ''; ?>" 
                       class="btn btn-sm <?= ($filtroEstado === 'Convertida en Pedido') ? 'btn-info fw-bold text-dark' : 'btn-outline-info text-dark'; ?>">En Taller</a>
                </div>
            </div>
            <div class="col-md-2 text-md-end">
                <?php if (!empty($busqueda) || $filtroEstado !== 'todos'): ?>
                    <a href="cotizaciones.php" class="btn btn-sm btn-link text-decoration-none text-muted">Limpiar</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-sm btn-dark">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla Principal de Cotizaciones -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 100px;">N° Cot.</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Prendas / Ítems</th>
                    <th scope="col" class="text-end">Neto</th>
                    <th scope="col" class="text-end">Total c/IVA</th>
                    <th scope="col" class="text-end">Abono (50%)</th>
                    <th scope="col" class="text-center">Estado</th>
                    <th scope="col" class="text-center" style="width: 160px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cotizaciones)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                            No se encontraron cotizaciones registradas.
                            <div class="mt-3">
                                <a href="nueva_cotizacion.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Crear Primera Cotización
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cotizaciones as $c): ?>
                        <tr>
                            <td>
                                <a href="ver_cotizacion.php?id=<?= (int)$c['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                    #<?= e($c['numero']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($c['cliente_nombre']); ?></div>
                                <?php if (!empty($c['cliente_telefono'])): ?>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($c['cliente_telefono']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small"><?= formatDate($c['fecha'], '-'); ?></span>
                            </td>
                            <td>
                                <div class="text-truncate small text-muted" style="max-width: 260px;" title="<?= e($c['resumen_items'] ?? ''); ?>">
                                    <?= e($c['resumen_items'] ?: 'Sin ítems'); ?>
                                </div>
                            </td>
                            <td class="text-end fw-semibold text-muted">
                                <?= formatMoney($c['subtotal']); ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                <?= formatMoney($c['total']); ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?= formatMoney($c['abono']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= getEstadoCotizacionBadgeClass($c['estado']); ?>">
                                    <?= e($c['estado']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="ver_cotizacion.php?id=<?= (int)$c['id']; ?>" class="btn btn-outline-primary" title="Ver formato oficial">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="generar_pdf_cotizacion.php?id=<?= (int)$c['id']; ?>" target="_blank" class="btn btn-outline-danger" title="Abrir / Descargar PDF">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                    <?php if ($c['estado'] !== 'Convertida en Pedido'): ?>
                                        <a href="convertir_cotizacion_a_pedido.php?id=<?= (int)$c['id']; ?>" class="btn btn-outline-success" title="Convertir a Pedido de Taller">
                                            <i class="bi bi-scissors"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
