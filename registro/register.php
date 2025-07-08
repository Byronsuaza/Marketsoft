<?php
session_start();
require_once '../includes/db.php';

// Procesamiento del formulario
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validaciones
        if (empty($nombre) || empty($correo) || empty($telefono) || empty($direccion) || empty($contrasena)) {
            $error = "Todos los campos son obligatorios.";
        } elseif (strlen($contrasena) < 4) {
            $error = "La contraseña debe tener al menos 4 caracteres.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del correo electrónico no es válido.";
        } elseif (strlen($telefono) < 8) {
            $error = "El teléfono debe tener al menos 8 dígitos.";
        } else {
            // Verificar si el correo ya existe
            $sql_check = "SELECT id FROM usuarios WHERE correo = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$correo]);
            
            if ($stmt_check->rowCount() > 0) {
                $error = "El correo ya está registrado. Por favor, utiliza otro correo.";
            } else {
                // Insertar nuevo usuario con contraseña hasheada
                $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                $sql_insert = "INSERT INTO usuarios (nombre, correo, telefono, direccion, contrasena) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $pdo->prepare($sql_insert);
                
                if ($stmt_insert->execute([$nombre, $correo, $telefono, $direccion, $contrasena_hash])) {
                    $_SESSION['registro_exitoso'] = true;
                    header("Location: ../login/login.php");
                    exit;
                } else {
                    $error = "Error al registrar el usuario.";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error en el sistema: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mx-auto">
        <div class="registration-container">
            <i class="fas fa-store header-icon fa-2x"></i>
            <h1 class="registration-header">Registro de Usuario</h1>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <script>
                    alert("<?php echo htmlspecialchars($error); ?>");
                </script>
            <?php endif; ?>
            <form action="register.php" method="POST">
                <div class="mb-4">
                    <label for="nombre" class="form-label block">Nombre completo *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ingrese su nombre completo" value="<?php echo htmlspecialchars($nombre); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="correo" class="form-label block">Correo electrónico *</label>
                    <input type="email" class="form-control" id="correo" name="correo" placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($correo); ?>" required>
                </div>

                <div class="mb-4">
                    <label for="telefono" class="form-label block">Teléfono *</label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Ej: 809-123-4567" value="<?php echo htmlspecialchars($telefono); ?>" required minlength="8">
                </div>

                <div class="mb-4">
                    <label for="direccion" class="form-label block">Dirección *</label>
                    <textarea class="form-control" id="direccion" name="direccion" placeholder="Ingrese su dirección completa" rows="3" required><?php echo htmlspecialchars($direccion); ?></textarea>
                </div>
                
                <div class="mb-4 input-group">
                    <label for="contrasena" class="form-label block">Contraseña *</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="Mínimo 4 caracteres" required minlength="4">
                    <span class="password-toggle" onclick="togglePassword()">
                        <i id="password-icon" class="fas fa-eye"></i>
                    </span>
                </div>

                <div class="mb-4 text-sm text-gray-600">
                    <p><i class="fas fa-info-circle mr-1"></i> Los campos marcados con * son obligatorios</p>
                </div>

                <div class="grid gap-2">
                    <button type="submit" class="btn-primary">Crear cuenta</button>
                    <a href="../login/login.php" class="btn-secondary">Volver al Login</a>
                    <a href="../index.php" class="btn-secondary">Volver al Inicio</a>
                </div>
            </form>
        </div>
    </div>
    <footer class="footer">
        <p>© 2025 MARKETSOFT. Todos los derechos reservados.</p>
    </footer>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('contrasena');
            const passwordIcon = document.getElementById('password-icon');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            passwordIcon.classList.toggle('fa-eye');
            passwordIcon.classList.toggle('fa-eye-slash');
        }

        // Validación en tiempo real del teléfono
        document.getElementById('telefono').addEventListener('input', function(e) {
            // Permitir solo números, guiones y paréntesis
            this.value = this.value.replace(/[^0-9\-\(\)]/g, '');
        });
    </script>
</body>
</html>