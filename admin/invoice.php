<?php
// admin/invoice.php
session_start();
require_once __DIR__ . '/../db.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$b = $DB->query("SELECT b.*, r.title as room_title, r.code as room_code FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id WHERE b.id=$id")->fetch_assoc();
if (!$b) {
    echo "Booking not found";
    exit;
}

// Handle Actions (Add/Delete Extra)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $booking_id = intval($_POST['booking_id'] ?? 0);

    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }

    if ($action === 'add_extra') {
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);

        if (empty($description) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }

        $DB->begin_transaction();
        try {
            $stmt = $DB->prepare("INSERT INTO booking_extras (booking_id, description, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $booking_id, $description, $amount);
            $stmt->execute();

            // Update booking total
            $stmt2 = $DB->prepare("UPDATE bookings SET total = total + ? WHERE id = ?");
            $stmt2->bind_param("di", $amount, $booking_id);
            $stmt2->execute();

            $DB->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $DB->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    } elseif ($action === 'delete_extra') {
        $extra_id = intval($_POST['id'] ?? 0);

        // Get amount first
        $stmt = $DB->prepare("SELECT amount FROM booking_extras WHERE id = ? AND booking_id = ?");
        $stmt->bind_param("ii", $extra_id, $booking_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $extra = $res->fetch_assoc();

        if ($extra) {
            $amount = $extra['amount'];
            $DB->begin_transaction();
            try {
                $DB->query("DELETE FROM booking_extras WHERE id=$extra_id");
                $stmt2 = $DB->prepare("UPDATE bookings SET total = total - ? WHERE id = ?");
                $stmt2->bind_param("di", $amount, $booking_id);
                $stmt2->execute();
                $DB->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $DB->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found']);
        }
        exit;
    }
    exit;
}

$extras = [];
$res = $DB->query("SELECT * FROM booking_extras WHERE booking_id = $id ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $extras[] = $row;
    }
}

$extras_rows = '';
$modal_extras_rows = '';
$extrasTotal = 0;

if (!empty($extras)) {
    foreach ($extras as $extra) {
        $extrasTotal += $extra['amount'];
        $desc = htmlspecialchars($extra['description'], ENT_QUOTES, 'UTF-8');
        $amt = number_format($extra['amount'], 2);

        // Invoice Table Row
        $extras_rows .= '
        <tr>
            <td colspan="5">
                <strong>' . $desc . '</strong>
            </td>
            <td class="text-right"><strong>Rs. ' . $amt . '</strong></td>
        </tr>';

        // Modal Table Row
        $modal_extras_rows .= '
        <tr>
            <td>' . $desc . '</td>
            <td class="text-right">Rs. ' . $amt . '</td>
            <td class="text-right">
                <button onclick="deleteExtra(' . $extra['id'] . ')" style="background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Delete</button>
            </td>
        </tr>';
    }
} else {
    $modal_extras_rows = '<tr><td colspan="3" style="text-align: center;">No extra charges</td></tr>';
}

$pdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';

// Calculate Room Totals separate from Extras
// Total = Room + GST + Extras
// GST is fixed in DB
// Room = Total - Extras - GST
$roomAmount = $b['total'] - $extrasTotal - $b['gst_amount'];
$roomRate = ($b['nights'] > 0) ? ($roomAmount / $b['nights']) : $roomAmount;


// Safe date displays with fallback
$checkinDisplay = (!empty($b['checkin']) && $b['checkin'] !== '0000-00-00') ? date('M d, Y', strtotime($b['checkin'])) : 'N/A';
$checkoutDisplay = '';
if (!empty($b['checkout']) && $b['checkout'] !== '0000-00-00') {
    $checkoutDisplay = date('M d, Y', strtotime($b['checkout']));
} else {
    if (!empty($b['checkin']) && !empty($b['nights']) && intval($b['nights']) >= 1) {
        $checkoutDisplay = date('M d, Y', strtotime('+' . intval($b['nights']) . ' day', strtotime($b['checkin'])));
    } else {
        $checkoutDisplay = 'Not checked out';
    }
}

// Build HTML conditionally - exclude buttons for PDF
$actionButtons = '';
if (!$pdf) {
    $actionButtons = '
    <div class="invoice-actions">
        <a href="javascript:window.print()" class="btn-pdf" style="background: #2C3E50; margin-right: 10px;">
            <i class="fas fa-print"></i> Print
        </a>
        <a href="?id=' . $id . '&pdf=1" class="btn-pdf">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>
        <button onclick="document.getElementById(\'editModal\').style.display=\'block\'" class="btn-pdf" style="background: #e67e22; margin-left: 10px; border: none; cursor: pointer;">
            <i class="fas fa-edit"></i> Edit Bill
        </button>
        <a href="bookings.php" class="btn-pdf" style="background: #808080; margin-left: 10px;">
            <i class="fas fa-arrow-left"></i> Back to Bookings
        </a>
    </div>';
}

$web_css = '
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
        .invoice-info-table { 
            width: 100%; 
            margin-bottom: 40px; 
        }
        .info-block { 
            width: 100%; 
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
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 8px 20px; 
            border-radius: 25px; 
            font-weight: bold; 
            font-size: 13px; 
            line-height: 1.2; 
            margin: 4px 0; 
            white-space: nowrap; 
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
        
        /* Modal styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 60%; 
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        /* Print Styles */
        @media print {
            @page {
                size: A4;
                margin: 5mm;
            }
            body {
                background: white;
                padding: 0;
                font-size: 11px;
            }
            .invoice-container {
                box-shadow: none;
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                border: none;
            }
            .invoice-actions, .modal {
                display: none !important;
            }
            .invoice-header {
                padding-bottom: 5px;
                margin-bottom: 10px;
                border-bottom: 2px solid #1A4D2E;
            }
            .invoice-header h1 {
                font-size: 20px;
                margin: 0;
            }
            .invoice-header p {
                font-size: 10px;
                margin: 2px 0 0 0;
            }
            .invoice-info-table {
                width: 100%;
                margin-bottom: 10px;
            }
            .info-block {
                width: 100%;
            }
            .info-block h3 {
                font-size: 12px;
                margin-bottom: 3px;
                padding-bottom: 2px;
                border-bottom-width: 1px;
            }
            .info-block p {
                font-size: 10px;
                line-height: 1.2;
            }
            table {
                margin: 10px 0;
                width: 100%;
                border-collapse: separate; 
                border-spacing: 0;
            }
            th {
                padding: 4px;
                font-size: 10px;
                background-color: #eee !important;
                color: #000 !important;
                border-bottom: 1px solid #000;
                text-align: left;
            }
            td {
                padding: 4px;
                font-size: 10px;
                border-bottom: 1px solid #ddd;
            }
            .text-right {
                text-align: right;
            }
            .gst-breakdown {
                padding: 5px;
                margin: 10px 0 0 0;
                background: none;
                border: 1px solid #eee;
            }
            .gst-breakdown h4 {
                font-size: 12px;
                margin-bottom: 5px;
            }
            .total-row td {
                padding: 5px;
                font-size: 12px;
                background: #eee !important;
                color: #000 !important;
            }
            .payment-terms-box {
                margin-top: 10px;
                padding: 5px;
                font-size: 9px;
                border: 1px solid #ddd;
                border-left-width: 2px;
            }
            .invoice-footer {
                margin-top: 10px;
                padding-top: 5px;
                border-top: 1px solid #ddd;
            }
            .invoice-footer p {
                font-size: 9px;
                margin: 2px 0;
            }
            h1, h2, h3, h4, h5, h6 { margin-top: 0; }
            p { margin-bottom: 0; }
        }
';

$pdf_css = '
        body { 
            background: white; 
            font-size: 10px; 
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
        }
        .invoice-container { 
            width: 100%; 
            margin: 0; 
            padding: 0; 
            border: none; 
        }
        .invoice-header { 
            padding-bottom: 5px; 
            margin-bottom: 15px; 
            border-bottom: 2px solid #1A4D2E; 
        }
        .invoice-header h1 { 
            font-size: 20px; 
            margin: 0; 
            color: #1A4D2E; 
        }
        .invoice-header p { 
            font-size: 10px; 
            color: #808080; 
            margin: 2px 0 0 0; 
        }
        .invoice-info-table { 
            width: 100%; 
            margin-bottom: 15px; 
            border-spacing: 0;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .info-block { 
            width: 100%; 
            vertical-align: top; 
        }
        .info-block h3 { 
            font-size: 11px; 
            color: #1A4D2E; 
            margin-bottom: 3px; 
            padding-bottom: 2px; 
            border-bottom: 1px solid #50B848; 
        }
        .info-block p { 
            font-size: 10px; 
            line-height: 1.3; 
            color: #2C3E50; 
            margin: 0; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            page-break-inside: avoid;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        th { 
            padding: 5px; 
            font-size: 9px; 
            background-color: #1A4D2E; 
            color: white; 
            border-bottom: 1px solid #1A4D2E; 
            text-align: left; 
            text-transform: uppercase; 
        }
        td { 
            padding: 5px; 
            font-size: 9px; 
            border-bottom: 1px solid #ddd; 
            color: #333; 
        }
        .text-right { 
            text-align: right; 
        }
        .gst-breakdown { 
            padding: 5px; 
            margin: 15px 0 0 0; 
            background-color: #F8F9FA; 
            border: 1px solid #E0E0E0; 
            page-break-inside: avoid;
        }
        .gst-breakdown h4 { 
            font-size: 11px; 
            margin-bottom: 5px; 
            color: #1A4D2E; 
        }
        .total-row td { 
            padding: 8px; 
            font-size: 11px; 
            background: #1A4D2E; 
            color: white; 
            font-weight: bold; 
        }
        .status-badge { 
            display: inline-block; 
            padding: 3px 10px; 
            border-radius: 10px; 
            font-size: 9px;
            font-weight: bold; 
        }
        .status-paid { background: #D4EDDA; color: #155724; }
        .status-pending { background: #FFF3CD; color: #856404; }
        .status-confirmed { background: #D1ECF1; color: #0C5460; }

        .payment-terms-box { 
            margin-top: 15px; 
            padding: 10px; 
            background-color: #F8F9FA; 
            border: 1px solid #ddd; 
            border-left: 4px solid #50B848; 
            page-break-inside: avoid; 
        }
        .payment-terms-box h4 {
            color: #1A4D2E;
            margin-bottom: 3px;
            font-size: 11px;
        }
        .payment-terms-box p {
            font-size: 9px;
            line-height: 1.2;
        }
        .invoice-footer { 
            margin-top: 20px; 
            padding-top: 10px; 
            border-top: 1px solid #E0E0E0; 
            text-align: center; 
            page-break-inside: avoid;
        }
        .invoice-footer p { 
            font-size: 9px; 
            margin: 2px 0; 
            color: #808080; 
        }
        .invoice-actions, .modal { display: none; }
';

$invoice_html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Bill #' . $b['id'] . '</title>
    <style>
        ' . ($pdf ? $pdf_css : $web_css) . '
    </style>
</head>
<body class="' . ($pdf ? 'pdf-view' : '') . '">
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>BILL</h1>
            <p>Bill #' . $b['id'] . ' | Date: ' . date('F d, Y', strtotime($b['created_at'])) . '</p>
        </div>
        
        <table class="invoice-info-table" style="width: 100%; border-spacing: 0; margin-bottom: 20px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                    <div class="info-block">
                        <h3>Hotel Information</h3>
                        <p>
                            <strong>Bed N Basics</strong><br>
                            Satav Chowk, Jatharpeth, Akola.<br>
                            Phone: +91 12345 67890<br>
                            Email: reservations@hotel.com<br>
                            <strong>GSTIN:</strong> 27CNLPD2064H1Z2
                        </p>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                    <div class="info-block">
                        <h3>Bill To</h3>
                        <p>
                            <strong>' . htmlspecialchars($b['customer_name'], ENT_QUOTES, 'UTF-8') . '</strong><br>
                            ' . htmlspecialchars($b['customer_email'], ENT_QUOTES, 'UTF-8') . '<br>
                            ' . htmlspecialchars($b['customer_phone'], ENT_QUOTES, 'UTF-8') . '
                        </p>
                    </div>
                </td>
            </tr>
        </table>
        
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
                        <strong>' . htmlspecialchars($b['room_title'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</strong><br>
                        <small style="color: #808080;">Room Code: ' . htmlspecialchars($b['room_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</small>
                    </td>
                    <td class="text-right">' . $checkinDisplay . '</td>
                    <td class="text-right">' . $checkoutDisplay . '</td>
                    <td class="text-right">' . intval($b['nights']) . '</td>
                    <td class="text-right">Rs. ' . number_format($roomRate, 2) . '</td>
                    <td class="text-right"><strong>Rs. ' . number_format($roomAmount, 2) . '</strong></td>
                </tr>
                ' . $extras_rows . '
            </tbody>
        </table>
        
        <div class="gst-breakdown">
            <h4>GST Breakdown</h4>
            <table style="width: 100%; border-spacing: 0;">
                <tr>
                    <td style="padding: 4px 0;">Room Charges:</td>
                    <td class="text-right" style="padding: 4px 0;"><strong>Rs. ' . number_format($roomAmount, 2) . '</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">GST Rate:</td>
                    <td class="text-right" style="padding: 4px 0;"><strong>' . number_format($b['gst_rate'], 2) . '%</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;">GST Amount:</td>
                    <td class="text-right" style="padding: 4px 0;"><strong>Rs. ' . number_format($b['gst_amount'], 2) . '</strong></td>
                </tr>
                ' . ($extrasTotal > 0 ? '
                <tr>
                    <td style="padding: 4px 0;">Additional Charges (Food, Services, etc):</td>
                    <td class="text-right" style="padding: 4px 0;"><strong>Rs. ' . number_format($extrasTotal, 2) . '</strong></td>
                </tr>' : '') . '
                <tr>
                    <td style="padding: 10px 0 0 0; border-top: 2px solid #1A4D2E;"><span style="font-size: 14px; font-weight: bold; color: #1A4D2E;">Total Amount (Including GST):</span></td>
                    <td class="text-right" style="padding: 10px 0 0 0; border-top: 2px solid #1A4D2E;"><span style="font-size: 16px; font-weight: bold; color: #1A4D2E;">Rs. ' . number_format($b['total'], 2) . '</span></td>
                </tr>
            </table>
        </div>
        
        <table>
            <tbody>
                <tr>
                    <td colspan="6" style="border: none; padding-top: 20px;">
                        <strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', htmlspecialchars($b['payment_method'], ENT_QUOTES, 'UTF-8'))) . '<br>
                        <strong>Status:</strong> <span class="status-badge status-' . htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') . '">' . ucfirst(htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8')) . '</span>
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
    ' . $actionButtons . '
</body>
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById(\'editModal\').style.display=\'none\'">&times;</span>
        <h2 style="color: #1A4D2E; margin-bottom: 20px;">Edit Bill</h2>
        
        <div class="add-extra-form" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
            <h3 style="margin-bottom: 10px;">Add Extra Charge</h3>
            <input type="text" id="extraDesc" placeholder="Description (e.g. Food Bill)" style="padding: 8px; width: 40%; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="number" id="extraAmount" placeholder="Amount" step="0.01" style="padding: 8px; width: 20%; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <button onclick="addExtra()" class="btn-pdf" style="padding: 8px 20px; cursor: pointer;">Add</button>
        </div>

        <div class="current-extras">
            <h3 style="margin-bottom: 10px;">Current Extras</h3>
            <table style="margin: 0;">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="extrasList">
                    ' . $modal_extras_rows . '
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function addExtra() {
    const desc = document.getElementById(\'extraDesc\').value;
    const amount = document.getElementById(\'extraAmount\').value;
    
    if (!desc || !amount) {
        alert(\'Please fill in all fields\');
        return;
    }

    const formData = new FormData();
    formData.append(\'action\', \'add_extra\');
    formData.append(\'booking_id\', ' . $id . ');
    formData.append(\'description\', desc);
    formData.append(\'amount\', amount);

    fetch(\'invoice.php?id=' . $id . '\', {
        method: \'POST\',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(\'Error: \' + data.message);
        }
    })
    .catch(err => alert(\'Error connecting to server\'));
}

function deleteExtra(id) {
    if (!confirm(\'Are you sure?\')) return;

    const formData = new FormData();
    formData.append(\'action\', \'delete_extra\');
    formData.append(\'booking_id\', ' . $id . ');
    formData.append(\'id\', id);

    fetch(\'invoice.php?id=' . $id . '\', {
        method: \'POST\',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(\'Error: \' + data.message);
        }
    })
    .catch(err => alert(\'Error connecting to server\'));
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById(\'editModal\');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</html>';

if ($pdf) {
    try {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 120);

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
    } catch (\Throwable $e) {
        // Log error
        $logFile = __DIR__ . '/pdf_error.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);

        // Show generic error to user
        http_response_code(500);
        die("PDF Generation Failed. Error logged to pdf_error.log");
    }
}

echo $invoice_html;