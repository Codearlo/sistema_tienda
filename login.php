<?php
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treinta - Iniciar Sesión</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <svg class="logo-img" viewBox="0 0 100 100" width="60" height="60">
                        <circle cx="50" cy="50" r="45" fill="#2563eb" stroke="#1d4ed8" stroke-width="2"/>
                        <text x="50" y="58" text-anchor="middle" fill="white" font-size="24" font-weight="bold">30</text>
                    </svg>
                </div>
                <h1 class="login-title">Treinta</h1>
                <p class="login-subtitle">Gestiona tu negocio de forma inteligente</p>
            </div>

            <form class="login-form" id="loginForm" action="auth/login.php" method="POST">
                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email" name="email" class="form-input" placeholder="tu@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <circle cx="12" cy="16" r="1"></circle>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" class="checkbox">
                        <span class="checkmark"></span>
                        Recordarme
                    </label>
                    <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Iniciar Sesión
                </button>
            </form>

            <div class="demo-credentials">
                <div class="demo-title">Credenciales de prueba:</div>
                <div class="demo-info">Email: <strong>admin@treinta.local</strong></div>
                <div class="demo-info">Contraseña: <strong>password</strong></div>
            </div>
        </div>

        <div class="info-section">
            <h2>¿Por qué elegir Treinta?</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <svg class="feature-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                    <h3>Rápido y Fácil</h3>
                    <p>Gestiona tu inventario y ventas en segundos</p>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <h3>Control Financiero</h3>
                    <p>Mantén el control total de tus finanzas</p>
                </div>
                <div class="feature-item">
                    <svg class="feature-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 19c-5 0-9-4-9-9s4-9 9-9 9 4 9 9-4 9-9 9"></path>
                        <path d="M15.5 2.5A9 9 0 0 1 15.5 21.5"></path>
                    </svg>
                    <h3>Acceso 24/7</h3>
                    <p>Tu negocio en la nube, disponible siempre</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
        }
    </script>
</body>
</html>