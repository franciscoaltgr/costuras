<?php
/**
 * Módulo: Vista Oficial de Cotización - Formato Grill Guard
 * Archivo: ver_cotizacion.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    setFlash('danger', 'ID de cotización inválido.');
    header('Location: cotizaciones.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        setFlash('danger', "La cotización #{$id} no existe.");
        header('Location: cotizaciones.php');
        exit;
    }

    $stmtItems = $pdo->prepare("SELECT * FROM cotizacion_items WHERE cotizacion_id = :id ORDER BY id ASC");
    $stmtItems->execute([':id' => $id]);
    $items = $stmtItems->fetchAll();

} catch (PDOException $e) {
    die("Error al consultar la cotización: " . e($e->getMessage()));
}

$pageTitle = "Cotización #" . $cotizacion['numero'];
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Estilos especializados para reproducir con máxima fidelidad la plantilla oficial de la imagen */
.cotizacion-sheet {
    background-color: #ffffff;
    max-width: 820px;
    margin: 0 auto;
    padding: 35px 40px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    border: 1px solid #dcdcdc;
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
    color: #000000;
}

.cotizacion-table-header th {
    border: 1px solid #333333 !important;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 6px 10px;
}

.th-peach {
    background-color: #f8cbad !important;
}

.th-yellow {
    background-color: #ffe699 !important;
}

.table-cotizacion {
    border-collapse: collapse;
    width: 100%;
}

.table-cotizacion td, .table-cotizacion th {
    border: 1px solid #333333 !important;
    padding: 6px 10px;
    font-size: 0.9rem;
}

.badge-cot-numero {
    background-color: #a9d18e;
    color: #111111;
    font-size: 1.05rem;
    font-weight: 700;
    padding: 9px 24px;
    border: 1px solid #70ad47;
    display: inline-block;
    letter-spacing: 0.5px;
}

.table-cliente {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 25px;
}

.table-cliente td {
    border: 1px solid #333333 !important;
    padding: 5px 10px;
    font-size: 0.88rem;
}

.table-cliente td.label-cell {
    font-weight: bold;
    width: 130px;
    background-color: #ffffff;
}

.table-totales {
    border-collapse: collapse;
    margin-left: auto;
    width: 280px;
}

.table-totales td {
    border: 1px solid #333333 !important;
    padding: 5px 10px;
    font-size: 0.9rem;
}

.table-totales td.label-tot {
    font-weight: bold;
    text-align: right;
    width: 110px;
}

.table-totales td.val-tot {
    text-align: right;
    font-weight: bold;
}

