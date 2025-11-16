<?php
session_start();
require_once __DIR__ . '/../db.php';
if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; }

$statusFilter = $_GET['status'] ?? 'all';
$whereClause = $statusFilter !== 'all' ? "WHERE b.status='" . $DB->real_escape_string($statusFilter) . "'" : "";

$bookings = $DB->query("SELECT b.*, r.title as room_title, r.code as room_code 
                        FROM bookings b 
                        LEFT JOIN rooms r ON r.id=b.room_id 
                        $whereClause
                        ORDER BY b.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Helper function to format dates safely
function formatDate($date) {
    // Handle NULL, empty string, or invalid dates
    if (empty($date) || $date === null || $date === 'NULL' || trim($date) === '') {
        return 'N/A';
    }
    
    // Handle MySQL zero dates
    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00' || $date === '1970-01-01') {
        return 'N/A';
    }
    
    // Try to parse the date
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp < 0) {
        return 'N/A';
    }
    
    return date('M d, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Admin Panel</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'admin-header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include 'admin-sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> Bookings Management</h1>
                <div class="header-actions">
                    <a href="create-booking.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Create Booking
                    </a>
                    <div class="filter-group">
                        <label>Filter by Status:</label>
                        <select id="statusFilter" onchange="window.location.href='?status='+this.value">
                            <option value="all" <?=$statusFilter=='all'?'selected':''?>>All Bookings</option>
                            <option value="pending" <?=$statusFilter=='pending'?'selected':''?>>Pending</option>
                            <option value="paid" <?=$statusFilter=='paid'?'selected':''?>>Paid</option>
                            <option value="confirmed" <?=$statusFilter=='confirmed'?'selected':''?>>Confirmed</option>
                            <option value="cancelled" <?=$statusFilter=='cancelled'?'selected':''?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer Details</th>
                            <th>Room</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Nights</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($bookings)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No bookings found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($bookings as $b): ?>
                            <tr id="booking-<?=esc($b['id'])?>">
                                <td><strong>#<?=esc($b['id'])?></strong></td>
                                <td>
                                    <strong><?=esc($b['customer_name'])?></strong><br>
                                    <small><i class="fas fa-envelope"></i> <?=esc($b['customer_email'])?></small><br>
                                    <small><i class="fas fa-phone"></i> <?=esc($b['customer_phone'])?></small>
                                </td>
                                <td>
                                    <?=esc($b['room_title'] ?? 'N/A')?><br>
                                    <small class="text-muted"><?=esc($b['room_code'] ?? 'N/A')?></small>
                                </td>
                                <td><?=!empty($b['checkin']) && $b['checkin'] !== '0000-00-00' ? date('M d, Y', strtotime($b['checkin'])) : 'N/A'?></td>
                                <td><?=formatDate($b['checkout'])?></td>
                                <td><?=esc($b['nights'])?></td>
                                <td>
                                    <strong>₹<?=number_format($b['total'], 2)?></strong><br>
                                    <small class="text-muted">GST: ₹<?=number_format($b['gst_amount'], 2)?></small>
                                </td>
                                <td>
                                    <?=ucfirst(str_replace('_', ' ', esc($b['payment_method'])))?>
                                    <?php if($b['bank_proof']): ?>
                                        <br><a href="../public/<?=esc($b['bank_proof'])?>" target="_blank" class="btn-link">
                                            <i class="fas fa-file-image"></i> View Proof
                                        </a>
                                    <?php endif; ?>
                                    <?php if(!empty($b['identity_card'])): ?>
                                        <br><a href="../public/<?=esc($b['identity_card'])?>" target="_blank" class="btn-link">
                                            <i class="fas fa-id-card"></i> View ID Card
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?=esc($b['status'])?>" id="status-<?=esc($b['id'])?>">
                                        <?=ucfirst(esc($b['status']))?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if($b['status'] === 'pending'): ?>
                                            <button class="btn-icon btn-success" onclick="markPaid(<?=esc($b['id'])?>)" title="Mark Paid">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon btn-warning" onclick="updateStatus(<?=esc($b['id'])?>, 'confirmed')" title="Confirm">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                        <button class="btn-icon btn-danger" onclick="updateStatus(<?=esc($b['id'])?>, 'cancelled')" title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <a href="invoice.php?id=<?=esc($b['id'])?>" target="_blank" class="btn-icon btn-info" title="Invoice">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <button class="btn-icon btn-danger" onclick="deleteBooking(<?=esc($b['id'])?>)" title="Delete Booking">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="admin-scripts.js"></script>
    <script>
    function markPaid(id) {
        if(!confirm('Mark booking #' + id + ' as paid?')) return;
        updateStatus(id, 'paid');
    }
    
    function updateStatus(id, status) {
        fetch('api.php?action=update_status', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + id + '&status=' + status
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.msg || 'Failed to update'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function deleteBooking(id) {
        if(!confirm('Are you sure you want to delete booking #' + id + '?\n\nThis action cannot be undone!')) {
            return;
        }
        
        // Show loading state
        const row = document.getElementById('booking-' + id);
        if(row) {
            row.style.opacity = '0.5';
        }
        
        fetch('api.php?action=delete_booking', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                // Remove row with animation
                if(row) {
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(function() {
                        row.remove();
                        // Show success message
                        showNotification('Booking deleted successfully', 'success');
                    }, 300);
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + (data.msg || 'Failed to delete booking'));
                if(row) {
                    row.style.opacity = '1';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the booking. Please try again.');
            if(row) {
                row.style.opacity = '1';
            }
        });
    }
    
    // Simple notification function
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 20px;background:' + 
            (type === 'success' ? '#50B848' : '#E74C3C') + ';color:white;border-radius:8px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
    </script>
</body>
</html>
