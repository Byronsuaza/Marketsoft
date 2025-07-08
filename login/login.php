<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $contrasena = trim($_POST['contrasena']);

    if (empty($correo) || empty($contrasena)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        try {
            // Consulta preparada para mayor seguridad
            $sql = "SELECT * FROM usuarios WHERE correo = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$correo]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $login_exitoso = false;
                
                // Verificar si la contraseña está hasheada (empieza con $2y$)
                if (substr($usuario['contrasena'], 0, 4) === '$2y$') {
                    // Contraseña hasheada - usar password_verify()
                    if (password_verify($contrasena, $usuario['contrasena'])) {
                        $login_exitoso = true;
                    }
                } else {
                    // Contraseña en texto plano - comparación directa
                    if ($contrasena === $usuario['contrasena']) {
                        $login_exitoso = true;
                        
                        // Actualizar a contraseña hasheada para mayor seguridad
                        $nueva_contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
                        $update_sql = "UPDATE usuarios SET contrasena = ? WHERE id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$nueva_contrasena_hash, $usuario['id']]);
                    }
                }

                if ($login_exitoso) {
                    // Autenticación exitosa
                    $_SESSION['user_id'] = $usuario['id'];
                    $_SESSION['user_name'] = $usuario['nombre'];
                    $_SESSION['user_email'] = $usuario['correo'];
                    $_SESSION['user_role'] = $usuario['rol'];
                    
                    // Redirigir según el rol
                    if ($usuario['rol'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../index.php");
                    }
                    exit;
                } else {
                    $error = "Credenciales incorrectas.";
                }
            } else {
                $error = "Credenciales incorrectas.";
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema. Por favor intente más tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MARKETSOFT</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container mx-auto">
        <div class="login-container">
            <i class="fas fa-lock header-icon fa-2x"></i>
            <h1 class="login-header">Iniciar Sesión</h1>
            <?php if (isset($error)): ?>
                <div id="error-alert" class="error-alert show">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                    <i class="fas fa-times alert-close" onclick="closeAlert()"></i>
                </div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div class="mb-4">
                    <label for="correo" class="form-label block">Correo Electrónico</label>
                    <input type="email" class="form-control" id="correo" name="correo" 
                           value="<?php echo isset($correo) ? htmlspecialchars($correo) : ''; ?>" required aria-label="Correo Electrónico">
                </div>
                <div class="mb-4 input-group">
                    <label for="contrasena" class="form-label block">Contraseña</label>
                    <input type="password" class="form-control" id="contrasena" name="contrasena" required aria-label="Contraseña">
                    <span class="password-toggle" onclick="togglePassword()">
                        <i id="password-icon" class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="grid gap-2">
                    <button type="submit" class="btn-primary" aria-label="Iniciar Sesión">Ingresar</button>
                    <a href="../registro/register.php" class="btn-secondary" aria-label="Ir a Registro">¿No tienes cuenta? Regístrate</a>
                    <a href="../index.php" class="btn-secondary" aria-label="Volver al Inicio">Volver al Inicio</a>
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

        function closeAlert() {
            const alert = document.getElementById('error-alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.style.display = 'none', 300);
            }
        }
    </script>
</body>
</html>