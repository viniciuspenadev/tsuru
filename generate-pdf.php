<?php
require_once 'config/config.php';
require_once 'classes/Invoice.php';
require_once 'vendor/autoload.php'; // Composer autoload para Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

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

// Configurar Dompdf
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);

// HTML para PDF
$html = '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        
        .invoice-header {
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-info {
            float: right;
            width: 300px;
            font-size: 10px;
            text-align: right;
        }
        
        .logo-section {
            float: left;
            width: 200px;
        }
        
        .company-logo {
            max-height: 60px;
            width: auto;
        }
        
        .invoice-title {
            background: #0066cc;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            margin: 20px 0 15px 0;
            clear: both;
        }
        
        .section {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .section-title {
            background: #f5f5f5;
            padding: 5px;
            margin: -10px -10px 10px -10px;
            border-bottom: 1px solid #ccc;
            font-weight: bold;
        }
        
        .field-row {
            margin-bottom: 8px;
        }
        
        .field-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
            vertical-align: top;
        }
        
        .field-value {
            border-bottom: 1px solid #ccc;
            display: inline-block;
            min-width: 150px;
            padding: 2px 0;
        }
        
        .values-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .values-table th,
        .values-table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
        }
        
        .values-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .totals-section {
            background: #fff8dc;
            border: 2px solid #ffc107;
            padding: 10px;
            margin: 15px 0;
        }
        
        .bank-info {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 10px;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .two-column {
            width: 48%;
            float: left;
            margin-right: 2%;
        }
        
        .shipping-grid {
            display: table;
            width: 100%;
        }
        
        .shipping-row {
            display: table-row;
        }
        
        .shipping-cell {
            display: table-cell;
            width: 16.66%;
            padding: 5px;
            vertical-align: top;
        }
    </style>
</head>
<body>
    <div class="invoice-header clearfix">
        <div class="logo-section">
            <h2 style="color: #0066cc; margin: 0;">Delphi</h2>
            <p style="margin: 0; color: #666;">Fretes Internacionais</p>
        </div>
        <div class="company-info">
            <strong>Delphi Fretes Internacionais e Despachos Aduaneiros EIRELI - ME</strong><br>
            CNPJ: 24.768.038/0001-88<br>
            Rua do Rosário, 260 Sala 31<br>
            Vila Camargo - Guarulhos - São Paulo - Cep 07111-000
        </div>
    </div>
    
    <div class="invoice-title">
        FATURA Nº ' . htmlspecialchars($invoiceData['invoice_number']) . '
    </div>
    
    <div style="float: right; width: 300px; margin-bottom: 20px;">
        <div class="field-row">
            <span class="field-label">Data Emissão:</span>
            <span class="field-value">' . date('d/m/Y', strtotime($invoiceData['issue_date'])) . '</span>
        </div>
        <div class="field-row">
            <span class="field-label">Termo:</span>
            <span class="field-value">' . $invoiceData['term_days'] . ' dias</span>
        </div>
        <div class="field-row">
            <span class="field-label">Data Vencimento:</span>
            <span class="field-value">' . date('d/m/Y', strtotime($invoiceData['due_date'])) . '</span>
        </div>
        <div class="field-row">
            <span class="field-label">Demonstrativo:</span>
            <span class="field-value">' . htmlspecialchars($invoiceData['demonstrative']) . '</span>
        </div>
    </div>
    
    <div style="clear: both;"></div>
    
    <div class="section">
        <div class="section-title">Cliente</div>
        <div class="field-row">
            <span class="field-label">Para:</span>
            <span class="field-value" style="width: 400px;">' . htmlspecialchars($invoiceData['client_name']) . '</span>
        </div>
        <div class="field-row">
            <span class="field-label">CNPJ:</span>
            <span class="field-value">' . htmlspecialchars($invoiceData['cnpj']) . '</span>
        </div>
        <div class="field-row">
            <span class="field-label">Endereço:</span>
            <span class="field-value" style="width: 400px;">' . htmlspecialchars($invoiceData['address']) . '</span>
        </div>
        <div class="field-row">
            <span class="field-label">Cidade:</span>
            <span class="field-value">' . htmlspecialchars($invoiceData['city']) . '</span>
            <span class="field-label" style="margin-left: 20px;">Estado:</span>
            <span class="field-value">' . htmlspecialchars($invoiceData['state']) . '</span>
            <span class="field-label" style="margin-left: 20px;">CEP:</span>
            <span class="field-value">' . htmlspecialchars($invoiceData['zip']) . '</span>
        </div>
    </div>
    
    <div class="section">
        <div class="shipping-grid">
            <div class="shipping-row">
                <div class="shipping-cell">
                    <strong>MAWB:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['mbl']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>HAWB:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['hbl']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>Origem:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['origin']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>Destino:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['destination']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>Incoterm:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['incoterm']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>Via Mercadoria:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['transport_mode']) . '</span>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 15px;">
            <div class="field-row">
                <span class="field-label">Exportador:</span>
                <span class="field-value" style="width: 400px;">' . htmlspecialchars($invoiceData['exporter']) . '</span>
            </div>
        </div>
        
        <div class="shipping-grid" style="margin-top: 10px;">
            <div class="shipping-row">
                <div class="shipping-cell">
                    <strong>Peso Bruto:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . number_format($invoiceData['gross_weight'], 3, ',', '.') . ' kg</span>
                </div>
                <div class="shipping-cell">
                    <strong>Peso Cubado:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . number_format($invoiceData['cubic_weight'], 3, ',', '.') . ' kg</span>
                </div>
                <div class="shipping-cell">
                    <strong>ETO:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . ($invoiceData['eto'] ? date('d/m/Y', strtotime($invoiceData['eto'])) : '') . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>ETA:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . ($invoiceData['eta'] ? date('d/m/Y', strtotime($invoiceData['eta'])) : '') . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>P.O.:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['po_number']) . '</span>
                </div>
                <div class="shipping-cell">
                    <strong>Invoice:</strong><br>
                    <span style="border-bottom: 1px solid #ccc; display: block; padding: 2px 0;">' . htmlspecialchars($invoiceData['invoice_ref']) . '</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Demonstrativo de Valores</div>';

