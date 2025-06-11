<?php
require_once 'config/config.php';
require_once 'classes/ClientAuth.php';

$clientAuth = new ClientAuth();
$clientAuth->requireLogin();

$clientData = $clientAuth->findById($_SESSION['client_id']);

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = strtoupper(trim($_POST['state'] ?? ''));
        $zip = trim($_POST['zip'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($name)) {
            $error = 'Nome é obrigatório.';
        } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
            $error = 'As senhas não conferem.';
        } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
            $error = 'A nova senha deve ter pelo menos 6 caracteres.';
        } else {
            // Atualizar perfil
            $data = [
                'name' => $name,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'phone' => $phone,
                'email' => $email
            ];
            
            $result = $clientAuth->updateProfile($_SESSION['client_id'], $data);
            
            // Atualizar senha se fornecida
            if (!empty($newPassword)) {
                if (!$clientAuth->verifyPassword($_SESSION['client_id'], $currentPassword)) {
                    $error = 'Senha atual incorreta.';
                } else {
                    $clientAuth->updatePassword($_SESSION['client_id'], $newPassword);
                    $success = true;
                }
            } else {
                $success = true;
            }
            
            if ($success) {
                // Atualizar dados da sessão
                $_SESSION['client_name'] = $name;
                
                // Recarregar dados do cliente
                $clientData = $clientAuth->findById($_SESSION['client_id']);
            }
        }
    }
}

// Definir variáveis para o layout
$pageTitle = "Meu Perfil - " . APP_NAME;
$pageHeader = "Meu Perfil";
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
                <h5 class="mb-0">Informações da Empresa</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome da Empresa</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNPJ</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['cnpj']) ?>" readonly>
                            <div class="form-text">O CNPJ não pode ser alterado</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Endereço</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($clientData['address']) ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['city']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF</label>
                            <input type="text" name="state" class="form-control" maxlength="2"
                                   value="<?= htmlspecialchars($clientData['state']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">CEP</label>
                            <input type="text" name="zip" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['zip']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['phone']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($clientData['email']) ?>">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5>Alterar Senha</h5>
                    <p class="text-muted small">Deixe em branco para manter a senha atual</p>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Senha Atual</label>
                            <input type="password" name="current_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" name="new_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirmar Nova Senha</label>
                            <input type="password" name="confirm_password" class="form-control">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end mt-4">
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
                        <p><strong>Email de Login:</strong><br>
                        <?= htmlspecialchars($clientData['login_email']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Último Acesso:</strong><br>
                        <?= isset($clientData['last_login']) ? date('d/m/Y H:i', strtotime($clientData['last_login'])) : 'Não disponível' ?></p>
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
include 'includes/client-layout.php';
?>
