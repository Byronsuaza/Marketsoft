<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit;
}

// Procesar acciones de administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'marcar_completado':
            $pedido_id = (int)$_POST['pedido_id'];
            // Verificar que el pedido existe y está pendiente
            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND estado = 'pendiente'");
            $stmt->execute([$pedido_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'completado' WHERE id = ?");
                $stmt->execute([$pedido_id]);
            }
            // Redirigir para evitar reenvío
            header("Location: finanzas.php");
            exit;
        case 'marcar_entregado':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND estado = 'pendiente'");
            $stmt->execute([$pedido_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'entregado' WHERE id = ?");
                $stmt->execute([$pedido_id]);
            }
            header("Location: finanzas.php");
            exit;
        case 'cancelar_pedido':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND estado = 'pendiente'");
            $stmt->execute([$pedido_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ?");
                $stmt->execute([$pedido_id]);
            }
            header("Location: finanzas.php");
            exit;
        case 'eliminar_pedido':
            $pedido_id = (int)$_POST['pedido_id'];
            // Verificar que el pedido esté cancelado
            $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE id = ? AND estado = 'cancelado'");
            $stmt->execute([$pedido_id]);
            if ($stmt->fetch()) {
                // Eliminar productos asociados
                $stmt = $pdo->prepare("DELETE FROM pedido_productos WHERE pedido_id = ?");
                $stmt->execute([$pedido_id]);
                // Eliminar pedido
                $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
                $stmt->execute([$pedido_id]);
            }
            header("Location: finanzas.php");
            exit;
    }
}

// Filtro de fechas para histórico
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// Ingresos totales
$ingresosTotales = $pdo->query("SELECT IFNULL(SUM(total),0) FROM pedidos WHERE estado IN ('enviado','entregado','completado')")->fetchColumn();
// Pedidos completados y pendientes
$pedidosCompletados = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('enviado','entregado','completado')")->fetchColumn();
$pedidosPendientes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
// Ingresos por mes (últimos 6 meses)
$ingresosMes = $pdo->query("
    SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, SUM(total) as ingresos
    FROM pedidos
    WHERE estado IN ('enviado','entregado','completado')
    GROUP BY mes
    ORDER BY mes DESC
    LIMIT 6
")->fetchAll();
// Últimos pedidos
$ultimosPedidos = $pdo->query("SELECT p.id, u.nombre as usuario, p.fecha, p.total, p.estado, p.direccion, p.telefono FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.fecha DESC LIMIT 10")->fetchAll();

// Utilidades y márgenes por producto
$utilidades = $pdo->query("
    SELECT pr.id, pr.nombre, pr.costo, pr.precio,
        IFNULL(SUM(pp.cantidad),0) as vendidos,
        IFNULL(SUM(pp.cantidad * (pr.precio - pr.costo)),0) as utilidad,
        IF(pr.precio > 0, ROUND((pr.precio - pr.costo)/pr.precio*100,2), 0) as margen
    FROM productos pr
    LEFT JOIN pedido_productos pp ON pp.producto_id = pr.id
    LEFT JOIN pedidos p ON pp.pedido_id = p.id AND p.estado IN ('enviado','entregado','completado')
    GROUP BY pr.id, pr.nombre, pr.costo, pr.precio
    ORDER BY utilidad DESC
")->fetchAll();

// Calcular utilidad real por producto considerando descuentos
$utilidadReal = $pdo->query('
    SELECT pr.id, pr.nombre, pr.costo, pr.precio,
        SUM(pp.cantidad) as vendidos,
        SUM((pp.precio - pr.costo) * pp.cantidad) as utilidad_real
    FROM productos pr
    LEFT JOIN pedido_productos pp ON pp.producto_id = pr.id
    LEFT JOIN pedidos p ON pp.pedido_id = p.id AND p.estado IN (\'entregado\', \'completado\')
    GROUP BY pr.id, pr.nombre, pr.costo, pr.precio
    ORDER BY utilidad_real DESC
')->fetchAll();

// Histórico financiero filtrable
$historico = $pdo->prepare("
    SELECT DATE(fecha) as dia, SUM(total) as ingresos
    FROM pedidos
    WHERE estado IN ('enviado','entregado','completado') AND fecha BETWEEN ? AND ?
    GROUP BY dia
    ORDER BY dia ASC
");
$historico->execute([$fecha_inicio, $fecha_fin]);
$historial = $historico->fetchAll();

// Consulta productos con stock bajo
$stock_bajo = $pdo->query("SELECT nombre, cantidad FROM productos WHERE cantidad < 5 ORDER BY cantidad ASC")->fetchAll();
?>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="container mt-4" style="margin-left:260px;">
<?php if (!empty($stock_bajo)): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>
            <strong>¡Atención!</strong> Los siguientes productos tienen stock bajo:
            <ul class="mb-0">
                <?php foreach ($stock_bajo as $prod): ?>
                    <li><?= htmlspecialchars($prod['nombre']) ?> (<?= $prod['cantidad'] ?> unidades)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
    <h2 class="mb-4">Gestión Financiera</h2>
    <ul class="nav nav-tabs mb-4" id="finanzasTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen" type="button" role="tab">Resumen</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="utilidades-tab" data-bs-toggle="tab" data-bs-target="#utilidades" type="button" role="tab">Utilidades y Márgenes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico" type="button" role="tab">Histórico</button>
        </li>
    </ul>
    <div class="tab-content" id="finanzasTabsContent">
        <!-- Resumen financiero -->
        <div class="tab-pane fade show active" id="resumen" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-header"><i class="fas fa-dollar-sign"></i> Ingresos Totales</div>
                        <div class="card-body">
                            <h4 class="card-title">$<?= number_format($ingresosTotales, 0, ',', '.') ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-header"><i class="fas fa-check"></i> Pedidos Completados</div>
                        <div class="card-body">
                            <h4 class="card-title"><?= $pedidosCompletados ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-header"><i class="fas fa-clock"></i> Pedidos Pendientes</div>
                        <div class="card-body">
                            <h4 class="card-title"><?= $pedidosPendientes ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-chart-line"></i> Ingresos por Mes (últimos 6 meses)
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Mes</th>
                                        <th>Ingresos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($ingresosMes) as $mes): ?>
                                    <tr>
                                        <td><?= $mes['mes'] ?></td>
                                        <td>$<?= number_format($mes['ingresos'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($ingresosMes)): ?>
                                    <tr><td colspan="2">No hay datos de ingresos.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <i class="fas fa-list"></i> Últimos Pedidos
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Estado / Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosPedidos as $pedido): ?>
                                        <tr>
                                            <td><?= $pedido['id'] ?></td>
                                            <td><?= htmlspecialchars($pedido['usuario']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></td>
                                            <td>$<?= number_format($pedido['total'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php if ($pedido['estado'] === 'pendiente'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                        <input type="hidden" name="action" value="marcar_entregado">
                                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('¿Confirmar que el pedido #<?= $pedido['id'] ?> ha sido ENTREGADO?')">
                                                            <i class="fas fa-check"></i> Entregado
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline; margin-left:4px;">
                                                        <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                        <input type="hidden" name="action" value="cancelar_pedido">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro que deseas CANCELAR el pedido #<?= $pedido['id'] ?>?')">
                                                            <i class="fas fa-times"></i> Cancelar
                                                        </button>
                                                    </form>
                                                <?php elseif ($pedido['estado'] === 'entregado'): ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Entregado</span>
                                                <?php elseif ($pedido['estado'] === 'cancelado'): ?>
                                                    <span class="text-danger"><i class="fas fa-times-circle"></i> Cancelado</span>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-info btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#modalPedido<?= $pedido['id'] ?>">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                                <?php if ($pedido['estado'] === 'cancelado'): ?>
                                                    <form method="POST" style="display:inline; margin-left:4px;">
                                                        <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                        <input type="hidden" name="action" value="eliminar_pedido">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Seguro que deseas ELIMINAR el pedido #<?= $pedido['id'] ?>? Esta acción no se puede deshacer.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($ultimosPedidos)): ?>
                                        <tr><td colspan="6">No hay pedidos registrados.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Utilidades y márgenes por producto -->
        <div class="tab-pane fade" id="utilidades" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-balance-scale"></i> Utilidades y Márgenes por Producto
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Costo</th>
                                    <th>Precio Venta</th>
                                    <th>Vendidos</th>
                                    <th>Utilidad</th>
                                    <th>Margen (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilidades as $prod): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                    <td>$<?= number_format($prod['costo'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($prod['precio'], 0, ',', '.') ?></td>
                                    <td><?= $prod['vendidos'] ?></td>
                                    <td>$<?= number_format($prod['utilidad'], 0, ',', '.') ?></td>
                                    <td><?= $prod['margen'] ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($utilidades)): ?>
                                <tr><td colspan="6">No hay datos de ventas.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Tabla de utilidad real por producto -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-coins"></i> Utilidad Real por Producto (considerando descuentos)
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Costo</th>
                                    <th>Precio Venta</th>
                                    <th>Vendidos</th>
                                    <th>Utilidad Real</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($utilidadReal as $prod): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                    <td>$<?= number_format($prod['costo'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($prod['precio'], 0, ',', '.') ?></td>
                                    <td><?= $prod['vendidos'] ?></td>
                                    <td>$<?= number_format($prod['utilidad_real'] ?? 0, 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($utilidadReal)): ?>
                                <tr><td colspan="5">No hay ventas registradas.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- Histórico financiero filtrable y gráfico -->
        <div class="tab-pane fade" id="historico" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-history"></i> Histórico de Ventas y Ganancias
                </div>
                <div class="card-body">
                    <form class="row g-3 mb-4" method="get" action="">
                        <div class="col-auto">
                            <label for="fecha_inicio" class="form-label">Desde</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
                        </div>
                        <div class="col-auto">
                            <label for="fecha_fin" class="form-label">Hasta</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
                        </div>
                        <div class="col-auto align-self-end">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                    <canvas id="graficoHistorico" height="80"></canvas>
                    <table class="table table-bordered table-sm mt-4">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?= $h['dia'] ?></td>
                                <td>$<?= number_format($h['ingresos'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($historial)): ?>
                            <tr><td colspan="2">No hay datos en el periodo seleccionado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales para ver detalles de pedidos -->
<?php foreach ($ultimosPedidos as $pedido): ?>
<div class="modal fade" id="modalPedido<?= $pedido['id'] ?>" tabindex="-1" aria-labelledby="modalPedidoLabel<?= $pedido['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPedidoLabel<?= $pedido['id'] ?>">
                    <i class="fas fa-shopping-bag me-2"></i>Detalles del Pedido #<?= $pedido['id'] ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Información del Cliente</h6>
                        <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['usuario']) ?></p>
                        <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? '-') ?></p>
                        <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Información del Pedido</h6>
                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></p>
                        <p><strong>Estado:</strong> 
                            <span class="badge bg-<?= $pedido['estado'] === 'completado' ? 'success' : ($pedido['estado'] === 'pendiente' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($pedido['estado']) ?>
                            </span>
                        </p>
                        <p><strong>Total:</strong> <span class="fw-bold text-primary">$<?= number_format($pedido['total'], 0, ',', '.') ?></span></p>
                    </div>
                </div>
                <hr>
                <h6>Productos del Pedido</h6>
                <?php
                    $stmtProd = $pdo->prepare("SELECT pp.*, pr.nombre, pr.imagen, pr.precio as precio_original FROM pedido_productos pp JOIN productos pr ON pp.producto_id = pr.id WHERE pp.pedido_id = ?");
                    $stmtProd->execute([$pedido['id']]);
                    $productos = $stmtProd->fetchAll();
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../<?= htmlspecialchars($prod['imagen']) ?>" alt="img" style="width:40px;height:40px;object-fit:cover;margin-right:10px;">
                                        <span><?= htmlspecialchars($prod['nombre']) ?></span>
                                    </div>
                                </td>
                                <td><?= $prod['cantidad'] ?></td>
                                <td>$<?= number_format($prod['precio'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($prod['precio'] * $prod['cantidad'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gráfico histórico
const ctx = document.getElementById('graficoHistorico');
if (ctx) {
    const labels = <?= json_encode(array_column($historial, 'dia')) ?>;
    const data = <?= json_encode(array_column($historial, 'ingresos')) ?>;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ingresos',
                data: data,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52,152,219,0.2)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
</script>
</body> 