<?php
require_once 'config/config.php';
require_once 'classes/Invoice.php';
require_once 'classes/Client.php';
require_once 'classes/FinancialControl.php';

requireLogin();

$invoice = new Invoice();
$client = new Client();
$financial = new FinancialControl();

// Obter estatísticas reais
$stats = $financial->getDashboardStats();

// Filtros
$filters = [
    'client_id' => $_GET['client_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Paginação
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$invoices = $invoice->findAll($limit, $offset, $filters);
$clients = $client->findAll(100);

// Definir variáveis para o layout
$pageTitle = "Dashboard - " . APP_NAME;
$pageHeader = "Dashboard";
$pageSubtitle = "Visão geral das faturas e atividades recentes";
$pageActions = '
    <a href="invoice-form.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nova Fatura
    </a>
';

// Iniciar buffer de saída para o conteúdo
ob_start();
?>

<!-- Cards de Resumo -->
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Faturas Emitidas</h6>
                        <h3 class="mb-0"><?= number_format($stats['total_invoices']) ?></h3>
                    </div>
                    <div class="icon-box bg-light-primary">
                        <i class="bi bi-receipt text-primary"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-info"><?= $stats['status_draft'] ?? 0 ?> rascunhos</span>
                    <span class="text-muted ms-1">• <?= $stats['status_sent'] ?? 0 ?> enviadas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Valor Faturado</h6>
                        <h3 class="mb-0">R$ <?= number_format($stats['total_billed'], 2, ',', '.') ?></h3>
                    </div>
                    <div class="icon-box bg-light-success">
                        <i class="bi bi-currency-dollar text-success"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success">R$ <?= number_format($stats['total_received'], 2, ',', '.') ?></span>
                    <span class="text-muted ms-1">recebido</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Lucro/Prejuízo</h6>
                        <h3 class="mb-0 <?= $stats['total_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            R$ <?= number_format($stats['total_profit'], 2, ',', '.') ?>
                        </h3>
                    </div>
                    <div class="icon-box bg-light-<?= $stats['total_profit'] >= 0 ? 'success' : 'danger' ?>">
                        <i class="bi bi-graph-<?= $stats['total_profit'] >= 0 ? 'up' : 'down' ?> text-<?= $stats['total_profit'] >= 0 ? 'success' : 'danger' ?>"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-warning">R$ <?= number_format($stats['total_paid'], 2, ',', '.') ?></span>
                    <span class="text-muted ms-1">em despesas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Vencimentos Próximos</h6>
                        <h3 class="mb-0"><?= $stats['due_soon'] ?></h3>
                    </div>
                    <div class="icon-box bg-light-warning">
                        <i class="bi bi-calendar-check text-warning"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-info"><?= $stats['active_clients'] ?> clientes</span>
                    <span class="text-muted ms-1">ativos este mês</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Filtros</h5>
        <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse show" id="filterCollapse">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <select name="client_id" class="form-select">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filters['client_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="DRAFT" <?= $filters['status'] == 'DRAFT' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="SENT" <?= $filters['status'] == 'SENT' ? 'selected' : '' ?>>Enviada</option>
                        <option value="PAID" <?= $filters['status'] == 'PAID' ? 'selected' : '' ?>>Paga</option>
                        <option value="CANCELLED" <?= $filters['status'] == 'CANCELLED' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabela de Faturas -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Faturas Recentes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="invoicesTable">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Data Emissão</th>
                        <th>Data Vencimento</th>
                        <th>Valor Faturado</th>
                        <th>Recebido</th>
                        <th>Status</th>
                        <th>Status Financeiro</th>
                        <th width="150">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                            <td><?= htmlspecialchars($inv['client_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($inv['due_date'])) ?></td>
                            <td>R$ <?= number_format($inv['total_receivable'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($inv['total_received'] ?? 0, 2, ',', '.') ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'DRAFT' => 'bg-secondary',
                                    'SENT' => 'bg-primary',
                                    'PAID' => 'bg-success',
                                    'CANCELLED' => 'bg-danger'
                                ];
                                $statusText = [
                                    'DRAFT' => 'Rascunho',
                                    'SENT' => 'Enviada',
                                    'PAID' => 'Paga',
                                    'CANCELLED' => 'Cancelada'
                                ];
                                ?>
                                <span class="badge <?= $statusClass[$inv['status']] ?>">
                                    <?= $statusText[$inv['status']] ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $financialStatusClass = [
                                    'PENDING' => 'bg-secondary',
                                    'PARTIAL' => 'bg-warning',
                                    'COMPLETED' => 'bg-success',
                                    'LOSS' => 'bg-danger'
                                ];
                                $financialStatusText = [
                                    'PENDING' => 'Pendente',
                                    'PARTIAL' => 'Parcial',
                                    'COMPLETED' => 'Completo',
                                    'LOSS' => 'Prejuízo'
                                ];
                                $financialStatus = $inv['financial_status'] ?? 'PENDING';
                                ?>
                                <span class="badge <?= $financialStatusClass[$financialStatus] ?>">
                                    <?= $financialStatusText[$financialStatus] ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="invoice-preview.php?id=<?= $inv['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="invoice-form.php?id=<?= $inv['id'] ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteInvoice(<?= $inv['id'] ?>)" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Obter o conteúdo do buffer e limpá-lo
$content = ob_get_clean();

// Incluir o layout
include 'includes/layout.php';
?>

<style>
.icon-box {
    width: 38px; /* 48px * 0.8 */
    height: 38px; /* 48px * 0.8 */
    border-radius: 10px; /* 12px * 0.8 */
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-box i {
    font-size: 19px; /* 24px * 0.8 */
}

.bg-light-primary {
    background-color: rgba(67, 97, 238, 0.1);
}

.bg-light-success {
    background-color: rgba(76, 201, 240, 0.1);
}

.bg-light-info {
    background-color: rgba(72, 149, 239, 0.1);
}

.bg-light-warning {
    background-color: rgba(248, 150, 30, 0.1);
}

.bg-light-danger {
    background-color: rgba(247, 37, 133, 0.1);
}
</style>

<script>
function deleteInvoice(id) {
    if (confirm('Tem certeza que deseja excluir esta fatura?')) {
        fetch('actions/delete-invoice.php', {
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
                alert('Erro ao excluir fatura: ' + data.message);
            }
        });
    }
}
</script>
