<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

// Obtener detalles del cliente
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'detalles') {
    $cliente_id = (int)$_GET['id'];
    
    // Obtener información del cliente
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(p.id) as total_pedidos,
               SUM(CASE WHEN p.estado IN ('enviado','entregado','completado') THEN p.total ELSE 0 END) as total_gastado,
               MAX(p.fecha) as ultimo_pedido,
               MIN(p.fecha) as primer_pedido
        FROM usuarios u
        LEFT JOIN pedidos p ON u.id = p.usuario_id
        WHERE u.id = ? AND u.rol != 'admin'
        GROUP BY u.id
    ");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }
    
    // Obtener historial de pedidos
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(pp.id) as total_productos
        FROM pedidos p
        LEFT JOIN pedido_productos pp ON p.id = pp.pedido_id
        WHERE p.usuario_id = ?
        GROUP BY p.id
        ORDER BY p.fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$cliente_id]);
    $pedidos = $stmt->fetchAll();
    
    // Generar HTML para el modal
    $html = "
    <div class='row'>
        <div class='col-md-6'>
            <h6><i class='fas fa-user me-2'></i>Información Personal</h6>
            <table class='table table-sm'>
                <tr><td><strong>ID:</strong></td><td>{$cliente['id']}</td></tr>
                <tr><td><strong>Nombre:</strong></td><td>" . htmlspecialchars($cliente['nombre']) . "</td></tr>
                <tr><td><strong>Correo:</strong></td><td>" . htmlspecialchars($cliente['correo']) . "</td></tr>
                <tr><td><strong>Teléfono:</strong></td><td>" . htmlspecialchars($cliente['telefono'] ?? 'No registrado') . "</td></tr>
                <tr><td><strong>Dirección:</strong></td><td>" . htmlspecialchars($cliente['direccion'] ?? 'No registrada') . "</td></tr>
                <tr><td><strong>Estado:</strong></td><td>
                    " . ($cliente['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>') . "
                </td></tr>
                <tr><td><strong>Fecha Registro:</strong></td><td>" . date('d/m/Y H:i', strtotime($cliente['fecha_registro'] ?? 'now')) . "</td></tr>
            </table>
        </div>
        <div class='col-md-6'>
            <h6><i class='fas fa-shopping-cart me-2'></i>Estadísticas de Compras</h6>
            <table class='table table-sm'>
                <tr><td><strong>Total Pedidos:</strong></td><td><span class='badge bg-info'>{$cliente['total_pedidos']}</span></td></tr>
                <tr><td><strong>Total Gastado:</strong></td><td><span class='text-success fw-bold'>$" . number_format($cliente['total_gastado'] ?? 0, 0, ',', '.') . "</span></td></tr>
                <tr><td><strong>Primer Pedido:</strong></td><td>" . ($cliente['primer_pedido'] ? date('d/m/Y', strtotime($cliente['primer_pedido'])) : 'Sin pedidos') . "</td></tr>
                <tr><td><strong>Último Pedido:</strong></td><td>" . ($cliente['ultimo_pedido'] ? date('d/m/Y', strtotime($cliente['ultimo_pedido'])) : 'Sin pedidos') . "</td></tr>
                <tr><td><strong>Promedio por Pedido:</strong></td><td>
                    " . ($cliente['total_pedidos'] > 0 ? '$' . number_format(($cliente['total_gastado'] ?? 0) / $cliente['total_pedidos'], 0, ',', '.') : '$0') . "
                </td></tr>
            </table>
        </div>
    </div>";
    
    if (!empty($pedidos)) {
        $html .= "
        <hr>
        <h6><i class='fas fa-history me-2'></i>Últimos Pedidos</h6>
        <div class='table-responsive'>
            <table class='table table-sm table-striped'>
                <thead class='table-dark'>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Productos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($pedidos as $pedido) {
            $estado_class = match($pedido['estado']) {
                'pendiente' => 'warning',
                'enviado' => 'info',
                'entregado', 'completado' => 'success',
                'cancelado' => 'danger',
                default => 'secondary'
            };
            
            $html .= "
                <tr>
                    <td>{$pedido['id']}</td>
                    <td>" . date('d/m/Y H:i', strtotime($pedido['fecha'])) . "</td>
                    <td>$" . number_format($pedido['total'], 0, ',', '.') . "</td>
                    <td>{$pedido['total_productos']}</td>
                    <td><span class='badge bg-{$estado_class}'>" . ucfirst($pedido['estado']) . "</span></td>
                </tr>";
        }
        
        $html .= "
                </tbody>
            </table>
        </div>";
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

// Obtener datos del cliente para editar
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener') {
    $cliente_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND rol != 'admin'");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }
    
    echo json_encode(['success' => true, 'cliente' => $cliente]);
    exit;
}

// Actualizar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $activo = (int)$_POST['activo'];
    
    // Validaciones
    if (empty($nombre) || empty($correo)) {
        echo json_encode(['success' => false, 'message' => 'Nombre y correo son obligatorios']);
        exit;
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
        exit;
    }
    
    // Verificar si el correo ya existe en otro usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
    $stmt->execute([$correo, $id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El correo ya está registrado por otro usuario']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET nombre = ?, correo = ?, telefono = ?, direccion = ?, activo = ?
            WHERE id = ? AND rol != 'admin'
        ");
        $stmt->execute([$nombre, $correo, $telefono, $direccion, $activo, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Cliente actualizado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el cliente']);
    }
    exit;
}

// Si no se reconoce la acción
echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?> 