<?php
session_start();
require_once '../includes/db.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit;
}

// Procesar acciones
$success = $error = '';

// Cambiar estado del cliente (activar/desactivar)
if (isset($_GET['toggle_status'])) {
    $cliente_id = (int)$_GET['toggle_status'];
    $stmt = $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = ? AND rol != "admin"');
    $stmt->execute([$cliente_id]);
    $success = 'Estado del cliente actualizado correctamente.';
}

// Eliminar cliente (solo si no tiene pedidos)
if (isset($_GET['delete'])) {
    $cliente_id = (int)$_GET['delete'];
    
    // Verificar si tiene pedidos
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = ?');
    $stmt->execute([$cliente_id]);
    $tiene_pedidos = $stmt->fetchColumn() > 0;
    
    if ($tiene_pedidos) {
        $error = 'No se puede eliminar el cliente porque tiene pedidos asociados.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND rol != "admin"');
        $stmt->execute([$cliente_id]);
        $success = 'Cliente eliminado correctamente.';
    }
}

// Filtros
$busqueda = $_GET['busqueda'] ?? '';
$estado_filtro = $_GET['estado_filtro'] ?? '';
$ver_inactivos = isset($_GET['ver_inactivos']) && $_GET['ver_inactivos'] == '1';

// Construir consulta con filtros
$where_conditions = ["rol != 'admin'"];
$params = [];

