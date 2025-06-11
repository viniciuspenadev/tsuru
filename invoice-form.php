<?php
require_once 'config/config.php';
require_once 'classes/Invoice.php';
require_once 'classes/Client.php';

requireLogin();

$invoice = new Invoice();
$client = new Client();

$isEdit = false;
$isClone = false;
$invoiceData = null;

// Verificar se é edição ou clonagem
if (isset($_GET['id'])) {
    $isEdit = true;
    $invoiceData = $invoice->findById($_GET['id']);
    if (!$invoiceData) {
        header('Location: dashboard.php');
        exit;
    }
} elseif (isset($_GET['clone'])) {
    $isClone = true;
    $invoiceData = $invoice->findById($_GET['clone']);
    if (!$invoiceData) {
        header('Location: dashboard.php');
        exit;
    }
    // Limpar dados para clonagem
    unset($invoiceData['id'], $invoiceData['invoice_number']);
    $invoiceData['issue_date'] = date('Y-m-d');
    $invoiceData['due_date'] = date('Y-m-d', strtotime('+30 days'));
    $invoiceData['status'] = 'DRAFT';
}

$clients = $client->findAll(1000);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } else {
        try {
            $data = [
                'client_id' => intval($_POST['client_id']),
                'issue_date' => $_POST['issue_date'],
                'due_date' => $_POST['due_date'],
                'term_days' => intval($_POST['term_days']),
                'demonstrative' => $_POST['demonstrative'],
                'mbl' => $_POST['mbl'],
                'hbl' => $_POST['hbl'],
                'origin' => $_POST['origin'],
                'destination' => $_POST['destination'],
                'incoterm' => $_POST['incoterm'],
                'transport_mode' => $_POST['transport_mode'],
                'exporter' => $_POST['exporter'],
                'gross_weight' => floatval($_POST['gross_weight']),
                'cubic_weight' => floatval($_POST['cubic_weight']),
                'eto' => $_POST['eto'] ?: null,
                'eta' => $_POST['eta'] ?: null,
                'po_number' => $_POST['po_number'],
                'invoice_ref' => $_POST['invoice_ref'],
                'total_receivable' => floatval($_POST['total_receivable']),
                'total_refund' => floatval($_POST['total_refund']),
                'total_paid_items' => floatval($_POST['total_paid_items']),
                'eur_usd_rate' => floatval($_POST['eur_usd_rate']),
                'closing_term' => $_POST['closing_term'],
                'status' => $_POST['status'],
                'received_items' => [],
                'paid_items' => []
            ];
            
            // Processar itens recebidos
            if (!empty($_POST['received_description'])) {
                foreach ($_POST['received_description'] as $index => $description) {
                    if (!empty($description)) {
                        $data['received_items'][] = [
                            'description' => $description,
                            'currency' => $_POST['received_currency'][$index],
                            'amount' => floatval($_POST['received_amount'][$index])
                        ];
                    }
                }
            }
            
            // Processar itens pagos
            if (!empty($_POST['paid_description'])) {
                foreach ($_POST['paid_description'] as $index => $description) {
                    if (!empty($description)) {
                        $data['paid_items'][] = [
                            'description' => $description,
                            'currency' => $_POST['paid_currency'][$index],
                            'amount' => floatval($_POST['paid_amount'][$index])
                        ];
                    }
                }
            }
            
            if ($isEdit) {
                $result = $invoice->update($invoiceData['id'], $data);
                $invoiceId = $invoiceData['id'];
            } else {
                $invoiceId = $invoice->create($data);
                $result = $invoiceId > 0;
            }
            
            if ($result) {
                header("Location: invoice-preview.php?id={$invoiceId}");
                exit;
            } else {
                $error = 'Erro ao salvar fatura.';
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Definir variáveis para o layout
$pageTitle = ($isEdit ? 'Editar' : ($isClone ? 'Clonar' : 'Nova')) . " Fatura - " . APP_NAME;
$pageHeader = ($isEdit ? 'Editar' : ($isClone ? 'Clonar' : 'Nova')) . " Fatura";
$pageSubtitle = "Preencha os dados para " . ($isEdit ? 'atualizar' : 'criar') . " a fatura";
$pageActions = '
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
';

// Definir alerta se houver erro
if (isset($error)) {
    $alertType = 'danger';
    $alertMessage = $error;
}

// Iniciar buffer de saída para o conteúdo
ob_start();
?>

<form method="POST" class="invoice-form">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    
    <!-- Dados Básicos -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Dados da Invoice</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cliente *</label>
                    <select name="client_id" class="form-select" required>
                        <option value="">Selecione um cliente</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" 
                                    <?= ($invoiceData['client_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Emissão *</label>
                    <input type="date" name="issue_date" class="form-control" 
                           value="<?= $invoiceData['issue_date'] ?? date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Vencimento *</label>
                    <input type="date" name="due_date" class="form-control" 
                           value="<?= $invoiceData['due_date'] ?? date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Termo (dias)</label>
                    <input type="number" name="term_days" class="form-control" 
                           value="<?= $invoiceData['term_days'] ?? 30 ?>" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="DRAFT" <?= ($invoiceData['status'] ?? 'DRAFT') == 'DRAFT' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="SENT" <?= ($invoiceData['status'] ?? '') == 'SENT' ? 'selected' : '' ?>>Enviada</option>
                        <option value="PAID" <?= ($invoiceData['status'] ?? '') == 'PAID' ? 'selected' : '' ?>>Paga</option>
                        <option value="CANCELLED" <?= ($invoiceData['status'] ?? '') == 'CANCELLED' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Demonstrativo Numerário</label>
                    <textarea name="demonstrative" class="form-control" rows="2"><?= htmlspecialchars($invoiceData['demonstrative'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dados de Embarque -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-truck"></i> Dados de Embarque</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">MAWB</label>
                    <input type="text" name="mbl" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['mbl'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">HAWB</label>
                    <input type="text" name="hbl" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['hbl'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Origem</label>
                    <input type="text" name="origin" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['origin'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Destino</label>
                    <input type="text" name="destination" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['destination'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Exportador</label>
                    <input type="text" name="exporter" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['exporter'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Peso Bruto (kg)</label>
                    <input type="number" step="0.001" name="gross_weight" class="form-control" 
                           value="<?= $invoiceData['gross_weight'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Peso Cubado (kg)</label>
                    <input type="number" step="0.001" name="cubic_weight" class="form-control" 
                           value="<?= $invoiceData['cubic_weight'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Incoterm</label>
                    <select name="incoterm" class="form-select">
                        <option value="FOB" <?= ($invoiceData['incoterm'] ?? 'FOB') == 'FOB' ? 'selected' : '' ?>>FOB</option>
                        <option value="CIF" <?= ($invoiceData['incoterm'] ?? '') == 'CIF' ? 'selected' : '' ?>>CIF</option>
                        <option value="EXW" <?= ($invoiceData['incoterm'] ?? '') == 'EXW' ? 'selected' : '' ?>>EXW</option>
                        <option value="DDP" <?= ($invoiceData['incoterm'] ?? '') == 'DDP' ? 'selected' : '' ?>>DDP</option>
                        <option value="DAP" <?= ($invoiceData['incoterm'] ?? '') == 'DAP' ? 'selected' : '' ?>>DAP</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Via Mercadoria</label>
                    <select name="transport_mode" class="form-select">
                        <option value="MARITIMO" <?= ($invoiceData['transport_mode'] ?? 'MARITIMO') == 'MARITIMO' ? 'selected' : '' ?>>Marítimo</option>
                        <option value="AEREO" <?= ($invoiceData['transport_mode'] ?? '') == 'AEREO' ? 'selected' : '' ?>>Aéreo</option>
                        <option value="RODOVIARIO" <?= ($invoiceData['transport_mode'] ?? '') == 'RODOVIARIO' ? 'selected' : '' ?>>Rodoviário</option>
                        <option value="FERROVIARIO" <?= ($invoiceData['transport_mode'] ?? '') == 'FERROVIARIO' ? 'selected' : '' ?>>Ferroviário</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">ETO</label>
                    <input type="date" name="eto" class="form-control" 
                           value="<?= $invoiceData['eto'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">ETA</label>
                    <input type="date" name="eta" class="form-control" 
                           value="<?= $invoiceData['eta'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">P.O.</label>
                    <input type="text" name="po_number" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['po_number'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Invoice</label>
                    <input type="text" name="invoice_ref" class="form-control" 
                           value="<?= htmlspecialchars($invoiceData['invoice_ref'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Demonstrativo de Valores Recebidos -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cash-coin text-success"></i> Demonstrativo de Valores Recebidos</h5>
            <button type="button" class="btn btn-sm btn-success" onclick="addReceivedItem()">
                <i class="bi bi-plus-circle"></i> Adicionar Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="receivedItemsTable">
                    <thead>
                        <tr>
                            <th width="50%">Descrição</th>
                            <th width="15%">Moeda</th>
                            <th width="25%">Valor</th>
                            <th width="10%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($invoiceData['received_items'])): ?>
                            <?php foreach ($invoiceData['received_items'] as $item): ?>
                                <tr>
                                    <td>
                                        <input type="text" name="received_description[]" class="form-control" 
                                               value="<?= htmlspecialchars($item['description']) ?>" required>
                                    </td>
                                    <td>
                                        <select name="received_currency[]" class="form-select" required>
                                            <option value="BRL" <?= $item['currency'] == 'BRL' ? 'selected' : '' ?>>BRL</option>
                                            <option value="USD" <?= $item['currency'] == 'USD' ? 'selected' : '' ?>>USD</option>
                                            <option value="EUR" <?= $item['currency'] == 'EUR' ? 'selected' : '' ?>>EUR</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="received_amount[]" class="form-control received-amount" 
                                               value="<?= $item['amount'] ?>" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>
                                    <input type="text" name="received_description[]" class="form-control" placeholder="Descrição do valor recebido">
                                </td>
                                <td>
                                    <select name="received_currency[]" class="form-select">
                                        <option value="BRL">BRL</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="received_amount[]" class="form-control received-amount" placeholder="0,00">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Demonstrativo de Valores Pagos -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-credit-card text-warning"></i> Demonstrativo de Valores Pagos</h5>
            <button type="button" class="btn btn-sm btn-warning" onclick="addPaidItem()">
                <i class="bi bi-plus-circle"></i> Adicionar Item
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="paidItemsTable">
                    <thead>
                        <tr>
                            <th width="50%">Descrição</th>
                            <th width="15%">Moeda</th>
                            <th width="25%">Valor</th>
                            <th width="10%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($invoiceData['paid_items'])): ?>
                            <?php foreach ($invoiceData['paid_items'] as $item): ?>
                                <tr>
                                    <td>
                                        <input type="text" name="paid_description[]" class="form-control" 
                                               value="<?= htmlspecialchars($item['description']) ?>" required>
                                    </td>
                                    <td>
                                        <select name="paid_currency[]" class="form-select" required>
                                            <option value="BRL" <?= $item['currency'] == 'BRL' ? 'selected' : '' ?>>BRL</option>
                                            <option value="USD" <?= $item['currency'] == 'USD' ? 'selected' : '' ?>>USD</option>
                                            <option value="EUR" <?= $item['currency'] == 'EUR' ? 'selected' : '' ?>>EUR</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="paid_amount[]" class="form-control paid-amount" 
                                               value="<?= $item['amount'] ?>" required>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>
                                    <input type="text" name="paid_description[]" class="form-control" placeholder="Descrição do valor pago">
                                </td>
                                <td>
                                    <select name="paid_currency[]" class="form-select">
                                        <option value="BRL">BRL</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="paid_amount[]" class="form-control paid-amount" placeholder="0,00">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Fechamento -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calculator"></i> Fechamento</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Saldo a Receber (BRL)</label>
                    <input type="number" step="0.01" name="total_receivable" id="totalReceivable" class="form-control" 
                           value="<?= $invoiceData['total_receivable'] ?? '0' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Saldo a Devolver (BRL)</label>
                    <input type="number" step="0.01" name="total_refund" id="totalRefund" class="form-control" 
                           value="<?= $invoiceData['total_refund'] ?? '0' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Pago (BRL)</label>
                    <input type="number" step="0.01" name="total_paid_items" id="totalPaidItems" class="form-control" 
                           value="<?= $invoiceData['total_paid_items'] ?? '0' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Paridade EUR x USD</label>
                    <input type="number" step="0.0001" name="eur_usd_rate" class="form-control" 
                           value="<?= $invoiceData['eur_usd_rate'] ?? '1.0000' ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Fechamento Prazo</label>
                    <input type="date" name="closing_term" class="form-control" 
                           value="<?= $invoiceData['closing_term'] ?? '' ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botões -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Salvar Fatura
        </button>
    </div>
</form>

<?php
// Obter o conteúdo do buffer e limpá-lo
$content = ob_get_clean();

// Incluir o layout
include 'includes/layout.php';
?>

<script>
    function addReceivedItem() {
        const tbody = document.querySelector('#receivedItemsTable tbody');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <input type="text" name="received_description[]" class="form-control" placeholder="Descrição do valor recebido" required>
            </td>
            <td>
                <select name="received_currency[]" class="form-select" required>
                    <option value="BRL">BRL</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" name="received_amount[]" class="form-control received-amount" placeholder="0,00" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
        
        // Adicionar evento para calcular totais
        newRow.querySelector('.received-amount').addEventListener('change', calculateTotals);
    }
    
    function addPaidItem() {
        const tbody = document.querySelector('#paidItemsTable tbody');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <input type="text" name="paid_description[]" class="form-control" placeholder="Descrição do valor pago" required>
            </td>
            <td>
                <select name="paid_currency[]" class="form-select" required>
                    <option value="BRL">BRL</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" name="paid_amount[]" class="form-control paid-amount" placeholder="0,00" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(newRow);
        
        // Adicionar evento para calcular totais
        newRow.querySelector('.paid-amount').addEventListener('change', calculateTotals);
    }
    
    function removeItem(button) {
        const row = button.closest('tr');
        const tbody = row.parentNode;
        if (tbody.children.length > 1) {
            row.remove();
            calculateTotals();
        } else {
            // Limpar a linha se for a única
            row.querySelectorAll('input, select').forEach(input => input.value = '');
            calculateTotals();
        }
    }
    
    // Auto-calcular data de vencimento baseada no termo
    document.querySelector('input[name="issue_date"]').addEventListener('change', updateDueDate);
    document.querySelector('input[name="term_days"]').addEventListener('change', updateDueDate);
    
    function updateDueDate() {
        const issueDate = document.querySelector('input[name="issue_date"]').value;
        const termDays = parseInt(document.querySelector('input[name="term_days"]').value) || 30;
        
        if (issueDate) {
            const dueDate = new Date(issueDate);
            dueDate.setDate(dueDate.getDate() + termDays);
            document.querySelector('input[name="due_date"]').value = dueDate.toISOString().split('T')[0];
        }
    }
    
    // Calcular totais baseados nos itens
    function calculateTotals() {
        let totalReceivedBRL = 0;
        let totalPaidBRL = 0;
        
        // Calcular total recebido
        document.querySelectorAll('#receivedItemsTable tbody tr').forEach(row => {
            const currency = row.querySelector('select[name="received_currency[]"]').value;
            const amount = parseFloat(row.querySelector('input[name="received_amount[]"]').value) || 0;
            
            if (currency === 'BRL') {
                totalReceivedBRL += amount;
            }
        });
        
        // Calcular total pago
        document.querySelectorAll('#paidItemsTable tbody tr').forEach(row => {
            const currency = row.querySelector('select[name="paid_currency[]"]').value;
            const amount = parseFloat(row.querySelector('input[name="paid_amount[]"]').value) || 0;
            
            if (currency === 'BRL') {
                totalPaidBRL += amount;
            }
        });
        
        document.getElementById('totalReceivable').value = totalReceivedBRL.toFixed(2);
        document.getElementById('totalPaidItems').value = totalPaidBRL.toFixed(2);
    }
    
    // Adicionar evento de cálculo aos campos de valor
    document.querySelectorAll('.received-amount, .paid-amount').forEach(input => {
        input.addEventListener('change', calculateTotals);
    });
    
    // Calcular totais iniciais
    calculateTotals();
</script>
