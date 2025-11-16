<?php
require_once __DIR__ . '/../db.php';
$bookings = $DB->query("SELECT b.*, r.title as room_title, r.code as room_code 
                        FROM bookings b 
                        LEFT JOIN rooms r ON r.id = b.room_id 
                        ORDER BY b.created_at DESC 
                        LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Check Bookings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #20B2AA; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .status { padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.paid { background: #d4edda; color: #155724; }
        .status.confirmed { background: #d1ecf1; color: #0c5460; }
        .status.cancelled { background: #f8d7da; color: #721c24; }
        h1 { color: #20B2AA; }
        .total { margin-top: 20px; font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Recent Bookings (Last 50)</h1>
    <p><a href="index.php">← Back to Home</a> | <a href="/admin/login.php">Admin Panel</a></p>
    
    <div class="total">Total Bookings: <?=count($bookings)?></div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Room</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Nights</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Booked On</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($bookings)): ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 40px;">
                        No bookings found.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($bookings as $b): ?>
                <tr>
                    <td><?=esc($b['id'])?></td>
                    <td><?=esc($b['customer_name'])?></td>
                    <td><?=esc($b['customer_email'])?></td>
                    <td><?=esc($b['customer_phone'])?></td>
                    <td><?=esc($b['room_title'] ?? 'N/A')?> (<?=esc($b['room_code'] ?? 'N/A')?>)</td>
                    <td><?=esc($b['checkin'])?></td>
                    <td><?=esc($b['checkout'])?></td>
                    <td><?=esc($b['nights'])?></td>
                    <td>₹<?=number_format($b['total'], 2)?></td>
                    <td><span class="status <?=esc($b['status'])?>"><?=esc($b['status'])?></span></td>
                    <td><?=esc($b['payment_method'])?></td>
                    <td><?=date('Y-m-d H:i', strtotime($b['created_at']))?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
