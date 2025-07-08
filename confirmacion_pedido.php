<?php
session_start();
require_once 'includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php");
    exit;
}

// Verificar si se proporcionó un ID de pedido
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// Obtener información del pedido
$stmt = $pdo->prepare("
    SELECT p.*, u.nombre as usuario_nombre, u.correo as usuario_email 
    FROM pedidos p 
    JOIN usuarios u ON p.usuario_id = u.id 
    WHERE p.id = ? AND p.usuario_id = ?
");
$stmt->execute([$pedido_id, $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header("Location: index.php");
    exit;
}

// Obtener productos del pedido
$stmt = $pdo->prepare("
    SELECT pp.*, pr.nombre, pr.imagen, pr.precio as precio_original
    FROM pedido_productos pp 
    JOIN productos pr ON pp.producto_id = pr.id 
    WHERE pp.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$productos_pedido = $stmt->fetchAll();

// Calcular total de descuento aplicado
$total_descuento = 0;
foreach ($productos_pedido as &$prod) {
    $descuento_unitario = max(0, $prod['precio_original'] - $prod['precio']);
    $prod['descuento_unitario'] = $descuento_unitario;
    $total_descuento += $descuento_unitario * $prod['cantidad'];
}
unset($prod);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido - MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .success-animation { animation: bounceIn 1s ease-in-out; }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .order-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
        }
        .order-item img {
            max-width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-basket me-2"></i>MARKETSOFT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#productos">Productos</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link cart-icon" href="carrito.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count">0</span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Panel Admin</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="login/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card success-animation">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h2 class="text-success mb-3">¡Pedido Confirmado!</h2>
                        <p class="lead">Tu pedido ha sido procesado exitosamente.</p>
                        <p class="text-muted">Número de pedido: <strong>#<?= $pedido_id ?></strong></p>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Detalles del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Información del Cliente</h6>
                                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['usuario_nombre']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($pedido['usuario_email']) ?></p>
                                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono']) ?></p>
                                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Información del Pedido</h6>
                                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></p>
                                <p><strong>Estado:</strong> 
                                    <span class="badge bg-warning"><?= ucfirst($pedido['estado']) ?></span>
                                </p>
                                <p><strong>Método de Pago:</strong> <?= ucfirst($pedido['metodo_pago']) ?></p>
                                <p><strong>Total:</strong> <span class="fw-bold text-primary">$<?= number_format($pedido['total'], 0, ',', '.') ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Productos del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($productos_pedido as $producto): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" class="img-fluid">
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-1"><?= htmlspecialchars($producto['nombre']) ?></h6>
                                        <p class="text-muted mb-0">Cantidad: <?= $producto['cantidad'] ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <?php if ($producto['descuento_unitario'] > 0): ?>
                                            <span class="text-decoration-line-through text-muted me-2">$<?= number_format($producto['precio_original'], 0, ',', '.') ?></span>
                                            <span class="fw-bold text-danger">$<?= number_format($producto['precio'], 0, ',', '.') ?> c/u</span>
                                            <div class="text-success small">Descuento: -$<?= number_format($producto['descuento_unitario'], 0, ',', '.') ?> c/u</div>
                                        <?php else: ?>
                                            <span class="text-muted">$<?= number_format($producto['precio'], 0, ',', '.') ?> c/u</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <span class="fw-bold">$<?= number_format($producto['precio'] * $producto['cantidad'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Descuento aplicado:</span>
                    <span class="text-success">-$<?= number_format($total_descuento, 0, ',', '.') ?></span>
                </div>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Próximos Pasos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <i class="fas fa-phone text-primary mb-2" style="font-size: 2rem;"></i>
                                <h6>Confirmación</h6>
                                <p class="small text-muted">Recibirás una llamada para confirmar tu pedido</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-truck text-primary mb-2" style="font-size: 2rem;"></i>
                                <h6>Preparación</h6>
                                <p class="small text-muted">Tu pedido será preparado y empacado</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-home text-primary mb-2" style="font-size: 2rem;"></i>
                                <h6>Entrega</h6>
                                <p class="small text-muted">Te entregaremos en la dirección especificada</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Volver al Inicio
                    </a>
                    <a href="carrito.php" class="btn btn-outline-secondary">
                        <i class="fas fa-shopping-cart"></i> Hacer Otro Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 