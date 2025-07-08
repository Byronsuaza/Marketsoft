<?php
session_start();
require_once 'includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php");
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Procesar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $producto_id = $_POST['producto_id'] ?? 0;
    
    switch ($action) {
        case 'agregar':
            $cantidad = $_POST['cantidad'] ?? 1;
            agregarAlCarrito($producto_id, $cantidad);
            break;
        case 'actualizar':
            $cantidad = $_POST['cantidad'] ?? 1;
            actualizarCantidad($producto_id, $cantidad);
            break;
        case 'eliminar':
            eliminarDelCarrito($producto_id);
            break;
        case 'limpiar':
            $_SESSION['carrito'] = [];
            break;

    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: carrito.php");
    exit;
}

// Función para agregar producto al carrito
function agregarAlCarrito($producto_id, $cantidad) {
    global $pdo;
    
    // Verificar si el producto existe y tiene stock
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND cantidad > 0");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    
    if ($producto) {
        // Verificar si ya está en el carrito
        if (isset($_SESSION['carrito'][$producto_id])) {
            $_SESSION['carrito'][$producto_id]['cantidad'] += $cantidad;
        } else {
            $_SESSION['carrito'][$producto_id] = [
                'id' => $producto['id'],
                'nombre' => $producto['nombre'],
                'precio' => $producto['precio'],
                'imagen' => $producto['imagen'],
                'cantidad' => $cantidad,
                'stock_disponible' => $producto['cantidad']
            ];
        }
        
        // Verificar que no exceda el stock
        if ($_SESSION['carrito'][$producto_id]['cantidad'] > $producto['cantidad']) {
            $_SESSION['carrito'][$producto_id]['cantidad'] = $producto['cantidad'];
        }
    }
}

// Función para actualizar cantidad
function actualizarCantidad($producto_id, $cantidad) {
    if (isset($_SESSION['carrito'][$producto_id])) {
        if ($cantidad <= 0) {
            eliminarDelCarrito($producto_id);
        } else {
            $_SESSION['carrito'][$producto_id]['cantidad'] = $cantidad;
        }
    }
}

