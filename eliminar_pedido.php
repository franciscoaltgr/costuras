<?php
/**
 * Módulo: Eliminación Transaccional de Pedido (CRUD - Delete)
 * Archivo: eliminar_pedido.php
 * 
 * Elimina de manera controlada y atómica un pedido junto con sus registros dependientes
 * (pagos y medidas), garantizando la integridad referencial y evitando estados inconsistentes.
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

// Exigir método POST para evitar eliminaciones accidentales por enlaces GET o crawlers
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Método no permitido para esta operación.');
    header('Location: index.php');
    exit;
}

$pedidoId = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT);

if (!$pedidoId) {
    setFlash('danger', 'Identificador de pedido inválido.');
    header('Location: index.php');
    exit;
}

try {
    // INICIO DE LA TRANSACCIÓN ACID
    $pdo->beginTransaction();

    // 1. Verificar existencia y bloquear la fila
    $stmtCheck = $pdo->prepare("SELECT id, descripcion_prenda FROM pedidos WHERE id = :id FOR UPDATE");
    $stmtCheck->execute([':id' => $pedidoId]);
    $pedido = $stmtCheck->fetch();

    if (!$pedido) {
        throw new Exception("El pedido #{$pedidoId} no existe o ya fue eliminado.");
    }

    // 2. Eliminar registros de la bitácora de pagos
    $stmtDelPagos = $pdo->prepare("DELETE FROM pagos WHERE pedido_id = :pedido_id");
    $stmtDelPagos->execute([':pedido_id' => $pedidoId]);

    // 3. Eliminar ficha de medidas asociadas
    $stmtDelMedidas = $pdo->prepare("DELETE FROM medidas WHERE pedido_id = :pedido_id");
    $stmtDelMedidas->execute([':pedido_id' => $pedidoId]);

    // 4. Eliminar el pedido principal
    $stmtDelPedido = $pdo->prepare("DELETE FROM pedidos WHERE id = :id");
    $stmtDelPedido->execute([':id' => $pedidoId]);

    // CONFIRMACIÓN DE LA TRANSACCIÓN (COMMIT)
    $pdo->commit();

    setFlash('success', "El pedido #{$pedidoId} ({$pedido['descripcion_prenda']}) y su historial fueron eliminados correctamente.");

} catch (Throwable $e) {
    // REVERSIÓN ATÓMICA ANTE FALLOS (ROLLBACK)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('danger', "Error al intentar eliminar el pedido: " . $e->getMessage());
}

header('Location: index.php');
exit;

