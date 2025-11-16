<?php
// admin/invoice.php
session_start();
require_once __DIR__ . '/../db.php';
if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; }

$id = intval($_GET['id'] ?? 0);
$b = $DB->query("SELECT b.*, r.title as room_title, r.code as room_code FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id WHERE b.id=$id")->fetch_assoc();
if(!$b){ echo "Booking not found"; exit; }

$pdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';
$subtotal = $b['total'] - $b['gst_amount'];
$roomRate = $subtotal / $b['nights'];

// Build HTML conditionally - exclude buttons for PDF
$actionButtons = '';
if(!$pdf) {
    $actionButtons = '
    <div class="invoice-actions">
        <a href="?id='.$id.'&pdf=1" class="btn-pdf">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>
        <a href="bookings.php" class="btn-pdf" style="background: #808080; margin-left: 10px;">
            <i class="fas fa-arrow-left"></i> Back to Bookings
        </a>
    </div>';
}

$invoice_html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Bill #'.$b['id'].'</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif; 
            padding: 40px; 
            background: #f5f5f5; 
        }
        .invoice-container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            padding: 50px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.1); 
        }
        .invoice-header { 
            border-bottom: 4px solid #1A4D2E; 
            padding-bottom: 25px; 
            margin-bottom: 35px; 
        }
        .invoice-header h1 { 
            color: #1A4D2E; 
            font-size: 36px; 
            font-weight: bold; 
            margin-bottom: 10px; 
        }
        .invoice-header p { 
            color: #808080; 
            font-size: 14px; 
        }
        .invoice-info { 
            display: table; 
            width: 100%; 
            margin-bottom: 40px; 
        }
        .info-block { 
            display: table-cell; 
            width: 50%; 
            padding-right: 20px; 
            vertical-align: top; 
        }
        .info-block:last-child { 
            padding-right: 0; 
            padding-left: 20px; 
        }
        .info-block h3 { 
            color: #1A4D2E; 
            margin-bottom: 15px; 
            font-size: 18px; 
            font-weight: bold; 
            border-bottom: 2px solid #50B848; 
            padding-bottom: 8px; 
        }
        .info-block p { 
            color: #2C3E50; 
            line-height: 1.8; 
            font-size: 14px; 
        }
        .info-block strong { 
            color: #1A4D2E; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 30px 0; 
        }
        th, td { 
            padding: 15px; 
            text-align: left; 
            border-bottom: 1px solid #E0E0E0; 
        }
        th { 
            background: #1A4D2E; 
            color: white; 
            font-weight: bold; 
            font-size: 13px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .text-right { 
            text-align: right; 
        }
        .gst-breakdown { 
            background: #F8F9FA; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px 0; 
        }
        .gst-breakdown h4 { 
            color: #1A4D2E; 
            margin-bottom: 15px; 
            font-size: 16px; 
            font-weight: bold; 
        }
        .gst-row { 
            display: table; 
            width: 100%; 
            padding: 8px 0; 
            border-bottom: 1px solid #E0E0E0; 
        }
        .gst-row span { 
            display: table-cell; 
        }
        .gst-row span:last-child { 
            text-align: right; 
        }
        .gst-row:last-child { 
            border-bottom: none; 
        }
        .total-row { 
            background: #1A4D2E; 
            color: white; 
            font-weight: bold; 
        }
        .total-row td { 
            border-top: 3px solid #50B848; 
            padding: 20px 15px; 
            font-size: 18px; 
        }
        .invoice-footer { 
            margin-top: 50px; 
            padding-top: 25px; 
            border-top: 2px solid #E0E0E0; 
            text-align: center; 
            color: #808080; 
        }
        .status-badge { 
            display: inline-block; 
            padding: 8px 20px; 
            border-radius: 25px; 
            font-weight: bold; 
            font-size: 13px; 
        }
        .status-paid { 
            background: #D4EDDA; 
            color: #155724; 
        }
        .status-pending { 
            background: #FFF3CD; 
            color: #856404; 
        }
        .status-confirmed { 
            background: #D1ECF1; 
            color: #0C5460; 
        }
        .invoice-actions { 
            text-align: center; 
            margin: 30px 0; 
        }
        .btn-pdf { 
            display: inline-block; 
            padding: 12px 30px; 
            background: #1A4D2E; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: bold; 
            transition: all 0.3s; 
        }
        .btn-pdf:hover { 
            background: #50B848; 
        }
        .payment-terms-box {
            margin-top: 30px; 
            padding: 20px; 
            background: #F8F9FA; 
            border-radius: 8px; 
            border-left: 4px solid #50B848;
        }
        .payment-terms-box h4 { 
            color: #1A4D2E; 
            margin-bottom: 15px; 
            font-size: 16px; 
            font-weight: bold; 
        }
        .payment-terms-box p { 
            color: #2C3E50; 
            line-height: 1.8; 
            font-size: 14px; 
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>BILL</h1>
            <p>Bill #'.$b['id'].' | Date: '.date('F d, Y', strtotime($b['created_at'])).'</p>
        </div>
        
        <div class="invoice-info">
            <div class="info-block">
                <h3>Hotel Information</h3>
                <p>
                    <strong>Hotel Reservation System</strong><br>
                    City Center, Main Street<br>
                    Phone: +91 12345 67890<br>
                    Email: reservations@hotel.com<br>
                    <strong>GSTIN:</strong> 29ABCDE1234F1Z5
                </p>
            </div>
            <div class="info-block">
                <h3>Bill To</h3>
                <p>
                    <strong>'.htmlspecialchars($b['customer_name'], ENT_QUOTES, 'UTF-8').'</strong><br>
                    '.htmlspecialchars($b['customer_email'], ENT_QUOTES, 'UTF-8').'<br>
                    '.htmlspecialchars($b['customer_phone'], ENT_QUOTES, 'UTF-8').'
                </p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Check-in</th>
                    <th class="text-right">Check-out</th>
                    <th class="text-right">Nights</th>
                    <th class="text-right">Rate (Rs.)</th>
                    <th class="text-right">Amount (Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>'.htmlspecialchars($b['room_title'] ?? 'N/A', ENT_QUOTES, 'UTF-8').'</strong><br>
                        <small style="color: #808080;">Room Code: '.htmlspecialchars($b['room_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8').'</small>
                    </td>
                    <td class="text-right">'.date('M d, Y', strtotime($b['checkin'])).'</td>
                    <td class="text-right">'.(!empty($b['checkout']) && $b['checkout'] !== '0000-00-00' ? date('M d, Y', strtotime($b['checkout'])) : 'N/A').'</td>
                    <td class="text-right">'.intval($b['nights']).'</td>
                    <td class="text-right">Rs. '.number_format($roomRate, 2).'</td>
                    <td class="text-right"><strong>Rs. '.number_format($subtotal, 2).'</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="gst-breakdown">
            <h4>GST Breakdown</h4>
            <div class="gst-row">
                <span>Subtotal (Before GST):</span>
                <span><strong>Rs. '.number_format($subtotal, 2).'</strong></span>
            </div>
            <div class="gst-row">
                <span>GST Rate:</span>
                <span><strong>'.number_format($b['gst_rate'], 2).'%</strong></span>
            </div>
            <div class="gst-row">
                <span>GST Amount:</span>
                <span><strong>Rs. '.number_format($b['gst_amount'], 2).'</strong></span>
            </div>
            <div class="gst-row" style="border-top: 2px solid #1A4D2E; margin-top: 10px; padding-top: 15px;">
                <span style="font-size: 18px; font-weight: bold; color: #1A4D2E;">Total Amount (Including GST):</span>
                <span style="font-size: 20px; font-weight: bold; color: #1A4D2E;">Rs. '.number_format($b['total'], 2).'</span>
            </div>
        </div>
        
        <table>
            <tbody>
                <tr>
                    <td colspan="6" style="border: none; padding-top: 20px;">
                        <strong>Payment Method:</strong> '.ucfirst(str_replace('_', ' ', htmlspecialchars($b['payment_method'], ENT_QUOTES, 'UTF-8'))).'<br>
                        <strong>Status:</strong> <span class="status-badge status-'.htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8').'">'.ucfirst(htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8')).'</span>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="payment-terms-box">
            <h4>Payment Terms</h4>
            <p>
                Payment is due upon receipt. For bank transfers, please include invoice number in the transaction reference.
            </p>
        </div>
        
        <div class="invoice-footer">
            <p style="margin-bottom: 10px;"><strong>Thank you for your business!</strong></p>
            <p style="font-size: 12px;">This is a computer-generated invoice and does not require a signature.</p>
            <p style="font-size: 12px; margin-top: 15px;">For any queries, please contact: reservations@hotel.com | +91 12345 67890</p>
        </div>
    </div>
    '.$actionButtons.'
</body>
</html>';

if($pdf){
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Configure Dompdf options
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($invoice_html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream("invoice-{$b['id']}.pdf", array("Attachment" => true));
    exit;
}

echo $invoice_html;