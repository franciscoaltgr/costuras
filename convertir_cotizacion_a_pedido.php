<?php
/**
 * Módulo: Convertir Cotización en Pedido de Confección
 * Archivo: convertir_cotizacion_a_pedido.php
 */

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/helpers.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    setFlash('danger', 'ID de cotización no válido.');
    header('Location: cotizaciones.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        setFlash('danger', "La cotización #{$id} no fue encontrada.");
        header('Location: cotizaciones.php');
        exit;
    }

    $stmtItems = $pdo->prepare("SELECT * FROM cotizacion_items WHERE cotizacion_id = :id");
    $stmtItems->execute([':id' => $id]);
    $items = $stmtItems->fetchAll();

    // Procesar envío de confirmación
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? date('Y-m-d', strtotime('+15 days')));
        $descripcionPrenda = trim($_POST['descripcion_prenda'] ?? '');

        if (empty($descripcionPrenda)) {
            $descParts = [];
            foreach ($items as $it) {
                $descParts[] = "{$it['cantidad']}x {$it['producto']}" . (!empty($it['color']) ? " ({$it['color']})" : "");
            }
            $descripcionPrenda = implode(', ', $descParts);
        }

        // Abono REAL recibido al momento de la confirmación (puede diferir del proyectado en la cotización).
        $costoTotal = (int)$cotizacion['total'];
        $abonoRaw   = str_replace(['.', ','], ['', '.'], trim($_POST['abono_real'] ?? '0'));
        $abono      = (int)round((float)$abonoRaw);
        if ($abono < 0) { $abono = 0; }
        if ($abono > $costoTotal) {
            throw new Exception("El abono real (" . formatMoney($abono) . ") no puede superar el total del pedido (" . formatMoney($costoTotal) . ").");
        }

        $pdo->beginTransaction();

        // 1. Obtener o crear el cliente
        $clienteId = $cotizacion['cliente_id'];
        if (!$clienteId) {
            // Verificar si ya existe cliente con mismo nombre
            $stmtBuscarCli = $pdo->prepare("SELECT id FROM clientes WHERE nombre = :nombre LIMIT 1");
            $stmtBuscarCli->execute([':nombre' => $cotizacion['cliente_nombre']]);
            $cliRow = $stmtBuscarCli->fetch();

            if ($cliRow) {
                $clienteId = $cliRow['id'];
            } else {
                $stmtNewCli = $pdo->prepare("INSERT INTO clientes (nombre, telefono, email) VALUES (:nombre, :telefono, :email)");
                $stmtNewCli->execute([
                    ':nombre'   => $cotizacion['cliente_nombre'],
                    ':telefono' => $cotizacion['cliente_telefono'] ?: null,
                    ':email'    => $cotizacion['cliente_email'] ?: null
                ]);
                $clienteId = (int)$pdo->lastInsertId();
            }
        }

        // 2. Crear el pedido (usa el abono REAL recibido, no el proyectado)
        $stmtPedido = $pdo->prepare("
            INSERT INTO pedidos (cliente_id, descripcion_prenda, estado, fecha_entrega, costo_total, abono)
            VALUES (:cliente_id, :descripcion_prenda, 'En Proceso', :fecha_entrega, :costo_total, :abono)
        ");
        $stmtPedido->execute([
            ':cliente_id'          => $clienteId,
            ':descripcion_prenda'  => $descripcionPrenda,
            ':fecha_entrega'       => $fechaEntrega,
            ':costo_total'         => $costoTotal,
            ':abono'               => $abono
        ]);

        $pedidoId = (int)$pdo->lastInsertId();

        // 3. Crear medidas iniciales
        $stmtMedidas = $pdo->prepare("
            INSERT INTO medidas (pedido_id, observaciones)
            VALUES (:pedido_id, :observaciones)
        ");
        $stmtMedidas->execute([
            ':pedido_id'     => $pedidoId,
            ':observaciones' => "Generado desde Cotización #" . $cotizacion['numero']
        ]);

        // 4. Registrar abono en historial de pagos si es mayor a cero
        if ($abono > 0) {
            $stmtPago = $pdo->prepare("INSERT INTO pagos (pedido_id, monto) VALUES (:pedido_id, :monto)");
            $stmtPago->execute([
                ':pedido_id' => $pedidoId,
                ':monto'     => $abono
            ]);
        }

        // 5. Actualizar estado de la cotización
        $stmtUpdateCot = $pdo->prepare("UPDATE cotizaciones SET estado = 'Convertida en Pedido' WHERE id = :id");
        $stmtUpdateCot->execute([':id' => $id]);

        $pdo->commit();

        setFlash('success', "¡Cotización #{$cotizacion['numero']} convertida exitosamente en el Pedido #{$pedidoId}!");
        header("Location: ver_pedido.php?id={$pedidoId}");
        exit;
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('danger', 'Error al procesar la conversión: ' . $e->getMessage());
    header('Location: cotizaciones.php');
    exit;
}

$pageTitle = "Convertir Cotización #" . $cotizacion['numero'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-scissors text-success me-2"></i>Convertir Cotización a Pedido de Confección
                </h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">
                    Esta acción ingresará formalmente la <strong>Cotización #<?= e($cotizacion['numero']); ?></strong> a la cola de trabajo del taller de costura, creando el pedido y registrando el abono acordado.
                </p>

                <?php
                $totalCot        = (int)$cotizacion['total'];
                $abonoProyectado = (int)$cotizacion['abono'];
                $pctProyectado   = $totalCot > 0 ? (int)round($abonoProyectado / $totalCot * 100) : 0;
                ?>

                <div class="alert alert-light border mb-4">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <strong>Cliente:</strong> <?= e($cotizacion['cliente_nombre']); ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Total Pedido:</strong> <?= formatMoney($totalCot); ?>
                        </div>
                        <div class="col-sm-6">
                            <strong>Abono proyectado en cotización:</strong>
                            <?= formatMoney($abonoProyectado); ?> (<?= $pctProyectado; ?>%)
                        </div>
                        <div class="col-sm-6">
                            <strong>Saldo proyectado:</strong> <?= formatMoney($cotizacion['saldo']); ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Descripción del Trabajo en Taller <span class="text-danger">*</span></label>
                        <?php
                        $descDefault = [];
                        foreach ($items as $it) {
                            $descDefault[] = "{$it['cantidad']}x {$it['producto']}" . (!empty($it['color']) ? " ({$it['color']})" : "");
                        }
                        ?>
                        <textarea name="descripcion_prenda" class="form-control" rows="3" required><?= e(implode(', ', $descDefault)); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Fecha Prometida de Entrega <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_entrega" class="form-control" required value="<?= date('Y-m-d', strtotime('+15 days')); ?>">
                        <small class="text-muted">Fecha estimada para finalizar la confección.</small>
                    </div>

                    <!-- Abono REAL recibido al confirmar: puede diferir del proyectado -->
                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold text-success mb-1">
                            <i class="bi bi-cash-coin me-1"></i> ¿Cuánto abonó realmente el cliente al confirmar?
                        </label>
                        <div class="d-flex gap-1 flex-wrap mb-2" role="group" aria-label="Preselección de abono real">
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-amount="0">Sin abono aún</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="30">30%</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="50">50%</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-amount="<?= $abonoProyectado; ?>">
                                Según cotización (<?= formatMoney($abonoProyectado); ?>)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-abono" data-pct="100">Pagó completo</button>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number"
                                   name="abono_real"
                                   id="inpAbonoReal"
                                   class="form-control fw-bold"
                                   min="0"
                                   max="<?= $totalCot; ?>"
                                   step="1"
                                   value="<?= $abonoProyectado; ?>"
                                   data-total="<?= $totalCot; ?>"
                                   oninput="recalcularSaldoReal()" required>
                            <span class="input-group-text" id="lblPctReal" style="min-width: 70px; justify-content: center;"><?= $pctProyectado; ?>%</span>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Prellenado con el abono proyectado. Ajústalo si la clienta pagó un monto distinto (o aún no ha pagado).
                        </small>
                        <div class="mt-2 small">
                            Saldo pendiente a cobrar al entregar:
                            <strong id="lblSaldoReal" class="text-danger"><?= formatMoney($totalCot - $abonoProyectado); ?></strong>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="ver_cotizacion.php?id=<?= (int)$cotizacion['id']; ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Confirmar e Ingresar Pedido
                        </button>
                    </div>
                </form>

                <script>
                function formatCLP(val) {
                    return '$ ' + Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
                function recalcularSaldoReal() {
                    const inp = document.getElementById('inpAbonoReal');
                    const total = parseInt(inp.dataset.total, 10) || 0;
                    let abono = Math.max(0, Math.round(parseFloat(inp.value) || 0));
                    if (abono > total) { abono = total; inp.value = abono; }
                    const saldo = total - abono;
                    const pct = total > 0 ? Math.round((abono / total) * 100) : 0;
                    document.getElementById('lblSaldoReal').textContent = formatCLP(saldo);
                    document.getElementById('lblPctReal').textContent = pct + '%';
                }
                document.addEventListener('DOMContentLoaded', function() {
                    const inp = document.getElementById('inpAbonoReal');
                    const total = parseInt(inp.dataset.total, 10) || 0;
                    document.querySelectorAll('.quick-abono').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (this.dataset.amount !== undefined) {
                                inp.value = parseInt(this.dataset.amount, 10) || 0;
                            } else if (this.dataset.pct !== undefined) {
                                const pct = parseInt(this.dataset.pct, 10) || 0;
                                inp.value = Math.round(total * (pct / 100));
                            }
                            recalcularSaldoReal();
                        });
                    });
                    recalcularSaldoReal();
                });
                </script>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
