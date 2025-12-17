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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Bookings</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        h1 { 
            color: #20B2AA;
            margin-bottom: 20px;
        }
        
        .nav-links {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #20B2AA;
            text-decoration: none;
            padding: 8px 16px;
            border: 2px solid #20B2AA;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: #20B2AA;
            color: white;
        }
        
        .total { 
            margin-top: 20px; 
            font-size: 18px; 
            font-weight: bold;
            padding: 15px;
            background: #e8f5f4;
            border-radius: 4px;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table { 
            border-collapse: collapse; 
            width: 100%; 
            min-width: 800px;
        }
        
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left;
        }
        
        th { 
            background: #20B2AA; 
            color: white;
            font-weight: 600;
        }
        
        tr:nth-child(even) { 
            background: #f9f9f9;
        }
        
        tr:hover {
            background: #f0f0f0;
        }
        
        .status { 
            padding: 5px 10px; 
            border-radius: 4px; 
            font-weight: bold;
            display: inline-block;
            font-size: 13px;
        }
        
        .status.pending { 
            background: #fff3cd; 
            color: #856404;
        }
        
        .status.paid { 
            background: #d4edda; 
            color: #155724;
        }
        
        .status.confirmed { 
            background: #d1ecf1; 
            color: #0c5460;
        }
        
        .status.cancelled { 
            background: #f8d7da; 
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            body {
                margin: 10px;
            }
            
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .nav-links {
                flex-direction: column;
            }
            
            .nav-links a {
                text-align: center;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px 5px;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 20px;
            }
            
            table {
                font-size: 11px;
                min-width: 600px;
            }
            
            th, td {
                padding: 6px 4px;
            }
            
            .status {
                font-size: 11px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Recent Bookings (Last 50)</h1>
        <div class="nav-links">
            <a href="index.php">← Back to Home</a>
            <a href="/admin/login.php">Admin Panel</a>
        </div>
        
        <div class="total">Total Bookings: <?=count($bookings)?></div>
        
        <div class="table-responsive">
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
        </div>
    </div>
</body>
</html>
