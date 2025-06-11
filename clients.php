<?php
require_once 'config/config.php';
require_once 'classes/Client.php';

requireLogin();

$client = new Client();

// Paginação e busca
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$clients = $client->findAll($limit, $offset, $search);
$totalClients = $client->count($search);
$totalPages = ceil($totalClients / $limit);

// Definir variáveis para o layout
$pageTitle = "Clientes - " . APP_NAME;
$pageHeader = "Gerenciamento de Clientes";
$pageSubtitle = "Cadastre e gerencie seus clientes";
$pageActions = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal">
        <i class="bi bi-plus-circle"></i> Novo Cliente
    </button>
';

// Iniciar buffer de saída para o conteúdo
ob_start();
?>

<!-- Busca -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Buscar por nome, CNPJ ou email..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <a href="clients.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Limpar Filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Clientes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Lista de Clientes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Cidade/UF</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['cnpj']) ?></td>
                            <td><?= htmlspecialchars($c['city'] . '/' . $c['state']) ?></td>
                            <td><?= htmlspecialchars($c['phone']) ?></td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editClient(<?= htmlspecialchars(json_encode($c)) ?>)" 
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteClient(<?= $c['id'] ?>)" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginação" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Cliente -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="clientForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="id" id="clientId">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="name" id="clientName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CNPJ</label>
                            <input type="text" name="cnpj" id="clientCnpj" class="form-control" 
                                   placeholder="00.000.000/0000-00">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Endereço</label>
                            <textarea name="address" id="clientAddress" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="city" id="clientCity" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">UF</label>
                            <input type="text" name="state" id="clientState" class="form-control" maxlength="2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">CEP</label>
                            <input type="text" name="zip" id="clientZip" class="form-control" 
                                   placeholder="00000-000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="phone" id="clientPhone" class="form-control" 
                                   placeholder="(00) 0000-0000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="clientEmail" class="form-control">
                        </div>
                        <div class="col-12" id="loginCredentialsSection" style="display: none;">
                            <hr>
                            <h6>Credenciais de Acesso ao Portal</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email de Login</label>
                                    <input type="email" id="loginEmail" class="form-control" placeholder="Email para acesso ao portal">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Senha</label>
                                    <input type="password" id="loginPassword" class="form-control" placeholder="Senha (mín. 6 caracteres)">
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-success" onclick="createClientLogin()">
                                        <i class="bi bi-key"></i> Criar Acesso ao Portal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="col-12 text-center">
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="toggleLoginCredentials()">
                            <i class="bi bi-key"></i> Configurar Acesso ao Portal
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Obter o conteúdo do buffer e limpá-lo
$content = ob_get_clean();

// Incluir o layout
include 'includes/layout.php';
?>

<script>
document.getElementById('clientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const isEdit = formData.get('id') !== '';
    
    fetch('actions/save-client.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao salvar cliente: ' + data.message);
        }
    });
});

function editClient(client) {
    document.getElementById('clientId').value = client.id;
    document.getElementById('clientName').value = client.name;
    document.getElementById('clientCnpj').value = client.cnpj || '';
    document.getElementById('clientAddress').value = client.address || '';
    document.getElementById('clientCity').value = client.city || '';
    document.getElementById('clientState').value = client.state || '';
    document.getElementById('clientZip').value = client.zip || '';
    document.getElementById('clientPhone').value = client.phone || '';
    document.getElementById('clientEmail').value = client.email || '';
    
    document.querySelector('#clientModal .modal-title').textContent = 'Editar Cliente';
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}

function deleteClient(id) {
    if (confirm('Tem certeza que deseja excluir este cliente?')) {
        fetch('actions/delete-client.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                csrf_token: '<?= generateCSRFToken() ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir cliente: ' + data.message);
            }
        });
    }
}

// Reset modal when closed
document.getElementById('clientModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clientForm').reset();
    document.getElementById('clientId').value = '';
    document.querySelector('#clientModal .modal-title').textContent = 'Novo Cliente';
});

function toggleLoginCredentials() {
    const section = document.getElementById('loginCredentialsSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

function createClientLogin() {
    const clientId = document.getElementById('clientId').value;
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    if (!clientId) {
        alert('Salve o cliente primeiro antes de criar as credenciais de acesso.');
        return;
    }
    
    if (!email || !password) {
        alert('Email e senha são obrigatórios.');
        return;
    }
    
    if (password.length < 6) {
        alert('A senha deve ter pelo menos 6 caracteres.');
        return;
    }
    
    const formData = new FormData();
    formData.append('client_id', clientId);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('actions/create-client-login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Credenciais de acesso criadas com sucesso! O cliente já pode acessar o portal.');
            document.getElementById('loginCredentialsSection').style.display = 'none';
            document.getElementById('loginEmail').value = '';
            document.getElementById('loginPassword').value = '';
        } else {
            alert('Erro ao criar credenciais: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro de comunicação: ' + error);
    });
}
</script>
