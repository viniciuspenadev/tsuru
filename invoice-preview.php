<?php
require_once 'config/config.php';
require_once 'classes/Invoice.php';

requireLogin();

$invoiceId = intval($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
  header('Location: dashboard.php');
  exit;
}

$invoice = new Invoice();

$invoiceData = $invoice->findById($invoiceId);
if (!$invoiceData) {
  header('Location: dashboard.php');
  exit;
}

// --- Cálculos para o Resumo Financeiro Superior e Lógica do Saldo a Devolver ---
$totalRecebidoDemonstrativoBRL = 0;
if (!empty($invoiceData['received_items'])) {
  foreach ($invoiceData['received_items'] as $item) {
      if ($item['currency'] === 'BRL') {
          $totalRecebidoDemonstrativoBRL += floatval($item['amount']);
      }
      // TODO: Adicionar lógica de conversão se outras moedas precisarem ser somadas aqui
  }
}

$totalPagoDemonstrativoBRL = 0;
if (!empty($invoiceData['paid_items'])) {
  foreach ($invoiceData['paid_items'] as $item) {
      if ($item['currency'] === 'BRL') {
          $totalPagoDemonstrativoBRL += floatval($item['amount']);
      }
      // TODO: Adicionar lógica de conversão se outras moedas precisarem ser somadas aqui
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
$pageHeader = "Detalhes da Fatura #" . htmlspecialchars($invoiceData['invoice_number']);
$statusColors = ['DRAFT' => 'bg-secondary', 'SENT' => 'bg-primary', 'PAID' => 'bg-success', 'CANCELLED' => 'bg-danger'];
$statusTexts = ['DRAFT' => 'RASCUNHO', 'SENT' => 'ENVIADA', 'PAID' => 'PAGA', 'CANCELLED' => 'CANCELADA'];
$pageSubtitle = 'Status: <span class="badge ' . ($statusColors[$invoiceData['status']] ?? 'bg-light text-dark') . '">' . ($statusTexts[$invoiceData['status']] ?? 'N/A') . '</span>';

$pageActions = '
  <div class="btn-group">
      <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
      <a href="invoice-form.php?id=' . $invoiceData['id'] . '" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
      <a href="invoice-form.php?clone=' . $invoiceData['id'] . '" class="btn btn-secondary"><i class="bi bi-files"></i> Clonar</a>
  </div>
  <div class="btn-group ms-2">
      <button onclick="printInvoice()" class="btn btn-success"><i class="bi bi-printer"></i> Imprimir</button>
      <a href="generate-pdf.php?id=' . $invoiceData['id'] . '" class="btn btn-danger" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
      <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="bi bi-envelope"></i> Email</button>
  </div>
';

ob_start();
?>

<!-- Resumo Financeiro Superior (Não Imprimível - Baseado nos Demonstrativos da Fatura) -->
<div class="row mb-4 no-print">
  <div class="col-md-3">
      <div class="card shadow-sm">
          <div class="card-body text-center">
              <h6 class="text-muted mb-1">Faturado (Cliente)</h6>
              <h4 class="mb-0">R$ <?= number_format(floatval($invoiceData['total_receivable']), 2, ',', '.') ?></h4>
          </div>
      </div>
  </div>
  <div class="col-md-3">
      <div class="card shadow-sm">
          <div class="card-body text-center">
              <h6 class="text-muted mb-1">Total Recebido (Numerário)</h6>
              <h4 class="mb-0 text-success">R$ <?= number_format($totalRecebidoDemonstrativoBRL, 2, ',', '.') ?></h4>
          </div>
      </div>
  </div>
  <div class="col-md-3">
      <div class="card shadow-sm">
          <div class="card-body text-center">
              <h6 class="text-muted mb-1">Total Pago (Demonstrativo)</h6>
              <h4 class="mb-0 text-warning">R$ <?= number_format($totalPagoDemonstrativoBRL, 2, ',', '.') ?></h4>
          </div>
      </div>
  </div>
  <div class="col-md-3">
      <div class="card shadow-sm">
          <div class="card-body text-center">
              <h6 class="text-muted mb-1">Saldo Final (Demonstrativo)</h6>
              <h4 class="mb-0 <?= $saldoFinalDemonstrativo >= 0 ? 'text-success' : 'text-danger' ?>">
                  R$ <?= number_format($saldoFinalDemonstrativo, 2, ',', '.') ?>
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
          <!-- Cabeçalho da Fatura (para tela e impressão) -->
          <div class="card mb-4 shadow-sm">
              <div class="card-body">
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
              <div class="card-header bg-light py-2"><h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Demonstrativo de Valores de (Numerário)</h5></div>
              <div class="card-body p-0">
                  <?php if (!empty($invoiceData['received_items'])): ?>
                      <div class="table-responsive">
                          <table class="table table-striped table-hover mb-0">
                              <thead class="table-light"><tr><th class="ps-3">Descrição</th><th>Moeda</th><th class="text-end pe-3">Valor</th></tr></thead>
                              <tbody>
                              <?php foreach ($invoiceData['received_items'] as $item): ?>
                                  <tr><td class="ps-3"><?= htmlspecialchars($item['description']) ?></td><td><?= htmlspecialchars($item['currency']) ?></td><td class="text-end pe-3"><?= number_format(floatval($item['amount']), 2, ',', '.') ?></td></tr>
                              <?php endforeach; ?>
                              </tbody>
                              <tfoot class="table-light"><tr><th colspan="2" class="text-end fw-bold ps-3">Total Demonstrativo de Numerário (BRL):</th><th class="text-end fw-bold pe-3">R$ <?= number_format($totalRecebidoDemonstrativoBRL, 2, ',', '.') ?></th></tr></tfoot>
                          </table>
                      </div>
                  <?php else: ?><p class="text-muted p-3">Nenhum item neste demonstrativo.</p><?php endif; ?>
              </div>
          </div>
          
          <!-- Demonstrativo de valor pago -->
          <div class="card mb-4 shadow-sm">
              <div class="card-header bg-light py-2"><h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Demonstrativo de valor pago</h5></div>
              <div class="card-body p-0">
                  <?php if (!empty($invoiceData['paid_items'])): ?>
                      <div class="table-responsive">
                          <table class="table table-striped table-hover mb-0">
                              <thead class="table-light"><tr><th class="ps-3">Descrição</th><th>Moeda</th><th class="text-end pe-3">Valor</th></tr></thead>
                              <tbody>
                              <?php foreach ($invoiceData['paid_items'] as $item): ?>
                                  <tr><td class="ps-3"><?= htmlspecialchars($item['description']) ?></td><td><?= htmlspecialchars($item['currency']) ?></td><td class="text-end pe-3"><?= number_format(floatval($item['amount']), 2, ',', '.') ?></td></tr>
                              <?php endforeach; ?>
                              </tbody>
                              <tfoot class="table-light"><tr><th colspan="2" class="text-end fw-bold ps-3">Total Demonstrativo de valor pago (BRL):</th><th class="text-end fw-bold pe-3">R$ <?= number_format($totalPagoDemonstrativoBRL, 2, ',', '.') ?></th></tr></tfoot>
                          </table>
                      </div>
                  <?php else: ?><p class="text-muted p-3">Nenhum item neste demonstrativo.</p><?php endif; ?>
              </div>
          </div>

          <!-- Fechamento e Dados Bancários -->
          <div class="row">
              <div class="col-md-7 mb-4">
                  <div class="card shadow-sm h-100">
                      <div class="card-header bg-light py-2"><h5 class="mb-0"><i class="bi bi-calculator-fill me-2"></i>Fechamento</h5></div>
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
                      <div class="card-header bg-light py-2"><h5 class="mb-0"><i class="bi bi-bank me-2"></i>Dados Bancários</h5></div>
                      <div class="card-body">
                          <p class="mb-1"><strong>Delphi Fretes Internacionais Ltda</strong></p>
                          <p class="mb-1"><small>CNPJ: 24.768.038/0001-88</small></p>
                          <p class="mb-1"><strong>Banco:</strong> Itaú Unibanco S/A</p>
                          <p class="mb-0"><strong>Agência:</strong> 0500 &nbsp;&nbsp;<strong>Conta:</strong> 93570-2</p>
                      </div>
                  </div>
              </div>
          </div>
      </div> <!-- Fim de #printable-invoice-area -->
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

</div>

<!-- Modal Anexar Documento -->
<div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentModalLabel">Anexar Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="documentForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                    
                    <div class="mb-3">
                        <label for="document_file" class="form-label">Arquivo *</label>
                        <input type="file" id="document_file" name="document_file" class="form-control" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xls,.xlsx" required>
                        <div class="form-text">Formatos aceitos: PDF, DOC, DOCX, JPG, PNG, GIF, XLS, XLSX (máx. 5MB)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_description" class="form-label">Descrição *</label>
                        <input type="text" id="document_description" name="description" class="form-control" 
                               placeholder="Ex: Nota fiscal, comprovante de pagamento, etc." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_category" class="form-label">Categoria</label>
                        <select id="document_category" name="category" class="form-select">
                            <option value="FISCAL">Documento Fiscal</option>
                            <option value="PAGAMENTO">Comprovante de Pagamento</option>
                            <option value="CONTRATO">Contrato</option>
                            <option value="CORRESPONDENCIA">Correspondência</option>
                            <option value="OUTROS">Outros</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_notes" class="form-label">Observações</label>
                        <textarea id="document_notes" name="notes" class="form-control" rows="2" 
                                  placeholder="Observações adicionais sobre o documento"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Anexar Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Email -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
      <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title" id="emailModalLabel">Enviar Fatura por Email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <form id="emailForm"><div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="invoice_id" value="<?= $invoiceData['id'] ?>">
              <div class="mb-3"><label for="to_email_modal" class="form-label">Para:</label><input type="email" id="to_email_modal" name="to_email" class="form-control" value="<?= htmlspecialchars($invoiceData['client_email']) ?>" required></div>
              <div class="mb-3"><label for="subject_modal" class="form-label">Assunto:</label><input type="text" id="subject_modal" name="subject" class="form-control" value="Fatura <?= htmlspecialchars($invoiceData['invoice_number']) ?> - Delphi Fretes" required></div>
              <div class="mb-3"><label for="message_modal" class="form-label">Mensagem:</label><textarea id="message_modal" name="message" class="form-control" rows="5" required>Prezado(a) Cliente,\n\nSegue em anexo a fatura de número <?= htmlspecialchars($invoiceData['invoice_number']) ?> referente aos serviços prestados pela Delphi Fretes Internacionais.\n\nVencimento em: <?= date('d/m/Y', strtotime($invoiceData['due_date'])) ?>.\n\nEm caso de dúvidas, estamos à disposição.\n\nAtenciosamente,\nEquipe Delphi Fretes</textarea></div>
          </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="bi bi-envelope-fill me-2"></i>Enviar Email</button></div></form>
      </div>
  </div>
</div>

<?php
$content = ob_get_clean();
// Adicionar classes CSS customizadas para cores de header de card
$extraStyles = '
<style>
  .bg-light-success { background-color: rgba(25, 135, 84, 0.07) !important; }
  .bg-light-warning { background-color: rgba(255, 193, 7, 0.07) !important; }
  .table-light th, .table-light td { background-color: #f8f9fa; } /* Para os footers das tabelas */
</style>
';
include 'includes/layout.php';
?>

<script>
function printInvoice() {
    const printStyle = document.createElement('style');
    printStyle.textContent = `
        @media print {
            body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
            .no-print, .btn-group, .nav-tabs, .mobile-header, .sidebar, .page-header, .alert, .modal, .modal-backdrop { display: none !important; }
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

// Carregar documentos ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    loadDocuments();
});

// Envio do formulário de documento
document.getElementById('documentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
    
    fetch('actions/upload-document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('documentModal')).hide();
            loadDocuments();
            this.reset();
        } else {
            alert('Erro ao anexar documento: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro de comunicação: ' + error);
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
});

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

// Função para excluir documento
function deleteDocument(id) {
    if (confirm('Tem certeza que deseja excluir este documento?')) {
        fetch('actions/delete-document.php', {
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
                loadDocuments();
            } else {
                alert('Erro ao excluir documento: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro de comunicação: ' + error);
        });
    }
}

// Funções auxiliares
function getFileIcon(extension) {
    const icons = {
        'pdf': 'bi-file-earmark-pdf',
        'doc': 'bi-file-earmark-word',
        'docx': 'bi-file-earmark-word',
        'xls': 'bi-file-earmark-excel',
        'xlsx': 'bi-file-earmark-excel',
        'jpg': 'bi-file-earmark-image',
        'jpeg': 'bi-file-earmark-image',
        'png': 'bi-file-earmark-image',
        'gif': 'bi-file-earmark-image'
    };
    return icons[extension.toLowerCase()] || 'bi-file-earmark';
}

function getCategoryBadge(category) {
    const categories = {
        'FISCAL': '<span class="badge bg-success">Fiscal</span>',
        'PAGAMENTO': '<span class="badge bg-primary">Pagamento</span>',
        'CONTRATO': '<span class="badge bg-warning">Contrato</span>',
        'CORRESPONDENCIA': '<span class="badge bg-info">Correspondência</span>',
        'OUTROS': '<span class="badge bg-secondary">Outros</span>'
    };
    return categories[category] || '<span class="badge bg-secondary">Outros</span>';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
}

// Envio do formulário de email (mantido)
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
    fetch('actions/send-email.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email enviado com sucesso!');
            var emailModalEl = document.getElementById('emailModal');
            if (emailModalEl) { bootstrap.Modal.getInstance(emailModalEl)?.hide(); }
        } else { alert('Erro ao enviar email: ' + data.message); }
    })
    .catch(error => { alert('Erro de comunicação: ' + error); })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
});

// Reset modal quando fechado
document.getElementById('documentModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('documentForm').reset();
});
</script>
