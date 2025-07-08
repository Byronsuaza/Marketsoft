<?php
session_start();
require_once 'includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php");
    exit;
}

// Verificar si hay productos en el carrito
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    header("Location: carrito.php");
    exit;
}

// Obtener descuentos activos
$descuentos = [];
$stmt = $pdo->query("SELECT * FROM descuentos WHERE valido = 1");
while ($row = $stmt->fetch()) {
    $descuentos[$row['producto_id']] = $row;
}

// Calcular totales con descuento
$total_items = 0;
$total_precio = 0;
$total_descuento = 0;
$carrito_con_descuento = [];
foreach ($_SESSION['carrito'] as $item) {
    $precio = $item['precio'];
    $descuento_aplicado = 0;
    if (isset($descuentos[$item['id']])) {
        $descuento = $descuentos[$item['id']];
        $descuento_aplicado = $precio * $descuento['valor']/100;
        $precio = $precio - $descuento_aplicado;
    }
    $item['precio_final'] = $precio;
    $item['descuento_aplicado'] = $descuento_aplicado;
    $carrito_con_descuento[] = $item;
    $total_items += $item['cantidad'];
    $total_precio += $precio * $item['cantidad'];
    $total_descuento += $descuento_aplicado * $item['cantidad'];
}

// Obtener datos del usuario para autocompletar
$stmt = $pdo->prepare("SELECT direccion, telefono FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

// Procesar el pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    
    $error = '';
    
    // Validaciones
    if (empty($direccion)) {
        $error = 'La dirección es obligatoria';
    } elseif (empty($telefono)) {
        $error = 'El teléfono es obligatorio';
    } elseif (empty($metodo_pago)) {
        $error = 'Debe seleccionar un método de pago';
    }
    
    if (!$error) {
        try {
            // Guardar dirección y teléfono en el perfil si han cambiado o están vacíos
            $stmt = $pdo->prepare("UPDATE usuarios SET direccion = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$direccion, $telefono, $_SESSION['user_id']]);

            // Iniciar transacción
            $pdo->beginTransaction();
            
            // Verificar stock antes de procesar
            foreach ($carrito_con_descuento as $item) {
                $stmt = $pdo->prepare("SELECT cantidad FROM productos WHERE id = ?");
                $stmt->execute([$item['id']]);
                $stock_actual = $stmt->fetchColumn();
                
                if ($stock_actual < $item['cantidad']) {
                    throw new Exception("Stock insuficiente para: " . $item['nombre']);
                }
            }
            
            $total_final = $total_precio;
            
            // Crear pedido
            $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, fecha, total, direccion, telefono, metodo_pago, estado) VALUES (?, NOW(), ?, ?, ?, ?, 'pendiente')");
            $stmt->execute([$_SESSION['user_id'], $total_final, $direccion, $telefono, $metodo_pago]);
            $pedido_id = $pdo->lastInsertId();
            
            // Agregar productos al pedido
            foreach ($carrito_con_descuento as $item) {
                // Insertar producto del pedido
                $stmt = $pdo->prepare("INSERT INTO pedido_productos (pedido_id, producto_id, cantidad, precio) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pedido_id, $item['id'], $item['cantidad'], $item['precio_final']]);
                
                // Actualizar stock
                $stmt = $pdo->prepare("UPDATE productos SET cantidad = cantidad - ? WHERE id = ?");
                $stmt->execute([$item['cantidad'], $item['id']]);
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            // Limpiar carrito
            $_SESSION['carrito'] = [];
            
            // Redirigir a confirmación
            header("Location: confirmacion_pedido.php?id=" . $pedido_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

if (!isset($total_descuento)) $total_descuento = 0;
if (!isset($total_final)) $total_final = $total_precio;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .checkout-item {
            border-bottom: 1px solid #e9ecef;
            padding: 10px 0;
        }
        .checkout-item:last-child {
            border-bottom: none;
        }
        .checkout-item img {
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
                            <span class="cart-count"><?= $total_items ?></span>
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
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Información de Pago</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="direccion" class="form-label">Dirección de Entrega *</label>
                                    <textarea class="form-control" id="direccion" name="direccion" rows="3" required><?= htmlspecialchars($_POST['direccion'] ?? $usuario['direccion'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" required value="<?= htmlspecialchars($_POST['telefono'] ?? $usuario['telefono'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="metodo_pago" class="form-label">Método de Pago *</label>
                                    <select class="form-select" id="metodo_pago" name="metodo_pago" required>
                                        <option value="">Selecciona un método</option>
                                        <option value="efectivo" <?= ($_POST['metodo_pago'] ?? '') === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                        <option value="tarjeta" <?= ($_POST['metodo_pago'] ?? '') === 'tarjeta' ? 'selected' : '' ?>>Tarjeta de Crédito/Débito</option>
                                        <option value="transferencia" <?= ($_POST['metodo_pago'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferencia Bancaria</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="carrito.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver al Carrito
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Confirmar Pedido
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($carrito_con_descuento as $item): ?>
                            <div class="checkout-item">
                                <div class="row align-items-center">
                                    <div class="col-3">
                                        <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" class="img-fluid">
                                    </div>
                                    <div class="col-6">
                                        <h6 class="mb-1"><?= htmlspecialchars($item['nombre']) ?></h6>
                                        <small class="text-muted">Cantidad: <?= $item['cantidad'] ?></small>
                                    </div>
                                    <div class="col-3 text-end">
                                        <span class="fw-bold">$<?= number_format($item['precio_final'] * $item['cantidad'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($total_precio, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Descuento:</span>
                            <span class="text-success">-$<?= number_format($total_descuento, 0, ',', '.') ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong class="text-primary">$<?= number_format($total_final, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 