<?php
/**
 * Controlador de Generación y Descarga de PDF de Cotización
 * Archivo: generar_pdf_cotizacion.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/CotizacionPDF.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$forceDownload = isset($_GET['download']) && $_GET['download'] === '1';

if (!$id) {
    setFlash('danger', 'ID de cotización no válido.');
    header('Location: cotizaciones.php');
    exit;
}

try {
    // 1. Consultar cabecera de la cotización
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        setFlash('danger', "La cotización #{$id} no fue encontrada.");
        header('Location: cotizaciones.php');
        exit;
    }

    // 2. Consultar ítems de la cotización
    $stmtItems = $pdo->prepare("SELECT * FROM cotizacion_items WHERE cotizacion_id = :cotizacion_id ORDER BY id ASC");
    $stmtItems->execute([':cotizacion_id' => $id]);
    $items = $stmtItems->fetchAll();

    // 3. Generar PDF con FPDF
    $pdf = new CotizacionPDF($cotizacion, $items);
    $pdf->generarDocumento();

    $filename = 'Cotizacion_GrillGuard_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cotizacion['numero']) . '.pdf';

    // Salida: 'D' para forzar descarga o 'I' para visualizar en el navegador
    $dest = $forceDownload ? 'D' : 'I';
    $pdf->Output($dest, $filename);
    exit;

} catch (Throwable $e) {
    setFlash('danger', 'Error al generar PDF: ' . $e->getMessage());
    header('Location: cotizaciones.php');
    exit;
}
