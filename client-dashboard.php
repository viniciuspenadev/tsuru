<?php
require_once 'config/config.php';
require_once 'classes/ClientAuth.php';
require_once 'classes/ClientDashboard.php';

$clientAuth = new ClientAuth();
$clientAuth->requireLogin();

$dashboard = new ClientDashboard();
$clientId = $_SESSION['client_id'];

// Obter estatísticas do cliente
$stats = $dashboard->getClientStats($clientId);

// Filtros
$filters = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Paginação
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$invoices = $dashboard->getClientInvoices($clientId, $limit, $offset, $filters);
$totalInvoices = $dashboard->countClientInvoices($clientId, $filters);
$totalPages = ceil($totalInvoices / $limit);

// Definir variáveis para o layout
$pageTitle = "Minhas Faturas - " . APP_NAME;
$pageHeader = "Minhas Faturas";
$pageSubtitle = "Acompanhe suas faturas e vencimentos";

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
                        <h6 class="text-muted mb-1">Total de Faturas</h6>
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
                        <h6 class="text-muted mb-1">Valor Total</h6>
                        <h3 class="mb-0">R$ <?= number_format($stats['total_billed'], 2, ',', '.') ?></h3>
                    </div>
                    <div class="icon-box bg-light-success">
                        <i class="bi bi-currency-dollar text-success"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-success"><?= $stats['status_paid'] ?? 0 ?></span>
                    <span class="text-muted ms-1">pagas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Vencimento Próximo</h6>
                        <h3 class="mb-0 <?= $stats['due_soon'] > 0 ? 'text-warning' : 'text-success' ?>">
                            <?= $stats['due_soon'] ?>
                        </h3>
                    </div>
                    <div class="icon-box bg-light-warning">
                        <i class="bi bi-calendar-check text-warning"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted">próximos 7 dias</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Faturas Vencidas</h6>
                        <h3 class="mb-0 <?= $stats['overdue'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $stats['overdue'] ?>
                        </h3>
                    </div>
                    <div class="icon-box bg-light-danger">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted">em atraso</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Última Fatura -->
<?php if ($stats['last_invoice']): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>Última Fatura</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Número:</strong><br>
                    <?= htmlspecialchars($stats['last_invoice']['invoice_number']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Data Emissão:</strong><br>
                    <?= date('d/m/Y', strtotime($stats['last_invoice']['issue_date'])) ?>
                </div>
                <div class="col-md-3">
                    <strong>Valor:</strong><br>
                    R$ <?= number_format($stats['last_invoice']['total_receivable'], 2, ',', '.') ?>
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong><br>
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
                    <span class="badge <?= $statusClass[$stats['last_invoice']['status']] ?>">
                        <?= $statusText[$stats['last_invoice']['status']] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                </div>
                <div class="col-md-3">
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
                    <a href="client-dashboard.php" class="btn btn-outline-secondary">
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
        <h5 class="mb-0">Minhas Faturas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data Emissão</th>
                        <th>Data Vencimento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th width="100">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($inv['due_date'])) ?>
                                <?php if ($inv['due_date'] < date('Y-m-d') && in_array($inv['status'], ['SENT', 'DRAFT'])): ?>
                                    <span class="badge bg-danger ms-1">Vencida</span>
                                <?php elseif ($inv['due_date'] <= date('Y-m-d', strtotime('+7 days')) && in_array($inv['status'], ['SENT', 'DRAFT'])): ?>
                                    <span class="badge bg-warning ms-1">Próximo</span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($inv['total_receivable'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $statusClass[$inv['status']] ?>">
                                    <?= $statusText[$inv['status']] ?>
                                </span>
                            </td>
                            <td>
                                <a href="client-invoice-view.php?id=<?= $inv['id'] ?>"
                                    class="btn btn-sm btn-outline-primary" title="Visualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
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
                            <a class="page-link"
                                href="?page=<?= $i ?>&date_from=<?= urlencode($filters['date_from']) ?>
                                    &date_to=<?= urlencode($filters['date_to']) ?>
                                    &status=<?= urlencode($filters['status']) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?> <!-- ← correção -->

                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php
// Obter o conteúdo do buffer e limpá-lo
$content = ob_get_clean();

// Incluir o layout do cliente
include 'includes/client-layout.php';
?>

<style>
    .icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .icon-box i {
        font-size: 19px;
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