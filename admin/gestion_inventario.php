<?php
session_start();
require_once '../includes/db.php';

// Reactivar producto
if (isset($_GET['reactivar'])) {
    $stmt = $pdo->prepare('UPDATE productos SET activo = 1 WHERE id = ?');
    $stmt->execute([$_GET['reactivar']]);
    header('Location: gestion_inventario.php?success=1');
    exit;
}

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit;
}

// Opciones de categorías
$categorias = [
    'Frutas y Verduras',
    'Carnes',
    'Lácteos',
    'Bebidas',
    'Panadería',
    'Abarrotes',
    'Limpieza y Hogar'
];

// Procesar formulario de producto
$success = $error = '';
$editando = false;
$producto = [
    'id' => '', 'nombre' => '', 'descripcion' => '', 'precio' => '', 'cantidad' => '', 'categoria' => '', 'imagen' => '', 'costo' => ''
];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([$_GET['edit']]);
    $producto = $stmt->fetch();
    $editando = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? '';
    $costo = $_POST['costo'] ?? '';
    $cantidad = $_POST['cantidad'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $imagen = '';

    // Validar imagen
    if (!$id || ($_FILES['imagen']['name'] ?? '')) {
        if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            if (!$id) $error = 'La imagen es obligatoria.';
        } else {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $nombreImagen = uniqid('prod_') . '.' . $ext;
            $rutaDestino = '../img/productos/' . $nombreImagen;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
                $error = 'Error al subir la imagen.';
            } else {
                $imagen = 'img/productos/' . $nombreImagen;
            }
        }
    } else {
        $imagen = $_POST['imagen_actual'] ?? '';
    }

    if (!$error) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, costo=?, cantidad=?, categoria=?, imagen=? WHERE id=?');
            $stmt->execute([$nombre, $descripcion, $precio, $costo, $cantidad, $categoria, $imagen, $id]);
            $success = 'Producto actualizado correctamente.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, costo, cantidad, categoria, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nombre, $descripcion, $precio, $costo, $cantidad, $categoria, $imagen]);
            $success = 'Producto agregado correctamente.';
        }
        header('Location: gestion_inventario.php?success=1');
        exit;
    }
}

// Eliminar producto
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('UPDATE productos SET activo = 0 WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: gestion_inventario.php?success=1');
    exit;
}

// Filtro de categoría para la lista de productos
$categoria_filtro = $_GET['categoria_filtro'] ?? '';
// Opción para mostrar inactivos
$ver_inactivos = isset($_GET['ver_inactivos']) && $_GET['ver_inactivos'] == '1';
if ($categoria_filtro && in_array($categoria_filtro, $categorias)) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE categoria = ? AND activo = ? ORDER BY nombre');
    $stmt->execute([$categoria_filtro, $ver_inactivos ? 0 : 1]);
    $productos = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE activo = ? ORDER BY nombre');
    $stmt->execute([$ver_inactivos ? 0 : 1]);
    $productos = $stmt->fetchAll();
}

