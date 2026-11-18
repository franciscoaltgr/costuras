<?php
/**
 * Módulo: Eliminación de Cotización
 * Archivo: eliminar_cotizacion.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Método no permitido.');
    header('Location: cotizaciones.php');
    exit;
}

$cotizacionId = filter_var($_POST['cotizacion_id'] ?? null, FILTER_VALIDATE_INT);

if (!$cotizacionId) {
    setFlash('danger', 'ID de cotización inválido.');
    header('Location: cotizaciones.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT numero, cliente_nombre FROM cotizaciones WHERE id = :id FOR UPDATE");
    $stmtCheck->execute([':id' => $cotizacionId]);
    $cot = $stmtCheck->fetch();

    if (!$cot) {
        throw new Exception("La cotización no existe o ya fue eliminada.");
    }

    // Los ítems se eliminan automáticamente por ON DELETE CASCADE en la BD, pero aseguramos la eliminación:
    $stmtDelItems = $pdo->prepare("DELETE FROM cotizacion_items WHERE cotizacion_id = :id");
    $stmtDelItems->execute([':id' => $cotizacionId]);

    $stmtDelCot = $pdo->prepare("DELETE FROM cotizaciones WHERE id = :id");
    $stmtDelCot->execute([':id' => $cotizacionId]);

    $pdo->commit();

    setFlash('success', "La Cotización #{$cot['numero']} de {$cot['cliente_nombre']} ha sido eliminada correctamente.");

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('danger', 'Error al eliminar la cotización: ' . $e->getMessage());
}

header('Location: cotizaciones.php');
exit;