if ($busqueda) {
    $where_conditions[] = "(u.nombre LIKE ? OR u.correo LIKE ? OR u.telefono LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if ($estado_filtro !== '') {
    $where_conditions[] = "u.activo = ?";
    $params[] = $estado_filtro;
} elseif (!$ver_inactivos) {
    $where_conditions[] = "u.activo = 1";
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener clientes con estadísticas
$sql = "
    SELECT u.*, 
           COUNT(p.id) as total_pedidos,
           SUM(CASE WHEN p.estado IN ('enviado','entregado','completado') THEN p.total ELSE 0 END) as total_gastado,
           MAX(p.fecha) as ultimo_pedido
    FROM usuarios u
    LEFT JOIN pedidos p ON u.id = p.usuario_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.nombre
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Estadísticas generales
$total_clientes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin'")->fetchColumn();
$clientes_activos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin' AND activo = 1")->fetchColumn();
$clientes_inactivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin' AND activo = 0")->fetchColumn();
$total_ventas = $pdo->query("SELECT IFNULL(SUM(total),0) FROM pedidos WHERE estado IN ('enviado','entregado','completado')")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stats {
            transition: transform 0.2s;
        }
        .card-stats:hover {
            transform: translateY(-2px);
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .badge-status {
            font-size: 0.8em;
        }
        .search-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-action {
            margin: 2px;
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="container mt-4" style="margin-left:260px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Gestión de Clientes</h2>
    </div>

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

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card card-stats bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($total_clientes) ?></h4>
                            <small>Total Clientes</small>
                        </div>
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stats bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($clientes_activos) ?></h4>
                            <small>Clientes Activos</small>
                        </div>
                        <i class="fas fa-user-check fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stats bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?= number_format($clientes_inactivos) ?></h4>
                            <small>Clientes Inactivos</small>
                        </div>
                        <i class="fas fa-user-times fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stats bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">$<?= number_format($total_ventas, 0, ',', '.') ?></h4>
                            <small>Total Ventas</small>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="search-box text-white">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="busqueda" class="form-label">Buscar Cliente</label>
                <input type="text" class="form-control" id="busqueda" name="busqueda" 
                       value="<?= htmlspecialchars($busqueda) ?>" 
                       placeholder="Nombre, correo o teléfono...">
            </div>
            <div class="col-md-3">
                <label for="estado_filtro" class="form-label">Estado</label>
                <select class="form-select" id="estado_filtro" name="estado_filtro">
                    <option value="">Todos</option>
                    <option value="1" <?= $estado_filtro === '1' ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= $estado_filtro === '0' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-light me-2">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                    <a href="gestion_clientes.php" class="btn btn-outline-light">
                        <i class="fas fa-times me-1"></i>Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de clientes -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-list me-2"></i>Lista de Clientes (<?= count($clientes) ?>)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Estado</th>
                            <th>Pedidos</th>
                            <th>Total Gastado</th>
                            <th>Último Pedido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">
                                    <i class="fas fa-search me-2"></i>No se encontraron clientes
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr class="<?= $cliente['activo'] ? '' : 'table-danger' ?>">
                                    <td><?= $cliente['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($cliente['nombre']) ?></strong>
                                        <?php if ($cliente['rol'] === 'admin'): ?>
                                            <span class="badge bg-danger ms-1">Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($cliente['correo']) ?></td>
                                    <td><?= htmlspecialchars($cliente['telefono'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($cliente['direccion'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($cliente['activo']): ?>
                                            <span class="badge bg-success badge-status">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger badge-status">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $cliente['total_pedidos'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($cliente['total_gastado'] > 0): ?>
                                            <span class="text-success fw-bold">
                                                $<?= number_format($cliente['total_gastado'], 0, ',', '.') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">$0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cliente['ultimo_pedido']): ?>
                                            <small><?= date('d/m/Y', strtotime($cliente['ultimo_pedido'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Sin pedidos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-action" 
                                                    onclick="verDetalles(<?= $cliente['id'] ?>)"
                                                    title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" 
                                                    onclick="editarCliente(<?= $cliente['id'] ?>)"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?toggle_status=<?= $cliente['id'] ?>" 
                                               class="btn btn-sm btn-outline-<?= $cliente['activo'] ? 'warning' : 'success' ?> btn-action"
                                               title="<?= $cliente['activo'] ? 'Desactivar' : 'Activar' ?>"
                                               onclick="return confirm('¿Estás seguro de cambiar el estado de este cliente?')">
                                                <i class="fas fa-<?= $cliente['activo'] ? 'ban' : 'check' ?>"></i>
                                            </a>
                                            <?php if ($cliente['total_pedidos'] == 0): ?>
                                                <a href="?delete=<?= $cliente['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-action"
                                                   title="Eliminar"
                                                   onclick="return confirm('¿Estás seguro de eliminar este cliente? Esta acción no se puede deshacer.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver detalles del cliente -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i>Detalles del Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesCliente">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar cliente -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Editar Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarCliente" method="POST">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_nombre" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_correo" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="edit_correo" name="correo" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_telefono" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="edit_telefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label for="edit_direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="edit_direccion" name="direccion" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_activo" class="form-label">Estado</label>
                        <select class="form-select" id="edit_activo" name="activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarCliente()">
                    <i class="fas fa-save me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function verDetalles(clienteId) {
    fetch(`ajax_clientes.php?action=detalles&id=${clienteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('detallesCliente').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('modalDetalles')).show();
            } else {
                alert('Error al cargar los detalles del cliente');
            }
        });
}

function editarCliente(clienteId) {
    fetch(`ajax_clientes.php?action=obtener&id=${clienteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = data.cliente.id;
                document.getElementById('edit_nombre').value = data.cliente.nombre;
                document.getElementById('edit_correo').value = data.cliente.correo;
                document.getElementById('edit_telefono').value = data.cliente.telefono || '';
                document.getElementById('edit_direccion').value = data.cliente.direccion || '';
                document.getElementById('edit_activo').value = data.cliente.activo;
                new bootstrap.Modal(document.getElementById('modalEditar')).show();
            } else {
                alert('Error al cargar los datos del cliente');
            }
        });
}

function guardarCliente() {
    const formData = new FormData(document.getElementById('formEditarCliente'));
    formData.append('action', 'actualizar');
    
    fetch('ajax_clientes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
</body>
</html> 