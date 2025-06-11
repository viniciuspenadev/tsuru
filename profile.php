<?php
require_once 'config/config.php';
require_once 'classes/User.php';

requireLogin();

$user = new User();
$userData = $user->findById($_SESSION['user_id']);

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($name) || empty($email)) {
            $error = 'Nome e email são obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email inválido.';
        } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
            $error = 'As senhas não conferem.';
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            $error = 'A nova senha deve ter pelo menos 6 caracteres.';
        } else {
            // Verificar se o email já está em uso por outro usuário
            $existingUser = $user->findByEmail($email);
            if ($existingUser && $existingUser['id'] != $_SESSION['user_id']) {
                $error = 'Este email já está em uso por outro usuário.';
            } else {
                // Atualizar perfil
                $result = $user->updateProfile($_SESSION['user_id'], $name, $email);
                
                // Atualizar senha se fornecida
                if (!empty($newPassword)) {
                    if (!$user->verifyPassword($_SESSION['user_id'], $currentPassword)) {
                        $error = 'Senha atual incorreta.';
                    } else {
                        $user->updatePassword($_SESSION['user_id'], $newPassword);
                        $success = true;
                    }
                } else {
                    $success = true;
                }
                
                if ($success) {
                    // Atualizar dados da sessão
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    
                    // Recarregar dados do usuário
                    $userData = $user->findById($_SESSION['user_id']);
                }
            }
        }
    }
}

// Definir variáveis para o layout
$pageTitle = "Perfil - " . APP_NAME;
$pageHeader = "Perfil do Usuário";
$pageSubtitle = "Gerencie suas informações pessoais e senha";

// Definir alerta se houver sucesso ou erro
if ($success) {
    $alertType = 'success';
    $alertMessage = 'Perfil atualizado com sucesso!';
} elseif (!empty($error)) {
    $alertType = 'danger';
    $alertMessage = $error;
}

// Iniciar buffer de saída para o conteúdo
ob_start();
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Informações Pessoais</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?= htmlspecialchars($userData['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($userData['email']) ?>" required>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5>Alterar Senha</h5>
                    <p class="text-muted small">Deixe em branco para manter a senha atual</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nova Senha</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Informações da Conta</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Data de Cadastro:</strong><br>
                        <?= date('d/m/Y H:i', strtotime($userData['created_at'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Último Acesso:</strong><br>
                        <?= isset($userData['last_login']) ? date('d/m/Y H:i', strtotime($userData['last_login'])) : 'Não disponível' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Obter o conteúdo do buffer e limpá-lo
$content = ob_get_clean();

// Incluir o layout
include 'includes/layout.php';
?>
