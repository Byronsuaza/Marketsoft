<?php
session_start();
include 'includes/db.php';
// Obtener descuentos activos
$descuentos = [];
$stmt = $pdo->query("SELECT * FROM descuentos WHERE valido = 1");
while ($row = $stmt->fetch()) {
    $descuentos[$row['producto_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MARKETSOFT - Supermercado Online</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: #f7f9fb;
        }
        .hero-section {
            background: linear-gradient(120deg, #3498db 60%, #2ecc71 100%);
            color: #fff;
            padding: 60px 0 80px 0;
            background-image: url('img/supermercado.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(52,152,219,0.7);
            z-index: 1;
        }
        .hero-section > .container {
            position: relative;
            z-index: 2;
        }
        .search-box {
            display: flex;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border-radius: 40px;
            overflow: hidden;
        }
        .search-box input {
            border: none;
            border-radius: 40px 0 0 40px;
            padding: 18px 24px;
            font-size: 1.2rem;
        }
        .search-box button {
            background: #2ecc71;
            color: #fff;
            border: none;
            padding: 0 28px;
            font-size: 1.3rem;
            border-radius: 0 40px 40px 0;
            transition: background 0.2s;
        }
        .search-box button:hover {
            background: #27ae60;
        }
        .category-card {
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(44,62,80,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            background: #fff;
            width: 120px; height: 120px;
            margin: 0 auto 10px auto;
            display: flex; align-items: center; justify-content: center;
        }
        .category-card img {
            width: 100px; height: 100px; object-fit: cover;
        }
        .category-card:hover {
            transform: scale(1.07) translateY(-4px);
            box-shadow: 0 8px 24px rgba(52,152,219,0.18);
        }
        .product-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(44,62,80,0.10);
            transition: transform 0.18s, box-shadow 0.18s;
            overflow: hidden;
            position: relative;
            border: none;
        }
        .product-card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 8px 32px rgba(52,152,219,0.18);
        }
        .product-img {
            border-radius: 18px 18px 0 0;
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .badge-discount {
            position: absolute;
            top: 12px; left: 12px;
            background: #e74c3c;
            color: #fff;
            font-weight: bold;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(231,76,60,0.12);
        }
        .product-card .btn-primary {
            background: #3498db;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: background 0.18s;
        }
        .product-card .btn-primary:hover {
            background: #217dbb;
        }
        .product-card .btn-outline-primary {
            border-radius: 8px;
        }
        .product-card .text-danger {
            font-weight: bold;
        }
        .footer {
            background: #2c3e50;
            color: #fff;
            padding: 40px 0 20px 0;
            margin-top: 60px;
        }
        .footer h5 {
            font-weight: 700;
            margin-bottom: 18px;
        }
        .footer .social-icons a {
            font-size: 1.5rem;
            margin-right: 16px;
            color: #fff;
            transition: color 0.2s;
        }
        .footer .social-icons a:hover {
            color: #2ecc71;
        }
        .footer ul li a {
            color: #fff;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer ul li a:hover {
            color: #2ecc71;
        }
        @media (max-width: 767px) {
            .hero-section { padding: 40px 0 60px 0; }
            .product-img { height: 120px; }
            .category-card { width: 80px; height: 80px; }
            .category-card img { width: 60px; height: 60px; }
        }
        /* Rediseño de categorías */
        .categories-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 24px;
            justify-content: flex-start;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .category-rect-card {
            flex: 0 0 190px;
            max-width: 190px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(44,62,80,0.08);
            transition: transform 0.18s, box-shadow 0.18s;
            text-align: center;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .category-rect-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 16px 16px 0 0;
            background: #f7f9fb;
            display: block;
        }
        .category-rect-card h5 {
            margin: 0;
            padding: 16px 0 12px 0;
            font-size: 1.1rem;
            color: #3498db;
            font-weight: 700;
        }
        .category-rect-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px rgba(52,152,219,0.13);
        }
        @media (max-width: 900px) {
            .categories-row { gap: 12px; }
            .category-rect-card { flex: 0 0 150px; max-width: 150px; }
        }
        @media (max-width: 600px) {
            .category-rect-card { flex: 0 0 120px; max-width: 120px; }
        }
        /* Fin rediseño categorías */
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#inicio">
                <i class="fas fa-shopping-basket me-2"></i>MARKETSOFT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#inicio">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#productos">Productos</a>

                    <li class="nav-item">
                        <a class="nav-link" href="#categorias">Categorías</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link cart-icon" href="carrito.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count" id="cart-count"><?= isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0 ?></span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-edit"></i> Mi Perfil</a></li>
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Panel Admin</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login/login.php"><i class="fas fa-user"></i> LOGIN</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Bienvenido a MARKETSOFT</h1>
            <p class="lead mb-5">Tu supermercado online con los mejores precios y productos frescos</p>
            
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <form class="d-flex mb-4" method="get" action="#productos">
                        <input type="text" name="buscar" class="form-control form-control-lg me-2" placeholder="Buscar productos..." value="<?= isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : '' ?>" style="border-radius: 0;">
                        <button type="submit" class="btn btn-success btn-lg" style="border-radius: 0 6px 6px 0;"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <!-- Categories -->
        <section class="mb-5" id="categorias">
            <h2 class="mb-4">Categorías</h2>
            <div class="categories-row">
                <div class="category-rect-card">
                    <a href="?categoria=Frutas%20y%20Verduras" style="text-decoration:none; color:inherit;">
                        <img src="img/verduras.jpg" alt="Frutas y Verduras">
                        <h5>Frutas y Verduras</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Carnes" style="text-decoration:none; color:inherit;">
                        <img src="img/Carnesfrescas.jpg" alt="Carnes">
                        <h5>Carnes</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Lácteos" style="text-decoration:none; color:inherit;">
                        <img src="img/lacteos.jpg" alt="Lácteos">
                        <h5>Lácteos</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Bebidas" style="text-decoration:none; color:inherit;">
                        <img src="img/bebidas.jpg" alt="Bebidas">
                        <h5>Bebidas</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Panadería" style="text-decoration:none; color:inherit;">
                        <img src="img/pan.jpg" alt="Panadería">
                        <h5>Panadería</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Abarrotes" style="text-decoration:none; color:inherit;">
                        <img src="img/abarrotes.jpg" alt="Abarrotes">
                        <h5>Abarrotes</h5>
                    </a>
                </div>
                <div class="category-rect-card">
                    <a href="?categoria=Limpieza%20y%20Hogar" style="text-decoration:none; color:inherit;">
                        <img src="img/aseo.jpg" alt="Limpieza y Hogar">
                        <h5>Limpieza y Hogar</h5>
                    </a>
                </div>
            </div>
            <?php if (isset($_GET['categoria']) && $_GET['categoria'] != ''): ?>
                <div class="mt-3 text-end">
                    <a href="index.php#productos" class="btn btn-secondary">Limpiar filtro de categoría</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Featured Products -->
        <section class="mb-5" id="productos">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Productos Disponibles<?= isset($_GET['categoria']) ? ' - ' . htmlspecialchars($_GET['categoria']) : '' ?></h2>
            </div>
            <div class="row">
                <?php
                $where = '';
                if (isset($_GET['categoria']) && $_GET['categoria'] != '') {
                    $cat = $pdo->quote($_GET['categoria']);
                    $where = "WHERE cantidad > 0 AND categoria = $cat";
                } else {
                    $where = "WHERE cantidad > 0";
                }
                // Filtro de búsqueda
                if (isset($_GET['buscar']) && trim($_GET['buscar']) !== '') {
                    $busqueda = '%' . trim($_GET['buscar']) . '%';
                    $where .= ($where ? ' AND' : 'WHERE') . " (nombre LIKE " . $pdo->quote($busqueda) . " OR descripcion LIKE " . $pdo->quote($busqueda) . ")";
                }
                $stmt = $pdo->query("SELECT * FROM productos $where ORDER BY id DESC");
                while ($producto = $stmt->fetch()):
                    $tieneDescuento = isset($descuentos[$producto['id']]);
                    $descuento = $tieneDescuento ? $descuentos[$producto['id']] : null;
                    $precio_desc = $tieneDescuento ? $producto['precio'] * (1 - $descuento['valor']/100) : $producto['precio'];
                ?>
                <div class="col-md-3 mb-4">
                    <div class="product-card h-100">
                        <div class="position-relative">
                            <img src="<?= htmlspecialchars($producto['imagen']) ?>" class="img-fluid product-img" alt="<?= htmlspecialchars($producto['nombre']) ?>">
                            <?php if ($tieneDescuento): ?>
                                <span class="badge badge-discount">-<?= htmlspecialchars($descuento['valor']) ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="p-3">
                            <h5><?= htmlspecialchars($producto['nombre']) ?></h5>
                            <p class="text-muted mb-1">Categoría: <?= htmlspecialchars($producto['categoria']) ?></p>
                            <p class="mb-1">Stock: <span class="fw-bold <?= $producto['cantidad'] < 10 ? 'text-danger' : '' ?>"><?= $producto['cantidad'] ?></span></p>
                            <?php if ($tieneDescuento): ?>
                                <p class="mb-1">
                                    <span class="text-decoration-line-through text-muted me-2">$<?= number_format($producto['precio'], 0, ',', '.') ?></span>
                                    <span class="fw-bold text-danger">$<?= number_format($precio_desc, 0, ',', '.') ?></span>
                                </p>
                            <?php else: ?>
                                <p class="mb-1">$<?= number_format($producto['precio'], 0, ',', '.') ?></p>
                            <?php endif; ?>
                            <p class="small text-muted"><?= htmlspecialchars($producto['descripcion']) ?></p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <div class="mt-3">
                                    <div class="input-group mb-2">
                                        <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidadInput(<?= $producto['id'] ?>, -1)">-</button>
                                        <input type="number" class="form-control text-center" id="cantidad_<?= $producto['id'] ?>" value="1" min="1" max="<?= $producto['cantidad'] ?>" style="width: 60px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="cambiarCantidadInput(<?= $producto['id'] ?>, 1)">+</button>
                                    </div>
                                    <button class="btn btn-primary w-100" onclick="agregarAlCarrito(<?= $producto['id'] ?>)">
                                        <i class="fas fa-cart-plus"></i> Agregar al Carrito
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <a href="login/login.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-sign-in-alt"></i> Inicia sesión para comprar
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="footer" id="contacto">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5><i class="fas fa-shopping-basket me-2"></i>MARKETSOFT</h5>
                    <p>Tu supermercado online de confianza con los mejores productos y precios.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5>Enlaces</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">Inicio</a></li>
                        <li><a href="#" class="text-white">Productos</a></li>
                        <li><a href="#" class="text-white">Ofertas</a></li>
                        <li><a href="#" class="text-white">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 mb-md-0">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Av. Principal 123, Ciudad</li>
                        <li><i class="fas fa-phone me-2"></i> (123) 456-7890</li>
                        <li><i class="fas fa-envelope me-2"></i> info@marketsoft.com</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-light">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 MARKETSOFT. Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <a href="#" class="text-white me-3">Términos</a>
                        <a href="#" class="text-white">Privacidad</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Scroll suave para navbar
    const links = document.querySelectorAll('.navbar-nav .nav-link');
    for (const link of links) {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const section = document.querySelector(href);
                if (section) {
                    section.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    }

    // Funciones del carrito
    function cambiarCantidadInput(productoId, cambio) {
        const input = document.getElementById('cantidad_' + productoId) || document.getElementById('cantidad_oferta_' + productoId);
        if (input) {
            console.log('Antes:', input.value);
            let nuevaCantidad = parseInt(input.value) + cambio;
            if (nuevaCantidad < 1) nuevaCantidad = 1;
            if (input.max && nuevaCantidad > parseInt(input.max)) nuevaCantidad = parseInt(input.max);
            input.value = nuevaCantidad;
            console.log('Después:', input.value, 'para producto', productoId);
        }
    }

    function agregarAlCarrito(productoId) {
        const input = document.getElementById('cantidad_' + productoId) || document.getElementById('cantidad_oferta_' + productoId);
        let cantidad = input ? parseInt(input.value) : 1;
        if (isNaN(cantidad) || cantidad < 1) cantidad = 1;
        if (input && input.max && cantidad > parseInt(input.max)) cantidad = parseInt(input.max);

        // Mostrar indicador de carga
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        btn.disabled = true;

        // Enviar solicitud AJAX
        fetch('ajax_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=agregar&producto_id=' + productoId + '&cantidad=' + cantidad
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cart-count').textContent = data.data.total_items;
                mostrarNotificacion('Producto agregado al carrito', 'success');
                if (input) input.value = 1;
            } else {
                mostrarNotificacion(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error al agregar al carrito', 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }

    function mostrarNotificacion(mensaje, tipo) {
        // Crear elemento de notificación
        const notificacion = document.createElement('div');
        notificacion.className = `alert alert-${tipo === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
        notificacion.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notificacion.innerHTML = `
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notificacion);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.remove();
            }
        }, 3000);
    }

    // Actualizar contador del carrito al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si el usuario está logueado
        <?php if (isset($_SESSION['user_id'])): ?>
        fetch('ajax_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=obtener_info'
        })
        .then(response => response.json())
        .then(data => {
            if (data.total_items !== undefined) {
                document.getElementById('cart-count').textContent = data.total_items;
            }
        })
        .catch(error => console.error('Error al obtener info del carrito:', error));
        <?php endif; ?>
    });
    </script>
</body>
</html>