if (!empty($invoiceData['items'])) {
    $html .= '
        <table class="values-table">
            <thead>
                <tr>
                    <th width="60%">Descrição</th>
                    <th width="15%">Moeda</th>
                    <th width="25%">Valor</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($invoiceData['items'] as $item) {
        $html .= '
                <tr>
                    <td style="text-align: left;">' . htmlspecialchars($item['description']) . '</td>
                    <td>' . htmlspecialchars($item['currency']) . '</td>
                    <td style="text-align: right;">' . number_format($item['amount'], 2, ',', '.') . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
}

$html .= '
        <div class="totals-section">
            <div class="two-column">
                <div class="field-row">
                    <span class="field-label">Fechamento Prazo:</span>
                    <span class="field-value">' . ($invoiceData['closing_term'] ? date('d/m/Y', strtotime($invoiceData['closing_term'])) : '') . '</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Paridade EUR x USD:</span>
                    <span class="field-value">' . number_format($invoiceData['eur_usd_rate'], 4, ',', '.') . '</span>
                </div>
            </div>
            <div class="two-column">
                <div class="field-row">
                    <span class="field-label">Saldo a Devolver:</span>
                    <span class="field-value">R$ ' . number_format($invoiceData['total_refund'], 2, ',', '.') . '</span>
                </div>
                <div class="field-row">
                    <span class="field-label"><strong>Saldo a Receber:</strong></span>
                    <span class="field-value"><strong>R$ ' . number_format($invoiceData['total_receivable'], 2, ',', '.') . '</strong></span>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
    
    <div class="bank-info">
        <div class="section-title">Dados Bancários</div>
        <strong>Delphi Fretes Internacionais Ltda</strong><br>
        CNPJ: 24.768.038/0001-88<br>
        Banco: Itaú Unibanco S/A<br>
        Agência: 0500 Conta: 93570-2
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nome do arquivo
$filename = 'Fatura_' . $invoiceData['invoice_number'] . '_' . date('Y-m-d') . '.pdf';

// Output do PDF
$dompdf->stream($filename, array('Attachment' => false));
?>
