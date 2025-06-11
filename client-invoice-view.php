<?php
require_once 'config/config.php';
require_once 'classes/ClientAuth.php';
require_once 'classes/Invoice.php';

$clientAuth = new ClientAuth();
$clientAuth->requireLogin();

$invoiceId = intval($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    header('Location: client-dashboard.php');
    exit;
}

$invoice = new Invoice();
$invoiceData = $invoice->findById($invoiceId);

if (!$invoiceData || $invoiceData['client_id'] != $_SESSION['client_id']) {
    header('Location: client-dashboard.php');
    exit;
}

// --- Cálculos para o Resumo Financeiro Superior ---
$totalRecebidoDemonstrativoBRL = 0;
if (!empty($invoiceData['received_items'])) {
    foreach ($invoiceData['received_items'] as $item) {
        if ($item['currency'] === 'BRL') {
            $totalRecebidoDemonstrativoBRL += floatval($item['amount']);
        }
    }
}

$totalPagoDemonstrativoBRL = 0;
if (!empty($invoiceData['paid_items'])) {
    foreach ($invoiceData['paid_items'] as $item) {
        if ($item['currency'] === 'BRL') {
            $totalPagoDemonstrativoBRL += floatval($item['amount']);
        }
    }
}
$saldoFinalDemonstrativo = $totalRecebidoDemonstrativoBRL - $totalPagoDemonstrativoBRL;

// --- Lógica de Estilização para "Saldo a Devolver (Fatura)" ---
$saldoDevolverValor = floatval($invoiceData['total_refund'] ?? 0);
$saldoDevolverDisplayClass = '';
$saldoDevolverIcon = '';

if ($saldoDevolverValor > 0) {
    if ($totalRecebidoDemonstrativoBRL <= $totalPagoDemonstrativoBRL) {
        $saldoDevolverDisplayClass = 'text-success fw-bold';
        $saldoDevolverIcon = '<i class="bi bi-check-circle-fill text-success me-1"></i>';
    } else {
        $saldoDevolverDisplayClass = 'text-warning fw-bold';
        $saldoDevolverIcon = '<i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>';
    }
}

// --- Variáveis para o Layout ---
$pageTitle = "Fatura " . $invoiceData['invoice_number'] . " - " . APP_NAME;
$pageHeader = "Fatura #" . htmlspecialchars($invoiceData['invoice_number']);
$statusColors = ['DRAFT' => 'bg-secondary', 'SENT' => 'bg-primary', 'PAID' => 'bg-success', 'CANCELLED' => 'bg-danger'];
$statusTexts = ['DRAFT' => 'RASCUNHO', 'SENT' => 'ENVIADA', 'PAID' => 'PAGA', 'CANCELLED' => 'CANCELADA'];
$pageSubtitle = 'Status: <span class="badge ' . ($statusColors[$invoiceData['status']] ?? 'bg-light text-dark') . '">' . ($statusTexts[$invoiceData['status']] ?? 'N/A') . '</span>';

$pageActions = '
    <div class="btn-group">
        <a href="client-dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
        <button onclick="printInvoice()" class="btn btn-success"><i class="bi bi-printer"></i> Imprimir</button>
        <a href="generate-pdf.php?id=' . $invoiceData['id'] . '" class="btn btn-danger" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
    </div>
';

ob_start();
?>

