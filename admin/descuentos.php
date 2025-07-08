<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit;
}

// Obtener productos para el select
$stmt = $pdo->query('SELECT id, nombre FROM productos ORDER BY nombre');
$productos = $stmt->fetchAll();

// Procesar formulario
$success = $error = '';
$editando = false;
$descuento = [
    'id' => '', 'codigo' => '', 'valor' => '', 'producto_id' => '', 'valido' => 1
];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM descuentos WHERE id = ?');
    $stmt->execute([$_GET['edit']]);
    $descuento = $stmt->fetch();
    $editando = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $producto_id = $_POST['producto_id'] ?? '';
    $valido = isset($_POST['valido']) ? 1 : 0;
    $id = $_POST['id'] ?? '';

    if (!$codigo || !$valor || !$producto_id) {
        $error = 'Todos los campos son obligatorios.';
    } else if ($valor < 0 || $valor > 100) {
        $error = 'El porcentaje de descuento debe estar entre 0% y 100%.';
    } else {
        if ($editando) {
            $stmt = $pdo->prepare('UPDATE descuentos SET codigo=?, valor=?, producto_id=?, valido=? WHERE id=?');
            $stmt->execute([$codigo, $valor, $producto_id, $valido, $id]);
            $success = 'Descuento actualizado correctamente.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO descuentos (codigo, valor, producto_id, valido) VALUES (?, ?, ?, ?)');
            $stmt->execute([$codigo, $valor, $producto_id, $valido]);
            $success = 'Descuento agregado correctamente.';
        }
        header('Location: descuentos.php?success=1');
        exit;
    }
}

// Eliminar descuento
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM descuentos WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: descuentos.php?success=1');
    exit;
}

// Listar descuentos
$stmt = $pdo->query('SELECT d.*, p.nombre as producto_nombre FROM descuentos d JOIN productos p ON d.producto_id = p.id ORDER BY d.id DESC');
$descuentos = $stmt->fetchAll();
?>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="container mt-4" style="margin-left:260px;">
    <h2>Gestión de Descuentos</h2>
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">Operación realizada correctamente.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">
                    <?= $editando ? 'Editar Descuento' : 'Agregar Nuevo Descuento' ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($descuento['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($descuento['codigo']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Porcentaje (%)</label>
                            <input type="number" step="0.01" name="valor" class="form-control" value="<?= htmlspecialchars($descuento['valor']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <select name="producto_id" class="form-select" required>
                                <option value="">Selecciona un producto</option>
                                <?php foreach ($productos as $prod): ?>
                                    <option value="<?= $prod['id'] ?>" <?= $descuento['producto_id'] == $prod['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prod['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="valido" id="valido" value="1" <?= $descuento['valido'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="valido">Descuento activo</label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?= $editando ? 'Actualizar' : 'Agregar' ?> Descuento
                        </button>
                        <?php if ($editando): ?>
                            <a href="descuentos.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">Lista de Descuentos</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Porcentaje</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($descuentos as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['codigo']) ?></td>
                                    <td><?= htmlspecialchars($row['producto_nombre']) ?></td>
                                    <td><?= htmlspecialchars($row['valor']) ?>%</td>
                                    <td><?= $row['valido'] ? 'Sí' : 'No' ?></td>
                                    <td>
                                        <a href="descuentos.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                        <a href="descuentos.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este descuento?')">Eliminar</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</body> 