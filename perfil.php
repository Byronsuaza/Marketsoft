<?php
session_start();
require_once 'includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php");
    exit;
}

$success = $error = '';

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header("Location: login/login.php");
    exit;
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
    
    // Validaciones
    if (empty($nombre) || empty($correo)) {
        $error = 'Nombre y correo son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo electrónico inválido.';
    } else {
        // Verificar si el correo ya existe en otro usuario
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
        $stmt->execute([$correo, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = 'El correo ya está registrado por otro usuario.';
        } else {
            // Si se quiere cambiar la contraseña
            if (!empty($nueva_contrasena)) {
                if (empty($contrasena_actual)) {
                    $error = 'Debe ingresar su contraseña actual.';
                } elseif (!password_verify($contrasena_actual, $usuario['contrasena'])) {
                    $error = 'La contraseña actual es incorrecta.';
                } elseif (strlen($nueva_contrasena) < 4) {
                    $error = 'La nueva contraseña debe tener al menos 4 caracteres.';
                } elseif ($nueva_contrasena !== $confirmar_contrasena) {
                    $error = 'Las contraseñas no coinciden.';
                } else {
                    // Actualizar con nueva contraseña
                    $contrasena_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nombre = ?, correo = ?, telefono = ?, direccion = ?, contrasena = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $correo, $telefono, $direccion, $contrasena_hash, $_SESSION['user_id']]);
                    $success = 'Perfil actualizado correctamente.';
                }
            } else {
                // Actualizar sin cambiar contraseña
                $stmt = $pdo->prepare("
                    UPDATE usuarios 
                    SET nombre = ?, correo = ?, telefono = ?, direccion = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $correo, $telefono, $direccion, $_SESSION['user_id']]);
                $success = 'Perfil actualizado correctamente.';
            }
            
            if ($success) {
                // Actualizar datos de sesión
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $correo;
                
                // Recargar datos del usuario
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $usuario = $stmt->fetch();
            }
        }
    }
}

// Obtener estadísticas del usuario
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado IN ('enviado','entregado','completado') THEN total ELSE 0 END) as total_gastado,
        MAX(fecha) as ultimo_pedido,
        MIN(fecha) as primer_pedido
    FROM pedidos 
    WHERE usuario_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Obtener últimos pedidos
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(pp.id) as total_productos
    FROM pedidos p
    LEFT JOIN pedido_productos pp ON p.id = pp.pedido_id
    WHERE p.usuario_id = ?
    GROUP BY p.id
    ORDER BY p.fecha DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$ultimos_pedidos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>MARKETSOFT
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
                        <a class="nav-link" href="carrito.php">Carrito</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['user_name']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user-edit me-2"></i>Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header del perfil -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-user-circle me-3"></i>Mi Perfil</h1>
                    <p class="mb-0">Gestiona tu información personal y revisa tu historial de compras</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <div class="text-end me-3">
                            <div class="fw-bold"><?= htmlspecialchars($usuario['nombre']) ?></div>
                            <small class="opacity-75">Cliente desde <?= date('M Y', strtotime($usuario['fecha_registro'] ?? 'now')) ?></small>
                        </div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="fas fa-user fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Estadísticas -->
            <div class="col-md-12 mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?= $stats['total_pedidos'] ?></h4>
                                        <small>Total Pedidos</small>
                                    </div>
                                    <i class="fas fa-shopping-bag fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">$<?= number_format($stats['total_gastado'] ?? 0, 0, ',', '.') ?></h4>
                                        <small>Total Gastado</small>
                                    </div>
                                    <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?= $stats['total_pedidos'] > 0 ? '$' . number_format(($stats['total_gastado'] ?? 0) / $stats['total_pedidos'], 0, ',', '.') : '$0' ?>
                                        </h4>
                                        <small>Promedio por Pedido</small>
                                    </div>
                                    <i class="fas fa-chart-line fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?= $stats['ultimo_pedido'] ? date('d/m/Y', strtotime($stats['ultimo_pedido'])) : 'Sin pedidos' ?>
                                        </h4>
                                        <small>Último Pedido</small>
                                    </div>
                                    <i class="fas fa-calendar fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de perfil -->
            <div class="col-md-8">
                <div class="form-section">
                    <h4><i class="fas fa-user-edit me-2"></i>Información Personal</h4>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre completo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="correo" class="form-label">Correo electrónico *</label>
                                <input type="email" class="form-control" id="correo" name="correo" 
                                       value="<?= htmlspecialchars($usuario['correo']) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" 
                                       value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="direccion" class="form-label">Dirección</label>
                                <textarea class="form-control" id="direccion" name="direccion" rows="3"><?= htmlspecialchars($usuario['direccion'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5><i class="fas fa-lock me-2"></i>Cambiar Contraseña</h5>
                        <p class="text-muted small">Deja en blanco si no quieres cambiar la contraseña</p>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="contrasena_actual" class="form-label">Contraseña actual</label>
                                <input type="password" class="form-control" id="contrasena_actual" name="contrasena_actual">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nueva_contrasena" class="form-label">Nueva contraseña</label>
                                <input type="password" class="form-control" id="nueva_contrasena" name="nueva_contrasena" minlength="4">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="confirmar_contrasena" class="form-label">Confirmar contraseña</label>
                                <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" minlength="4">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left me-1"></i>Volver
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Últimos pedidos -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-history me-2"></i>Últimos Pedidos
                    </div>
                    <div class="card-body">
                        <?php if (empty($ultimos_pedidos)): ?>
                            <p class="text-muted text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i><br>
                                No tienes pedidos aún
                            </p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($ultimos_pedidos as $pedido): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold">Pedido #<?= $pedido['id'] ?></div>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($pedido['fecha'])) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-primary">$<?= number_format($pedido['total'], 0, ',', '.') ?></div>
                                            <?php
                                            $estado_class = match($pedido['estado']) {
                                                'pendiente' => 'warning',
                                                'enviado' => 'info',
                                                'entregado', 'completado' => 'success',
                                                'cancelado' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $estado_class ?>"><?= ucfirst($pedido['estado']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="carrito.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Ver Todos
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 