// 1. Agregar un endpoint AJAX para obtener datos del producto
if (isset($_GET['get_producto']) && is_numeric($_GET['get_producto'])) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([$_GET['get_producto']]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($prod);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="container mt-4" style="margin-left:260px;">
    <h2>Gestión de Inventario</h2>
    
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">Operación realizada correctamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Lista de Productos -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    Lista de Productos
                </div>
                <div class="card-body">
                    <!-- Filtro por categoría -->
                    <form method="get" class="mb-3 d-flex align-items-center">
                        <label class="me-2 mb-0">Filtrar por categoría:</label>
                        <select name="categoria_filtro" class="form-select w-auto me-2" onchange="this.form.submit()">
                            <option value="">Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat ?>" <?= $categoria_filtro == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($categoria_filtro): ?>
                            <a href="gestion_inventario.php" class="btn btn-secondary btn-sm">Limpiar</a>
                        <?php endif; ?>
                    </form>
                    <!-- Botón para ver activos/inactivos -->
                    <div class="mb-3">
                        <?php if ($ver_inactivos): ?>
                            <a href="gestion_inventario.php" class="btn btn-success">Ver productos activos</a>
                        <?php else: ?>
                            <a href="gestion_inventario.php?ver_inactivos=1" class="btn btn-secondary">Ver productos inactivos</a>
                        <?php endif; ?>
                    </div>
                    <!-- Botón flotante para agregar producto -->
                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                    <!-- Modal Agregar Producto -->
                    <div class="modal fade" id="modalAgregarProducto" tabindex="-1" aria-labelledby="modalAgregarProductoLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="modalAgregarProductoLabel">Agregar Nuevo Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($producto['id']) ?>">
                                <?php if ($editando): ?>
                                    <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($producto['imagen']) ?>">
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Categoría</label>
                                        <select name="categoria" class="form-select" required>
                                            <option value="">Selecciona una categoría</option>
                                            <?php foreach ($categorias as $cat): ?>
                                                <option value="<?= $cat ?>" <?= $producto['categoria'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea name="descripcion" class="form-control" rows="2" required><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Precio</label>
                                        <input type="number" step="0.01" name="precio" class="form-control" value="<?= htmlspecialchars($producto['precio']) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Costo</label>
                                        <input type="number" step="0.01" name="costo" class="form-control" value="<?= htmlspecialchars($producto['costo'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" name="cantidad" class="form-control" value="<?= htmlspecialchars($producto['cantidad']) ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Imagen</label>
                                    <input type="file" name="imagen" class="form-control" accept="image/*">
                                    <?php if ($editando && $producto['imagen']): ?>
                                        <img src="../<?= htmlspecialchars($producto['imagen']) ?>" alt="Imagen actual" class="img-thumbnail mt-2" style="max-width:120px;">
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        Agregar Producto
                                    </button>
                                </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                    <!-- Modal Editar Producto (estructura, lógica JS pendiente) -->
                    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="modalEditarProductoLabel" aria-hidden="true">
                      <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="modalEditarProductoLabel">Editar Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                          </div>
                          <div class="modal-body">
                            <form method="POST" enctype="multipart/form-data" id="formEditarProducto">
                                <input type="hidden" name="id" id="edit_id">
                                <input type="hidden" name="imagen_actual" id="edit_imagen_actual">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre</label>
                                        <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Categoría</label>
                                        <select name="categoria" id="edit_categoria" class="form-select" required>
                                            <option value="">Selecciona una categoría</option>
                                            <?php foreach ($categorias as $cat): ?>
                                                <option value="<?= $cat ?>"><?= $cat ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Precio</label>
                                        <input type="number" step="0.01" name="precio" id="edit_precio" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Costo</label>
                                        <input type="number" step="0.01" name="costo" id="edit_costo" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Cantidad</label>
                                        <input type="number" name="cantidad" id="edit_cantidad" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Imagen (deja en blanco para mantener la actual)</label>
                                    <input type="file" name="imagen" class="form-control" accept="image/*">
                                    <img id="edit_imagen_preview" src="" alt="Imagen actual" class="img-thumbnail mt-2" style="max-width:120px; display:none;">
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                    <!-- Mejorar visualmente la tabla de productos -->
                    <div class="table-responsive">
                        <table class="table align-middle table-hover table-bordered bg-white shadow-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Imagen</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Precio</th>
                                    <th>Costo</th>
                                    <th>Ganancia</th>
                                    <th>Cantidad</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $row): ?>
                                <tr<?= ($row['cantidad'] < 5 && $row['activo']) ? ' class="table-warning"' : (!$row['activo'] ? ' class="table-secondary"' : '') ?>>
                                    <td><img src="../<?= htmlspecialchars($row['imagen']) ?>" alt="img" class="rounded shadow-sm" style="max-width:70px;max-height:70px;"></td>
                                    <td class="fw-bold"><?= htmlspecialchars($row['nombre']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['categoria']) ?></span></td>
                                    <td>$<?= number_format($row['precio'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($row['costo'], 0, ',', '.') ?></td>
                                    <td>$<?= number_format($row['precio'] - $row['costo'], 0, ',', '.') ?></td>
                                    <td>
                                        <?= $row['cantidad'] ?>
                                        <?php if ($row['cantidad'] < 5 && $row['activo']): ?>
                                            <span class="text-danger ms-1" title="Stock bajo"><i class="fas fa-exclamation-triangle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['activo']): ?>
                                            <button type="button" class="btn btn-sm btn-warning me-1 btn-editar" data-id="<?= $row['id'] ?>" data-bs-toggle="modal" data-bs-target="#modalEditarProducto">Editar</button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminar<?= $row['id'] ?>">
                                                Eliminar
                                            </button>
                                        <?php else: ?>
                                            <a href="gestion_inventario.php?reactivar=<?= $row['id'] ?>" class="btn btn-sm btn-success">Reactivar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Modal de confirmación -->
                                <div class="modal fade" id="modalEliminar<?= $row['id'] ?>" tabindex="-1" aria-labelledby="modalEliminarLabel<?= $row['id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="modalEliminarLabel<?= $row['id'] ?>">Confirmar eliminación</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                      </div>
                                      <div class="modal-body">
                                        ¿Estás seguro de que deseas eliminar el producto <strong><?= htmlspecialchars($row['nombre']) ?></strong>? Esta acción no se puede deshacer.
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <a href="gestion_inventario.php?delete=<?= $row['id'] ?>" class="btn btn-danger">Eliminar</a>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-editar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            fetch('gestion_inventario.php?get_producto=' + id)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_nombre').value = data.nombre;
                    document.getElementById('edit_descripcion').value = data.descripcion;
                    document.getElementById('edit_precio').value = data.precio;
                    document.getElementById('edit_costo').value = data.costo;
                    document.getElementById('edit_cantidad').value = data.cantidad;
                    document.getElementById('edit_categoria').value = data.categoria;
                    document.getElementById('edit_imagen_actual').value = data.imagen;
                    if (data.imagen) {
                        document.getElementById('edit_imagen_preview').src = '../' + data.imagen;
                        document.getElementById('edit_imagen_preview').style.display = 'block';
                    } else {
                        document.getElementById('edit_imagen_preview').style.display = 'none';
                    }
                });
        });
    });
});
</script>
</body>
</html>