/* Reglas de Impresión Limpia (oculta navbar, botones y pie del sistema) */
@media print {
    body {
        background-color: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    nav, .no-print, footer {
        display: none !important;
    }
    main {
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
    }
    .cotizacion-sheet {
        box-shadow: none !important;
        border: none !important;
        max-width: 100% !important;
        padding: 10px !important;
    }
}
</style>

<!-- Barra Superior de Acciones -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 no-print">
    <div class="d-flex align-items-center gap-2">
        <a href="cotizaciones.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver a Cotizaciones
        </a>
        <span class="badge <?= getEstadoCotizacionBadgeClass($cotizacion['estado']); ?> fs-6 py-2 px-3">
            Estado: <?= e($cotizacion['estado']); ?>
        </span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <!-- Generador Opensource FPDF -->
        <a href="generar_pdf_cotizacion.php?id=<?= (int)$cotizacion['id']; ?>" target="_blank" class="btn btn-danger fw-bold shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Descargar PDF Oficial (FPDF)
        </a>
        <!-- Imprimir con el navegador -->
        <button type="button" onclick="window.print()" class="btn btn-outline-dark">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
        <?php if ($cotizacion['estado'] !== 'Convertida en Pedido'): ?>
            <a href="convertir_cotizacion_a_pedido.php?id=<?= (int)$cotizacion['id']; ?>" class="btn btn-success fw-bold shadow-sm">
                <i class="bi bi-scissors me-1"></i> Convertir a Pedido
            </a>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar">
            <i class="bi bi-trash3"></i>
        </button>
    </div>
</div>

<!-- FORMATO DE COTIZACIÓN VISUAL (ESTILO EXACTO A LA IMAGEN) -->
<div class="cotizacion-sheet" id="areaCotizacion">
    <!-- Fila 1: Logo, Datos del Taller Grill Guard y Número de Cotización -->
    <div class="row align-items-start mb-3">
        <!-- Logo y Datos Taller -->
        <div class="col-7">
            <div class="d-flex gap-3 align-items-start">
                <div>
                    <img src="assets/img/logo_grill_guard.jpg" alt="Grill Guard" style="width: 110px; height: 116px; object-fit: cover; border-radius: 4px;" class="shadow-sm">
                </div>
                <div>
                    <h4 class="fw-bold mb-1" style="letter-spacing: 0.5px; color: #111;">GRILL GUARD</h4>
                    <p class="mb-1 fw-bold text-secondary" style="font-size: 0.95rem;">Pecheras Parrilleras</p>
                    <p class="mb-0" style="font-size: 0.9rem; color: #333;"><i class="bi bi-whatsapp me-1 text-success"></i> 56910131407</p>
                    <p class="mb-0" style="font-size: 0.9rem; color: #333;"><i class="bi bi-envelope me-1"></i> grillguardch@gmail.com</p>
                    <p class="mb-0" style="font-size: 0.9rem; color: #333;"><i class="bi bi-geo-alt me-1"></i> Rosas 1339 Santiago Centro</p>
                    <p class="mb-0" style="font-size: 0.9rem; color: #333;"><i class="bi bi-geo-alt me-1"></i> La Hermita 1556 Talca</p>
                </div>
            </div>
        </div>
        <!-- Número de Cotización en Verde -->
        <div class="col-5 text-end">
            <div class="badge-cot-numero mb-2">
                COTIZACION <?= e($cotizacion['numero']); ?>
            </div>
        </div>
    </div>

    <!-- Fila 2: Tabla de Datos del Cliente -->
    <table class="table-cliente">
        <tr>
            <td class="label-cell">NOMBRE</td>
            <td colspan="3" class="fw-bold"><?= e(strtoupper($cotizacion['cliente_nombre'])); ?></td>
        </tr>
        <tr>
            <td class="label-cell">DIRECCION</td>
            <td colspan="3"><?= e($cotizacion['cliente_direccion'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="label-cell">TELEFONO</td>
            <td colspan="3"><?= e($cotizacion['cliente_telefono'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="label-cell">CORREO</td>
            <td colspan="3"><?= e($cotizacion['cliente_email'] ?? ''); ?></td>
        </tr>
        <tr>
            <td class="label-cell">FECHA</td>
            <td style="width: 250px;" class="text-center fw-bold">
                <?= formatDate($cotizacion['fecha'], '-'); ?>
            </td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <!-- Fila 3: Tabla de Confección / Productos -->
    <table class="table-cotizacion mb-3">
        <thead>
            <tr class="cotizacion-table-header text-center">
                <th style="width: 32%;">DESCRIPCION</th>
                <th class="th-yellow" style="width: 18%;">COLOR</th>
                <th class="th-yellow" style="width: 16%;">CANTIDAD</th>
                <th class="th-yellow" style="width: 16%;">UNITARIO</th>
                <th class="th-peach" style="width: 18%;">VALOR</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $filasRendered = 0;
            foreach ($items as $item): 
                $filasRendered++;
            ?>
                <tr>
                    <td class="fw-bold"><?= e($item['producto']); ?></td>
                    <td class="text-center"><?= e($item['color'] ?? ''); ?></td>
                    <td class="text-center"><?= number_format((int)$item['cantidad'], 0, '', '.'); ?></td>
                    <td class="text-end"><?= formatMoney($item['precio_unitario']); ?></td>
                    <td class="text-end fw-bold"><?= formatMoney($item['total_linea']); ?></td>
                </tr>
            <?php endforeach; ?>

            <!-- Filas en blanco para preservar la retícula visual de la plantilla -->
            <?php for ($i = $filasRendered; $i < 6; $i++): ?>
                <tr>
                    <td style="height: 28px;">&nbsp;</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Fila 4: Totales y Condiciones -->
    <div class="row align-items-start mt-2">
        <div class="col-6">
            <!-- Condiciones al pie -->
            <div class="mt-4 pt-2">
                <p class="fw-bold mb-0" style="font-size: 0.85rem; letter-spacing: 0.3px; color: #111;">
                    <?= e($cotizacion['condiciones'] ?: 'TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA'); ?>
                </p>
            </div>
        </div>
        <div class="col-6">
            <table class="table-totales">
                <tr>
                    <td class="label-tot">TOTAL</td>
                    <td class="val-tot"><?= formatMoney($cotizacion['subtotal']); ?></td>
                </tr>
                <tr>
                    <td class="label-tot">IVA</td>
                    <td class="val-tot"><?= formatMoney($cotizacion['iva']); ?></td>
                </tr>
                <tr style="background-color: #f7f7f7;">
                    <td class="label-tot">SALDO</td>
                    <td class="val-tot text-primary"><?= formatMoney($cotizacion['total']); ?></td>
                </tr>
                <tr>
                    <td class="label-tot text-success">ABONO</td>
                    <td class="val-tot text-success"><?= formatMoney($cotizacion['abono']); ?></td>
                </tr>
                <tr>
                    <td class="label-tot text-danger">SALDO</td>
                    <td class="val-tot text-danger"><?= formatMoney($cotizacion['saldo']); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- Modal Eliminar Cotización -->
<div class="modal fade no-print" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="eliminar_cotizacion.php" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Cotización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="cotizacion_id" value="<?= (int)$cotizacion['id']; ?>">
                <p>¿Está seguro de que desea eliminar la <strong>Cotización #<?= e($cotizacion['numero']); ?></strong> del cliente <strong><?= e($cotizacion['cliente_nombre']); ?></strong>?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Esta acción no se puede deshacer y borrará el desglose de productos asociado.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger fw-bold">
                    <i class="bi bi-trash3 me-1"></i> Sí, Eliminar Cotización
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
