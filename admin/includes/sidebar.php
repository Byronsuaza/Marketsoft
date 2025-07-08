<style>
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #2c3e50;
    color: #fff;
    display: flex;
    flex-direction: column;
    z-index: 1000;
}
.sidebar .sidebar-header {
    font-size: 2rem;
    font-weight: bold;
    padding: 32px 24px 16px 24px;
    background: #222c36;
    letter-spacing: 1px;
}
.sidebar .nav {
    flex: 1;
    padding: 24px 0;
}
.sidebar .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 32px;
    color: #fff;
    font-size: 1.1rem;
    text-decoration: none;
    transition: background 0.2s;
    border-radius: 6px 0 0 6px;
    margin-bottom: 8px;
}
.sidebar .nav-link.active, .sidebar .nav-link:hover {
    background: #3498db;
    color: #fff;
}
.sidebar .nav-link i {
    margin-right: 12px;
    font-size: 1.2rem;
}
@media (max-width: 768px) {
    .sidebar { width: 100vw; height: auto; position: relative; }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="sidebar">
    <div class="sidebar-header">MARKETSOFT</div>
    <div class="nav flex-column">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="gestion_inventario.php" class="nav-link"><i class="fas fa-boxes"></i> Gestión de Inventario</a>
        <a href="descuentos.php" class="nav-link"><i class="fas fa-tags"></i> Gestión de Descuentos</a>
        <a href="gestion_clientes.php" class="nav-link"><i class="fas fa-users"></i> Gestión de Clientes</a>
        <a href="finanzas.php" class="nav-link"><i class="fas fa-coins"></i> Gestión Financiera</a>
        <a href="../login/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </div>
</div> 