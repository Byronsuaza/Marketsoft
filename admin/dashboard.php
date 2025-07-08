<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit;
}

// Métricas principales
$totalProductos = $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$bajoStock = $pdo->query("SELECT COUNT(*) FROM productos WHERE cantidad < 10")->fetchColumn();
$descuentosActivos = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT producto_id
        FROM descuentos d1
        WHERE d1.id = (
            SELECT MAX(d2.id) FROM descuentos d2 WHERE d2.producto_id = d1.producto_id
        )
        AND d1.valido = 1
    ) AS sub
")->fetchColumn();
$totalPedidos = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('enviado','entregado')")->fetchColumn();
$ingresosTotales = $pdo->query("SELECT IFNULL(SUM(total),0) FROM pedidos WHERE estado IN ('enviado','entregado')")->fetchColumn();

// Últimos pedidos
$ultimosPedidos = $pdo->query("SELECT p.id, u.nombre as usuario, p.fecha, p.total, p.estado FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id ORDER BY p.fecha DESC LIMIT 5")->fetchAll();

// Productos más vendidos
$masVendidos = $pdo->query("
    SELECT pr.nombre, SUM(pp.cantidad) as total_vendidos
    FROM pedido_productos pp
    JOIN productos pr ON pp.producto_id = pr.id
    JOIN pedidos p ON pp.pedido_id = p.id
    WHERE p.estado IN ('enviado','entregado')
    GROUP BY pr.id, pr.nombre
    ORDER BY total_vendidos DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
        }
        .sidebar {
            background-color: var(--primary-color);
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: white;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: var(--secondary-color);
        }
        .card-counter {
            box-shadow: 2px 2px 10px #DADADA;
            margin: 5px;
            padding: 20px;
            border-radius: 5px;
        }
        .card-counter.primary {
            background-color: var(--secondary-color);
            color: white;
        }
        .card-counter.danger {
            background-color: var(--accent-color);
            color: white;
        }
        .card-counter.success {
            background-color: #2ecc71;
            color: white;
        }
        .card-counter.info {
            background-color: #1abc9c;
            color: white;
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="container mt-4" style="margin-left:260px;">
    <div class="row">
        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-4">Panel de Administración</h2>
                <a href="../index.php" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-home"></i> Ver tienda
                </a>
            </div>
            
            <!-- Resumen Rápido -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card-counter primary">
                        <i class="fas fa-box-open fa-2x"></i>
                        <span class="count-numbers h3"><?= $totalProductos ?></span>
                        <span class="count-name">Productos</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter danger">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                        <span class="count-numbers h3"><?= $bajoStock ?></span>
                        <span class="count-name">Bajo stock</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter info">
                        <i class="fas fa-tags fa-2x"></i>
                        <span class="count-numbers h3"><?= $descuentosActivos ?></span>
                        <span class="count-name">Descuentos activos</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter success">
                        <i class="fas fa-shopping-cart fa-2x"></i>
                        <span class="count-numbers h3"><?= $totalPedidos ?></span>
                        <span class="count-name">Pedidos completados</span>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-dollar-sign me-2"></i> Ingresos Totales
                        </div>
                        <div class="card-body">
                            <h4 class="mb-0">$<?= number_format($ingresosTotales, 0, ',', '.') ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-star me-2"></i> Productos más vendidos
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($masVendidos as $prod): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($prod['nombre']) ?>
                                        <span class="badge bg-success rounded-pill"><?= $prod['total_vendidos'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($masVendidos)): ?>
                                    <li class="list-group-item">No hay ventas registradas.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-list me-2"></i> Últimos pedidos
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
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosPedidos as $pedido): ?>
                                        <tr>
                                            <td><?= $pedido['id'] ?></td>
                                            <td><?= htmlspecialchars($pedido['usuario']) ?></td>
                                            <td><?= $pedido['fecha'] ?></td>
                                            <td>$<?= number_format($pedido['total'], 0, ',', '.') ?></td>
                                            <td><?= ucfirst($pedido['estado']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($ultimosPedidos)): ?>
                                        <tr><td colspan="5">No hay pedidos registrados.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alertas de Stock Bajo -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <i class="fas fa-exclamation-circle me-2"></i> Productos con bajo stock
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Stock Actual</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->query("SELECT id, nombre, cantidad FROM productos WHERE cantidad < 10 ORDER BY cantidad ASC LIMIT 5");
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<tr>
                                                <td>".htmlspecialchars($row['nombre'])."</td>
                                                <td>{$row['cantidad']}</td>
                                                <td>
                                                    <a href='gestion_inventario.php?edit={$row['id']}' class='btn btn-sm btn-primary'>
                                                        <i class='fas fa-edit'></i> Reabastecer
                                                    </a>
                                                </td>
                                            </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>