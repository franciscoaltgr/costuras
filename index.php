<?php
/**
 * Panel Principal / Listado de Pedidos
 * Archivo: index.php
 * 
 * Consulta y lista pedidos activos y generales con cálculo de saldo pendiente (costo_total - abono).
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

// Filtro opcional por estado
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : 'activos';
$estadosPermitidos = ['activos', 'todos', 'Pendiente', 'En Proceso', 'Prueba Lista', 'Entregado', 'Cancelado'];

if (!in_array($filtroEstado, $estadosPermitidos, true)) {
    $filtroEstado = 'activos';
}

// Búsqueda libre: cliente, prenda, teléfono
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Paginación: 10 registros por página, ventana de 4 páginas
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

// Construcción compartida del WHERE
$where = [];
$params = [];

if ($filtroEstado === 'activos') {
    $where[] = "p.estado IN ('Pendiente', 'En Proceso', 'Prueba Lista')";
} elseif ($filtroEstado !== 'todos') {
    $where[] = "p.estado = :estado";
    $params[':estado'] = $filtroEstado;
}

if ($busqueda !== '') {
    $where[] = "(c.nombre LIKE :q OR p.descripcion_prenda LIKE :q OR c.telefono LIKE :q)";
    $params[':q'] = "%{$busqueda}%";
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    // 1) Total de registros que cumplen el filtro (para calcular páginas)
    $sqlCount = "SELECT COUNT(*) FROM pedidos p INNER JOIN clientes c ON p.cliente_id = c.id {$whereSQL}";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = (int)$stmtCount->fetchColumn();

    $totalPaginas = max(1, (int)ceil($totalRegistros / $perPage));
    $page = min($page, $totalPaginas);
    $offset = ($page - 1) * $perPage;

    // 2) Página solicitada (LIMIT/OFFSET con binding explícito a INT)
    $sql = "
        SELECT
            p.id,
            p.cliente_id,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono,
            p.descripcion_prenda,
            p.estado,
            p.fecha_entrega,
            p.costo_total,
            p.abono,
            (p.costo_total - p.abono) AS saldo_pendiente,
            p.created_at
        FROM pedidos p
        INNER JOIN clientes c ON p.cliente_id = c.id
        {$whereSQL}
        ORDER BY p.fecha_entrega ASC, p.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $pedidos = $stmt->fetchAll();

    // 3) Estadísticas globales (siempre sobre todo el universo, no sobre la página)
    $stmtStats = $pdo->query("
        SELECT
            COUNT(*) AS total_pedidos,
            SUM(CASE WHEN estado IN ('Pendiente', 'En Proceso', 'Prueba Lista') THEN 1 ELSE 0 END) AS activos_count,
            COALESCE(SUM(costo_total), 0) AS gran_total,
            COALESCE(SUM(abono), 0) AS total_abonos,
            COALESCE(SUM(costo_total - abono), 0) AS total_por_cobrar
        FROM pedidos
    ");
    $stats = $stmtStats->fetch();
} catch (PDOException $e) {
    die("Error al consultar pedidos: " . e($e->getMessage()));
}

// Helper: construye URL preservando el filtro, la búsqueda y aplicando una página concreta
$urlPagina = function (int $p) use ($filtroEstado, $busqueda): string {
    $qs = http_build_query(array_filter([
        'estado' => $filtroEstado,
        'q'      => $busqueda !== '' ? $busqueda : null,
        'page'   => $p,
    ], fn($v) => $v !== null && $v !== ''));
    return 'index.php?' . $qs;
};

// Ventana deslizante de 4 páginas centrada alrededor de la página actual
$ventana   = 4;
$pagInicio = max(1, min($page - 1, $totalPaginas - $ventana + 1));
$pagFin    = min($totalPaginas, $pagInicio + $ventana - 1);
$pagInicio = max(1, $pagFin - $ventana + 1);

$pageTitle = "Panel de Pedidos";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h2 class="h3 mb-1 fw-bold text-dark">
            <i class="bi bi-journal-bookmark text-primary me-2"></i>Gestión de Pedidos del Taller
        </h2>
        <p class="text-muted mb-0">Control de prendas, estados de confección y balance de cobros.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <a href="crear_pedido.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Nuevo Pedido
        </a>
    </div>
</div>

<!-- Tarjetas de Resumen Financiero y Operativo -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Pedidos Activos</span>
                <h3 class="fw-bold mb-0 text-primary mt-1"><?= (int)$stats['activos_count']; ?></h3>
                <small class="text-muted">de <?= (int)$stats['total_pedidos']; ?> registrados</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Total Facturado</span>
                <h3 class="fw-bold mb-0 text-dark mt-1"><?= formatMoney($stats['gran_total']); ?></h3>
                <small class="text-muted">Presupuesto global</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Total Recaudado</span>
                <h3 class="fw-bold mb-0 text-success mt-1"><?= formatMoney($stats['total_abonos']); ?></h3>
                <small class="text-muted">Pagos registrados</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body">
                <span class="text-muted small text-uppercase fw-semibold">Saldo por Cobrar</span>
                <h3 class="fw-bold mb-0 text-danger mt-1"><?= formatMoney($stats['total_por_cobrar']); ?></h3>
                <small class="text-muted">Pendiente de liquidación</small>
            </div>
        </div>
    </div>
</div>

<!-- Barra de Búsqueda + Filtros -->
<?php
// Helper interno para preservar la búsqueda en los enlaces de filtro
$urlFiltro = function (string $estado) use ($busqueda): string {
    $qs = http_build_query(array_filter([
        'estado' => $estado,
        'q'      => $busqueda !== '' ? $busqueda : null,
    ], fn($v) => $v !== null && $v !== ''));
    return 'index.php?' . $qs;
};
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-center mb-2">
            <input type="hidden" name="estado" value="<?= e($filtroEstado); ?>">
            <div class="col-lg-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Buscar por nombre del cliente, prenda o teléfono…"
                           value="<?= e($busqueda); ?>"
                           autocomplete="off">
                    <button type="submit" class="btn btn-primary fw-semibold">Buscar</button>
                    <?php if ($busqueda !== ''): ?>
                        <a href="<?= e($urlFiltro($filtroEstado)); ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5 text-lg-end">
                <?php if ($busqueda !== ''): ?>
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        <?= (int)$totalRegistros; ?> resultado(s) para «<strong><?= e($busqueda); ?></strong>»
                    </small>
                <?php else: ?>
                    <small class="text-muted">Busca por nombre, prenda o teléfono — Enter para filtrar.</small>
                <?php endif; ?>
            </div>
        </form>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
            <span class="text-muted small fw-semibold"><i class="bi bi-funnel me-1"></i>Filtrar por estado:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group">
                <a href="<?= e($urlFiltro('activos')); ?>"      class="btn <?= ($filtroEstado === 'activos') ? 'btn-dark' : 'btn-outline-secondary'; ?>">Activos (Taller)</a>
                <a href="<?= e($urlFiltro('todos')); ?>"        class="btn <?= ($filtroEstado === 'todos') ? 'btn-dark' : 'btn-outline-secondary'; ?>">Todos</a>
                <a href="<?= e($urlFiltro('Pendiente')); ?>"    class="btn <?= ($filtroEstado === 'Pendiente') ? 'btn-warning text-dark' : 'btn-outline-secondary'; ?>">Pendiente</a>
                <a href="<?= e($urlFiltro('En Proceso')); ?>"   class="btn <?= ($filtroEstado === 'En Proceso') ? 'btn-primary' : 'btn-outline-secondary'; ?>">En Proceso</a>
                <a href="<?= e($urlFiltro('Prueba Lista')); ?>" class="btn <?= ($filtroEstado === 'Prueba Lista') ? 'btn-info text-dark' : 'btn-outline-secondary'; ?>">Prueba Lista</a>
                <a href="<?= e($urlFiltro('Entregado')); ?>"    class="btn <?= ($filtroEstado === 'Entregado') ? 'btn-success' : 'btn-outline-secondary'; ?>">Entregado</a>
                <a href="<?= e($urlFiltro('Cancelado')); ?>"    class="btn <?= ($filtroEstado === 'Cancelado') ? 'btn-secondary' : 'btn-outline-secondary'; ?>">Cancelado</a>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Pedidos -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold">
            <i class="bi bi-table me-2"></i>
            <?= (int)$totalRegistros; ?> pedido(s)
            <?php if ($totalRegistros > 0): ?>
                <span class="text-muted fw-normal small">
                    · mostrando <?= (int)(($page - 1) * $perPage + 1); ?>–<?= (int)min($page * $perPage, $totalRegistros); ?>
                </span>
            <?php endif; ?>
        </span>
        <span class="small text-muted">Ordenado por fecha de entrega próxima · Página <?= (int)$page; ?> de <?= (int)$totalPaginas; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 60px;"># ID</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Prenda / Trabajo</th>
                    <th scope="col" class="text-center">Estado</th>
                    <th scope="col">Fecha Entrega</th>
                    <th scope="col" class="text-end">Costo Total</th>
                    <th scope="col" class="text-end">Abonado</th>
                    <th scope="col" class="text-end">Saldo Pendiente</th>
                    <th scope="col" class="text-center" style="min-width: 170px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            No se encontraron pedidos con el filtro seleccionado.
                            <div class="mt-2">
                                <a href="crear_pedido.php" class="btn btn-sm btn-primary">Registrar Nuevo Pedido</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <?php 
                            $saldo = (float)$p['saldo_pendiente'];
                            $isOverdue = (strtotime($p['fecha_entrega']) < strtotime(date('Y-m-d'))) && !in_array($p['estado'], ['Entregado', 'Cancelado']);
                        ?>
                        <tr>
                            <td class="fw-bold text-muted">#<?= (int)$p['id']; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($p['cliente_nombre']); ?></div>
                                <?php if (!empty($p['cliente_telefono'])): ?>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($p['cliente_telefono']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-wrap" style="max-width: 250px;">
                                    <?= e($p['descripcion_prenda']); ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-status <?= getEstadoBadgeClass($p['estado']); ?>">
                                    <?= e($p['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div><?= formatDate($p['fecha_entrega']); ?></div>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger small">Vencido</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold">
                                <?= formatMoney($p['costo_total']); ?>
                            </td>
                            <td class="text-end text-success fw-semibold">
                                <?= formatMoney($p['abono']); ?>
                            </td>
                            <td class="text-end fw-bold <?= ($saldo > 0) ? 'text-danger' : 'text-muted'; ?>">
                                <?= ($saldo <= 0) ? '<span class="text-success"><i class="bi bi-check2-circle me-1"></i>Pagado</span>' : formatMoney($saldo); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="ver_pedido.php?id=<?= (int)$p['id']; ?>" class="btn btn-outline-primary" title="Ver Detalles y Medidas">
                                        <i class="bi bi-eye"></i> Detalles
                                    </a>
                                    <a href="actualizar_estado.php?id=<?= (int)$p['id']; ?>" class="btn btn-outline-secondary" title="Actualizar Estado o Registrar Abono">
                                        <i class="bi bi-arrow-repeat"></i> Estado/Pago
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPaginas > 1): ?>
<nav aria-label="Paginación de pedidos" class="mt-3">
    <ul class="pagination justify-content-center mb-0">
        <!-- Anterior -->
        <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?= ($page > 1) ? e($urlPagina($page - 1)) : '#'; ?>" aria-label="Anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>

        <?php if ($pagInicio > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e($urlPagina(1)); ?>">1</a></li>
            <?php if ($pagInicio > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $pagInicio; $i <= $pagFin; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : ''; ?>">
                <a class="page-link" href="<?= e($urlPagina($i)); ?>"><?= $i; ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($pagFin < $totalPaginas): ?>
            <?php if ($pagFin < $totalPaginas - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= e($urlPagina($totalPaginas)); ?>"><?= (int)$totalPaginas; ?></a></li>
        <?php endif; ?>

        <!-- Siguiente -->
        <li class="page-item <?= ($page >= $totalPaginas) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?= ($page < $totalPaginas) ? e($urlPagina($page + 1)) : '#'; ?>" aria-label="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