<!-- Resumo Financeiro Superior -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Valor Total</h6>
                <h4 class="mb-0">R$ <?= number_format(floatval($invoiceData['total_receivable']), 2, ',', '.') ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Data Vencimento</h6>
                <h4 class="mb-0 <?= $invoiceData['due_date'] < date('Y-m-d') ? 'text-danger' : 'text-success' ?>">
                    <?= date('d/m/Y', strtotime($invoiceData['due_date'])) ?>
                </h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Status</h6>
                <h4 class="mb-0">
                    <span class="badge <?= $statusColors[$invoiceData['status']] ?> fs-6">
                        <?= $statusTexts[$invoiceData['status']] ?>
                    </span>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3 no-print" id="invoiceTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="fatura-tab" data-bs-toggle="tab" data-bs-target="#fatura-content" type="button" role="tab" aria-controls="fatura-content" aria-selected="true">
            <i class="bi bi-receipt-cutoff"></i> Fatura (Documento)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos-content" type="button" role="tab" aria-controls="documentos-content" aria-selected="false">
            <i class="bi bi-paperclip"></i> Documentos Anexados
        </button>
    </li>
</ul>
<div class="tab-content" id="invoiceTabsContent">
    <!-- Tab 1: Fatura (Documento para Cliente/Impressão) -->
    <div class="tab-pane fade show active" id="fatura-content" role="tabpanel" aria-labelledby="fatura-tab">
        <div id="printable-invoice-area">
            <!-- Cabeçalho da Fatura -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <h2 class="text-center text-primary fw-bold mt-3 mb-3">FATURA Nº <?= htmlspecialchars($invoiceData['invoice_number']) ?></h2>
                    </div>
                </div>

                <!-- Informações Cliente e Fatura -->
                <div class="row">
                    <div class="col-md-8 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light py-2">
                                <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Dados do Cliente</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Cliente:</strong> <?= htmlspecialchars($invoiceData['client_name']) ?></p>
                                <p class="mb-1"><strong>CNPJ:</strong> <?= htmlspecialchars($invoiceData['cnpj']) ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($invoiceData['client_email']) ?></p>
                                <p class="mb-1"><strong>Endereço:</strong> <?= htmlspecialchars($invoiceData['address']) ?>, <?= htmlspecialchars($invoiceData['city']) ?> - <?= htmlspecialchars($invoiceData['state']) ?>, CEP: <?= htmlspecialchars($invoiceData['zip']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light py-2">
                                <h5 class="mb-0"><i class="bi bi-file-earmark-text-fill me-2"></i>Informações da Fatura</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Data Emissão:</strong> <?= date('d/m/Y', strtotime($invoiceData['issue_date'])) ?></p>
                                <p class="mb-1"><strong>Termo:</strong> <?= $invoiceData['term_days'] ?> dias</p>
                                <p class="mb-1"><strong>Data Vencimento:</strong> <?= date('d/m/Y', strtotime($invoiceData['due_date'])) ?></p>
                                <p class="mb-1"><strong>Ref. Demonstrativo:</strong> <?= htmlspecialchars($invoiceData['demonstrative']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados de Embarque -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Dados de Embarque</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">MAWB:</small><strong><?= htmlspecialchars($invoiceData['mbl'] ?: '-') ?></strong></div>
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">HAWB:</small><strong><?= htmlspecialchars($invoiceData['hbl'] ?: '-') ?></strong></div>
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">Origem:</small><strong><?= htmlspecialchars($invoiceData['origin'] ?: '-') ?></strong></div>
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">Destino:</small><strong><?= htmlspecialchars($invoiceData['destination'] ?: '-') ?></strong></div>
                            <div class="col-md-6 mb-2"><small class="text-muted d-block">Exportador:</small><strong><?= htmlspecialchars($invoiceData['exporter'] ?: '-') ?></strong></div>
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">Incoterm:</small><strong><?= htmlspecialchars($invoiceData['incoterm'] ?: '-') ?></strong></div>
                            <div class="col-md-3 mb-2"><small class="text-muted d-block">Via Mercadoria:</small><strong><?= htmlspecialchars($invoiceData['transport_mode'] ?: '-') ?></strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">Peso Bruto:</small><strong><?= number_format(floatval($invoiceData['gross_weight']), 3, ',', '.') ?> kg</strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">Peso Cubado:</small><strong><?= number_format(floatval($invoiceData['cubic_weight']), 3, ',', '.') ?> kg</strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">ETO:</small><strong><?= $invoiceData['eto'] ? date('d/m/Y', strtotime($invoiceData['eto'])) : '-' ?></strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">ETA:</small><strong><?= $invoiceData['eta'] ? date('d/m/Y', strtotime($invoiceData['eta'])) : '-' ?></strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">P.O.:</small><strong><?= htmlspecialchars($invoiceData['po_number'] ?: '-') ?></strong></div>
                            <div class="col-md-2 mb-2"><small class="text-muted d-block">Invoice Ref.:</small><strong><?= htmlspecialchars($invoiceData['invoice_ref'] ?: '-') ?></strong></div>
                        </div>
                    </div>
                </div>

                <!-- Demonstrativo de Valores de (Numerário) -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Demonstrativo de Valores de (Numerário)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($invoiceData['received_items'])): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Descrição</th>
                                            <th>Moeda</th>
                                            <th class="text-end pe-3">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoiceData['received_items'] as $item): ?>
                                            <tr>
                                                <td class="ps-3"><?= htmlspecialchars($item['description']) ?></td>
                                                <td><?= htmlspecialchars($item['currency']) ?></td>
                                                <td class="text-end pe-3"><?= number_format(floatval($item['amount']), 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end fw-bold ps-3">Total Demonstrativo de Numerário (BRL):</th>
                                            <th class="text-end fw-bold pe-3">R$ <?= number_format($totalRecebidoDemonstrativoBRL, 2, ',', '.') ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?><p class="text-muted p-3">Nenhum item neste demonstrativo.</p><?php endif; ?>
                    </div>
                </div>

                <!-- Demonstrativo de valor pago -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Demonstrativo de valor pago</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($invoiceData['paid_items'])): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Descrição</th>
                                            <th>Moeda</th>
                                            <th class="text-end pe-3">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoiceData['paid_items'] as $item): ?>
                                            <tr>
                                                <td class="ps-3"><?= htmlspecialchars($item['description']) ?></td>
                                                <td><?= htmlspecialchars($item['currency']) ?></td>
                                                <td class="text-end pe-3"><?= number_format(floatval($item['amount']), 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end fw-bold ps-3">Total Demonstrativo de valor pago (BRL):</th>
                                            <th class="text-end fw-bold pe-3">R$ <?= number_format($totalPagoDemonstrativoBRL, 2, ',', '.') ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?><p class="text-muted p-3">Nenhum item neste demonstrativo.</p><?php endif; ?>
                    </div>
                </div>

                <!-- Fechamento e Dados Bancários -->
                <div class="row">
                    <div class="col-md-7 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light py-2">
                                <h5 class="mb-0"><i class="bi bi-calculator-fill me-2"></i>Fechamento</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><small class="text-muted d-block">Fechamento Prazo:</small><strong><?= $invoiceData['closing_term'] ? date('d/m/Y', strtotime($invoiceData['closing_term'])) : '-' ?></strong></p>
                                <p class="mb-2"><small class="text-muted d-block">Paridade EUR x USD:</small><strong><?= number_format(floatval($invoiceData['eur_usd_rate']), 4, ',', '.') ?></strong></p>
                                <p class="mb-0"><small class="text-muted d-block">Saldo a Devolver (Fatura):</small>
                                    <strong class="<?= $saldoDevolverDisplayClass ?>"><?= $saldoDevolverIcon ?> R$ <?= number_format($saldoDevolverValor, 2, ',', '.') ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-light py-2">
                                <h5 class="mb-0"><i class="bi bi-bank me-2"></i>Dados Bancários</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Delphi Fretes Internacionais Ltda</strong></p>
                                <p class="mb-1"><small>CNPJ: 24.768.038/0001-88</small></p>
                                <p class="mb-1"><strong>Banco:</strong> Itaú Unibanco S/A</p>
                                <p class="mb-0"><strong>Agência:</strong> 0500 &nbsp;&nbsp;<strong>Conta:</strong> 93570-2</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Tab 2: Documentos Anexados -->
    <div class="tab-pane fade" id="documentos-content" role="tabpanel" aria-labelledby="documentos-tab">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center bg-light">
                <h5 class="mb-0"><i class="bi bi-paperclip me-2"></i>Documentos Anexados</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#documentModal">
                    <i class="bi bi-plus-circle"></i> Anexar Documento
                </button>
            </div>
            <div class="card-body">
                <div id="documentsList">
                    <!-- Lista de documentos será carregada aqui via JavaScript -->
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-file-earmark-text display-4"></i>
                        <p class="mt-2">Nenhum documento anexado ainda.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $content = ob_get_clean();
    include 'includes/client-layout.php';
    ?>

    <script>
// Função para carregar documentos
function loadDocuments() {
    fetch('actions/get-documents.php?invoice_id=<?= $invoiceId ?>')
    .then(response => response.json())
    .then(data => {
        const documentsList = document.getElementById('documentsList');
        
        if (data.success && data.documents.length > 0) {
            let html = '<div class="row">';
            
            data.documents.forEach(doc => {
                const fileIcon = getFileIcon(doc.file_extension);
                const categoryBadge = getCategoryBadge(doc.category);
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <i class="bi ${fileIcon} display-6 text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1">${doc.description}</h6>
                                        <p class="card-text small text-muted mb-2">
                                            ${categoryBadge}
                                            <span class="ms-2">${formatFileSize(doc.file_size)}</span>
                                        </p>
                                        <p class="card-text small">
                                            <i class="bi bi-calendar3"></i> ${formatDate(doc.created_at)}
                                        </p>
                                        ${doc.notes ? `<p class="card-text small text-muted">${doc.notes}</p>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100">
                                    <a href="actions/download-document.php?id=${doc.id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${doc.id})">
                                        <i class="bi bi-trash"></i> Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            documentsList.innerHTML = html;
        } else {
            documentsList.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="bi bi-file-earmark-text display-4"></i>
                    <p class="mt-2">Nenhum documento anexado ainda.</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar documentos:', error);
    });
}

        function printInvoice() {
            const printStyle = document.createElement('style');
            printStyle.textContent = `
        @media print {
            body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
            .btn-group, .mobile-header, .sidebar, .page-header, .alert, .modal, .modal-backdrop { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .page-content { padding: 0 !important; }
            #printable-invoice-area { 
                margin: 0; 
                padding: 1cm;
                width: 100%; 
                box-shadow: none !important; 
                border: none !important;
            }
            .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; page-break-inside: avoid; }
            .card-header { background-color: #f8f9fa !important; border-bottom: 1px solid #dee2e6 !important; padding-top: 0.5rem !important; padding-bottom: 0.5rem !important;}
            .card-header h5 { font-size: 1.1rem; }
            .card-body { padding: 0.75rem !important; }
            .table { font-size: 9pt; }
            .table th, .table td { padding: 0.3rem 0.5rem !important; }
            .table-light th, .table-light td, .table-striped tbody tr:nth-of-type(odd) { background-color: #f8f9fa !important; }
            .table-striped tbody tr:nth-of-type(even) { background-color: #fff !important; }
            h1, h2, h3, h4, h5, h6 { color: #000 !important; }
            .text-primary { color: #0d6efd !important; }
            .text-success { color: #198754 !important; }
            .text-warning { color: #ffc107 !important; }
            .text-danger { color: #dc3545 !important; }
            .text-muted { color: #6c757d !important; }
            a { text-decoration: none !important; color: inherit !important; }
            img { max-width: 100% !important; height: auto; }
            hr { border-top: 1px solid #ccc !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    `;
            document.head.appendChild(printStyle);
            window.print();
            document.head.removeChild(printStyle);
        }
    </script>