// Función para eliminar del carrito
function eliminarDelCarrito($producto_id) {
    if (isset($_SESSION['carrito'][$producto_id])) {
        unset($_SESSION['carrito'][$producto_id]);
    }
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

// Consulta historial de pedidos del usuario
$historialPedidos = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY fecha DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $historialPedidos = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .cart-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        .cart-item img {
            max-width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .quantity-btn:hover {
            background: #f8f9fa;
        }
        .empty-cart {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-cart i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-shopping-cart me-2"></i>Carrito de Compras</h2>
                    <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalHistorialPedidos">
                        <i class="fas fa-history"></i> Ver historial de pedidos
                    </button>
                    <?php if (!empty($_SESSION['carrito'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="limpiar">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Estás seguro de que quieres vaciar el carrito?')">
                                <i class="fas fa-trash"></i> Vaciar Carrito
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($_SESSION['carrito'])): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Tu carrito está vacío</h3>
                        <p class="text-muted">Agrega algunos productos para comenzar a comprar</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Continuar Comprando
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($carrito_con_descuento as $item): ?>
                        <div class="cart-item">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" class="img-fluid">
                                </div>
                                <div class="col-md-4">
                                    <h5><?= htmlspecialchars($item['nombre']) ?></h5>
                                    <p class="text-muted mb-0">Stock disponible: <?= $item['stock_disponible'] ?></p>
                                </div>
                                <div class="col-md-2">
                                    <span class="fw-bold">$<?= number_format($item['precio_final'], 0, ',', '.') ?></span>
                                    <?php if ($item['descuento_aplicado'] > 0): ?>
                                        <div class="text-success small">Descuento: -$<?= number_format($item['descuento_aplicado'], 0, ',', '.') ?> c/u</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-2">
                                    <div class="quantity-control">
                                        <button class="quantity-btn" onclick="cambiarCantidad(<?= $item['id'] ?>, -1)">-</button>
                                        <span class="fw-bold"><?= $item['cantidad'] ?></span>
                                        <button class="quantity-btn" onclick="cambiarCantidad(<?= $item['id'] ?>, 1)">+</button>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <span class="fw-bold">$<?= number_format($item['precio_final'] * $item['cantidad'], 0, ',', '.') ?></span>
                                </div>
                                <div class="col-md-1">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="producto_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este producto?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Resumen del Pedido</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Productos (<?= $total_items ?>):</span>
                            <span>$<?= number_format($total_precio, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Descuento total:</span>
                            <span class="text-success">-$<?= number_format($total_descuento, 0, ',', '.') ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong class="text-primary">$<?= number_format($total_precio - $total_descuento, 0, ',', '.') ?></strong>
                        </div>
                        
                        <?php if (!empty($_SESSION['carrito'])): ?>
                            <a href="checkout.php" class="btn btn-primary w-100">
                                <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="fas fa-arrow-left me-2"></i>Seguir Comprando
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Historial de Pedidos -->
    <div class="modal fade" id="modalHistorialPedidos" tabindex="-1" aria-labelledby="modalHistorialPedidosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHistorialPedidosLabel"><i class="fas fa-history me-2"></i>Historial de Pedidos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($historialPedidos)): ?>
                        <div class="alert alert-info">No tienes pedidos registrados aún.</div>
                    <?php else: ?>
                        <div class="accordion" id="accordionPedidos">
                            <?php foreach ($historialPedidos as $i => $pedido): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?= $pedido['id'] ?>">
                                        <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $pedido['id'] ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $pedido['id'] ?>">
                                            Pedido #<?= $pedido['id'] ?> | <?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?> | <span class="badge bg-<?= $pedido['estado'] === 'completado' ? 'success' : ($pedido['estado'] === 'pendiente' ? 'warning' : 'secondary') ?> ms-2"><?= ucfirst($pedido['estado']) ?></span> | Total: <strong>$<?= number_format($pedido['total'], 0, ',', '.') ?></strong>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $pedido['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" aria-labelledby="heading<?= $pedido['id'] ?>" data-bs-parent="#accordionPedidos">
                                        <div class="accordion-body">
                                            <strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? '-') ?><br>
                                            <strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? '-') ?><br>
                                            <strong>Método de pago:</strong> <?= htmlspecialchars($pedido['metodo_pago'] ?? '-') ?><br>
                                            <hr>
                                            <strong>Productos:</strong>
                                            <ul class="list-group mb-2">
                                                <?php
                                                    $stmtProd = $pdo->prepare("SELECT pp.*, pr.nombre, pr.imagen FROM pedido_productos pp JOIN productos pr ON pp.producto_id = pr.id WHERE pp.pedido_id = ?");
                                                    $stmtProd->execute([$pedido['id']]);
                                                    $productos = $stmtProd->fetchAll();
                                                    foreach ($productos as $prod):
                                                ?>
                                                <li class="list-group-item d-flex align-items-center">
                                                    <img src="<?= htmlspecialchars($prod['imagen']) ?>" alt="img" style="width:40px;height:40px;object-fit:cover;margin-right:10px;">
                                                    <div class="flex-grow-1">
                                                        <strong><?= htmlspecialchars($prod['nombre']) ?></strong> <span class="text-muted">x<?= $prod['cantidad'] ?></span>
                                                    </div>
                                                    <span>$<?= number_format($prod['precio'], 0, ',', '.') ?></span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mb-2">
                                                <strong>Total del pedido: $<?= number_format($pedido['total'], 0, ',', '.') ?></strong>
                                            </div>
                                            <?php if ($pedido['estado'] === 'completado'): ?>
                                                <div class="alert alert-success text-center mb-0">¡Pedido completado!</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cambiarCantidad(productoId, cambio) {
            // Buscar el span que muestra la cantidad actual
            const cartItem = Array.from(document.querySelectorAll('.cart-item')).find(item =>
                item.querySelector('.quantity-control button') &&
                item.querySelector('.quantity-control button').onclick.toString().includes(productoId)
            );
            let cantidadActual = 1;
            if (cartItem) {
                const cantidadSpan = cartItem.querySelector('.quantity-control span.fw-bold');
                if (cantidadSpan) {
                    cantidadActual = parseInt(cantidadSpan.textContent) || 1;
                }
            }
            let nuevaCantidad = cantidadActual + cambio;
            if (nuevaCantidad < 1) nuevaCantidad = 1;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="actualizar">
                <input type="hidden" name="producto_id" value="${productoId}">
                <input type="hidden" name="cantidad" value="${nuevaCantidad}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html> 