<?php
require_once 'config/config.php';
require_once 'classes/User.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Email e senha são obrigatórios.';
        } else {
            $user = new User();
            if ($user->authenticate($email, $password)) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Email ou senha incorretos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: rgba(67, 97, 238, 0.05);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .login-header .logo {
            max-height: 48px; /* 60px * 0.8 */
            margin-bottom: 1rem;
        }
        
        .login-header h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem; /* 0.75rem * 0.8 e 1rem * 0.8 */
            border: 1px solid #ced4da;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 0.5rem;
            padding: 0.6rem 0.8rem; /* 0.75rem * 0.8 e 1rem * 0.8 */
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        
        .input-group-text {
            border-radius: 0.5rem 0 0 0.5rem;
            background-color: #f8f9fa;
        }
        
        .form-floating > .form-control {
            padding: 0.8rem 0.6rem; /* 1rem * 0.8 e 0.75rem * 0.8 */
        }
        
        .form-floating > label {
            padding: 0.8rem 0.6rem; /* 1rem * 0.8 e 0.75rem * 0.8 */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card">
                    <div class="login-header">
                        <img src="https://delphiforwarding.com/wp-content/uploads/2022/01/delphifretes-logo.png" alt="Delphi Logo" class="logo">
                        <p class="text-muted mb-0">Sistema de Faturamento</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2 mb-4">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                            
                            <div class="text-center">
                                <p class="text-muted">
                                    Usuário padrão: admin@invoicedelphi.com<br>
                                    Senha: password
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white">
                    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> - v<?= APP_VERSION ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
