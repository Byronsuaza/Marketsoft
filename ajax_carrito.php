<?php
session_start();
require_once 'includes/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $producto_id = (int)($_POST['producto_id'] ?? 0);
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    switch ($action) {
        case 'agregar':
            $cantidad = (int)($_POST['cantidad'] ?? 1);
            $result = agregarAlCarrito($producto_id, $cantidad);
            $response = $result;
            break;
            
        case 'actualizar':
            $cantidad = (int)($_POST['cantidad'] ?? 1);
            $result = actualizarCantidad($producto_id, $cantidad);
            $response = $result;
            break;
            
        case 'eliminar':
            $result = eliminarDelCarrito($producto_id);
            $response = $result;
            break;
            
        case 'obtener_info':
            $response = obtenerInfoCarrito();
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Acción no válida'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Función para agregar producto al carrito
function agregarAlCarrito($producto_id, $cantidad) {
    global $pdo;
    
    if ($cantidad <= 0) {
        return ['success' => false, 'message' => 'Cantidad debe ser mayor a 0'];
    }
    
    // Verificar si el producto existe y tiene stock
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND cantidad > 0");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        return ['success' => false, 'message' => 'Producto no disponible'];
    }
    
    // Verificar si ya está en el carrito
    if (isset($_SESSION['carrito'][$producto_id])) {
        $nueva_cantidad = $_SESSION['carrito'][$producto_id]['cantidad'] + $cantidad;
    } else {
        $nueva_cantidad = $cantidad;
    }
    
    // Verificar que no exceda el stock
    if ($nueva_cantidad > $producto['cantidad']) {
        return ['success' => false, 'message' => 'Stock insuficiente. Máximo disponible: ' . $producto['cantidad']];
    }
    
    // Agregar o actualizar en el carrito
    if (isset($_SESSION['carrito'][$producto_id])) {
        $_SESSION['carrito'][$producto_id]['cantidad'] = $nueva_cantidad;
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
    
    return [
        'success' => true, 
        'message' => 'Producto agregado al carrito',
        'data' => obtenerInfoCarrito()
    ];
}

// Función para actualizar cantidad
function actualizarCantidad($producto_id, $cantidad) {
    if (!isset($_SESSION['carrito'][$producto_id])) {
        return ['success' => false, 'message' => 'Producto no encontrado en el carrito'];
    }
    
    if ($cantidad <= 0) {
        return eliminarDelCarrito($producto_id);
    }
    
    // Verificar stock disponible
    $stock_disponible = $_SESSION['carrito'][$producto_id]['stock_disponible'];
    if ($cantidad > $stock_disponible) {
        return ['success' => false, 'message' => 'Stock insuficiente. Máximo disponible: ' . $stock_disponible];
    }
    
    $_SESSION['carrito'][$producto_id]['cantidad'] = $cantidad;
    
    return [
        'success' => true, 
        'message' => 'Cantidad actualizada',
        'data' => obtenerInfoCarrito()
    ];
}

// Función para eliminar del carrito
function eliminarDelCarrito($producto_id) {
    if (isset($_SESSION['carrito'][$producto_id])) {
        unset($_SESSION['carrito'][$producto_id]);
        return [
            'success' => true, 
            'message' => 'Producto eliminado del carrito',
            'data' => obtenerInfoCarrito()
        ];
    }
    
    return ['success' => false, 'message' => 'Producto no encontrado en el carrito'];
}

// Función para obtener información del carrito
function obtenerInfoCarrito() {
    $total_items = 0;
    $total_precio = 0;
    $items = [];
    
    foreach ($_SESSION['carrito'] as $producto_id => $item) {
        $total_items += $item['cantidad'];
        $total_precio += $item['precio'] * $item['cantidad'];
        $items[] = [
            'id' => $producto_id,
            'nombre' => $item['nombre'],
            'precio' => $item['precio'],
            'cantidad' => $item['cantidad'],
            'subtotal' => $item['precio'] * $item['cantidad']
        ];
    }
    
    return [
        'total_items' => $total_items,
        'total_precio' => $total_precio,
        'items' => $items,
        'cantidad_productos' => count($_SESSION['carrito'])
    ];
}
?> 