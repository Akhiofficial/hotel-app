<?php
session_start();
require_once __DIR__ . '/../db.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$col = $DB->query("SHOW COLUMNS FROM bookings LIKE 'archived_at'");
if (!$col || $col->num_rows === 0) {
    $DB->query("ALTER TABLE bookings ADD COLUMN archived_at DATETIME DEFAULT NULL AFTER status");
}

// Auto-archive past bookings
$today_archive = date('Y-m-d');
$DB->query("UPDATE bookings SET archived_at = NOW() 
            WHERE 
            (checkout < '$today_archive' AND checkout <> '0000-00-00' AND checkout IS NOT NULL)
            OR 
            ((checkout = '0000-00-00' OR checkout IS NULL) AND checkin < '$today_archive')
            AND archived_at IS NULL");


$statusFilter = $_GET['status'] ?? 'all';
$whereClause = "WHERE 1=1";
if ($statusFilter !== 'all') {
    if ($statusFilter === 'archived') {
        $whereClause .= " AND b.archived_at IS NOT NULL";
    } else {
        $whereClause .= " AND b.status='" . $DB->real_escape_string($statusFilter) . "'";
        $whereClause .= " AND b.archived_at IS NULL";
    }
} else {
    $whereClause .= " AND b.archived_at IS NULL";
}

$bookings = $DB->query("SELECT b.*, r.title as room_title, r.code as room_code 
                        FROM bookings b 
                        LEFT JOIN rooms r ON r.id=b.room_id 
                        $whereClause
                        ORDER BY b.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Helper function to format dates safely
function formatDate($date, $placeholder = 'N/A')
{
    if (empty($date) || $date === null || $date === 'NULL' || trim($date) === '') {
        error_log('formatDate: empty date');
        return $placeholder;
    }
    if ($date === '0000-00-00' || $date === '0000-00-00 00:00:00' || $date === '1970-01-01') {
        error_log('formatDate: invalid zero date ' . $date);
        return $placeholder;
    }
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp < 0) {
        error_log('formatDate: strtotime failed for ' . $date);
        return $placeholder;
    }
    return date('M d, Y', $timestamp);
}

function displayCheckout($checkin, $checkout, $nights)
{
    $out = formatDate($checkout, '');
    if ($out !== '')
        return $out;
    // derive from checkin + nights if possible
    if (!empty($checkin) && !empty($nights) && intval($nights) >= 1) {
        $d1ts = strtotime($checkin);
        if ($d1ts !== false) {
            $derived = date('M d, Y', strtotime("+" . intval($nights) . " day", $d1ts));
            error_log('Derived checkout from nights for booking display');
            return $derived;
        }
    }
    return 'Not checked out';
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
    <style>
        .btn-icon.confirm-delete {
            width: auto !important;
            padding: 0 10px !important;
            background-color: #dc3545 !important;
            color: white !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .btn-icon.confirm-delete i {
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <?php include 'admin-header.php'; ?>

    <!-- Mobile Sidebar Toggle -->
    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="admin-container">
        <div class="admin-sidebar" id="adminSidebar">
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
                            <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>All (Active)</option>
                            <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $statusFilter == 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="confirmed" <?= $statusFilter == 'confirmed' ? 'selected' : '' ?>>Confirmed
                            </option>
                            <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled
                            </option>
                            <option value="archived" <?= $statusFilter == 'archived' ? 'selected' : '' ?>>Archived</option>
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
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No bookings found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr id="booking-<?= esc($b['id']) ?>">
                                    <td><strong>#<?= esc($b['id']) ?></strong></td>
                                    <td>
                                        <strong><?= esc($b['customer_name']) ?></strong><br>
                                        <small><i class="fas fa-envelope"></i> <?= esc($b['customer_email']) ?></small><br>
                                        <small><i class="fas fa-phone"></i> <?= esc($b['customer_phone']) ?></small>
                                    </td>
                                    <td>
                                        <?= esc($b['room_title'] ?? 'N/A') ?><br>
                                        <small class="text-muted"><?= esc($b['room_code'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><?= formatDate($b['checkin']) ?></td>
                                    <td><?= displayCheckout($b['checkin'], $b['checkout'], $b['nights']) ?></td>
                                    <td><?= esc($b['nights']) ?></td>
                                    <td>
                                        <strong>₹<?= number_format($b['total'], 2) ?></strong><br>
                                        <small class="text-muted">GST: ₹<?= number_format($b['gst_amount'], 2) ?></small>
                                    </td>
                                    <td>
                                        <?= ucfirst(str_replace('_', ' ', esc($b['payment_method']))) ?>
                                        <?php if ($b['bank_proof']): ?>
                                            <br><a href="../public/<?= esc($b['bank_proof']) ?>" target="_blank" class="btn-link">
                                                <i class="fas fa-file-image"></i> View Proof
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($b['identity_card'])): ?>
                                            <br><a href="../public/<?= esc($b['identity_card']) ?>" target="_blank"
                                                class="btn-link">
                                                <i class="fas fa-id-card"></i> View ID Card
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= esc($b['status']) ?>"
                                            id="status-<?= esc($b['id']) ?>">
                                            <?= ucfirst(esc($b['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($b['status'] === 'pending'): ?>
                                                <button class="btn-icon btn-success" onclick="markPaid(<?= esc($b['id']) ?>)"
                                                    title="Mark Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-icon btn-warning"
                                                onclick="updateStatus(<?= esc($b['id']) ?>, 'confirmed')" title="Confirm">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                            <button class="btn-icon btn-danger"
                                                onclick="updateStatus(<?= esc($b['id']) ?>, 'cancelled')" title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <a href="invoice.php?id=<?= esc($b['id']) ?>" target="_blank"
                                                class="btn-icon btn-info" title="Invoice">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                            <button type="button" class="btn-icon btn-danger"
                                                onclick="removeBooking(<?= esc($b['id']) ?>, this)" title="Delete Booking">
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
        // Emit capacity update if redirected after creation
        (function () {
            try {
                var params = new URLSearchParams(window.location.search);
                if (params.get('capacity_updated') === '1' && window.CapacityUpdates) { window.CapacityUpdates.emit(); }
            } catch (e) { }
        })();
        function markPaid(id) {
            if (!confirm('Mark booking #' + id + ' as paid?')) return;
            updateStatus(id, 'paid');
        }

        function updateStatus(id, status) {
            fetch('api.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id + '&status=' + status
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (window.CapacityUpdates) { window.CapacityUpdates.emit(); }
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

        let deleteTimeout;

        function removeBooking(id, btn) {
            console.log('Initiating delete for booking #' + id);

            // If button is not in "confirm" state, switch to it
            if (!btn.classList.contains('confirm-delete')) {
                // Reset any other delete buttons
                document.querySelectorAll('.btn-danger.confirm-delete').forEach(b => {
                    b.classList.remove('confirm-delete');
                    b.innerHTML = '<i class="fas fa-trash"></i>';
                    // Reset styles
                    b.style.width = '';
                    b.style.padding = '';
                    b.style.backgroundColor = '';
                });

                btn.classList.add('confirm-delete');
                btn.innerHTML = '<i class="fas fa-check"></i> Confirm?';

                // FORCE STYLES via JS to ensure it works
                btn.style.setProperty('width', 'auto', 'important');
                btn.style.setProperty('min-width', '110px', 'important');
                btn.style.setProperty('padding-left', '15px', 'important');
                btn.style.setProperty('padding-right', '15px', 'important');
                btn.style.setProperty('background-color', '#dc3545', 'important');
                btn.style.setProperty('font-weight', 'bold', 'important');
                btn.style.setProperty('color', '#ffffff', 'important');
                btn.style.setProperty('text-align', 'center', 'important');
                btn.style.setProperty('display', 'inline-flex', 'important');

                // Auto-reset after 3 seconds
                if (deleteTimeout) clearTimeout(deleteTimeout);
                deleteTimeout = setTimeout(() => {
                    if (btn) {
                        btn.classList.remove('confirm-delete');
                        btn.innerHTML = '<i class="fas fa-trash"></i>';
                        // Reset styles
                        btn.style.width = '';
                        btn.style.padding = '';
                        btn.style.backgroundColor = '';
                        btn.style.fontWeight = '';
                        btn.style.minWidth = '';
                    }
                }, 3000);
                return;
            }

            // If already in confirm state, proceed with delete
            if (deleteTimeout) clearTimeout(deleteTimeout);
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const row = document.getElementById('booking-' + id);
            if (row) {
                row.style.opacity = '0.5';
            }

            fetch('api.php?action=delete_booking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            })
                .then(r => r.json())
                .then(data => {
                    console.log('Delete response:', data);
                    if (data.success) {
                        if (row) {
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-20px)';
                            setTimeout(function () {
                                row.remove();
                                showNotification('Booking deleted successfully', 'success');
                                if (window.CapacityUpdates) { window.CapacityUpdates.emit(); }
                            }, 300);
                        } else {
                            if (window.CapacityUpdates) { window.CapacityUpdates.emit(); }
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.msg || 'Failed to delete booking'));
                        if (row) row.style.opacity = '1';
                        btn.disabled = false;
                        btn.classList.remove('confirm-delete');
                        btn.innerHTML = '<i class="fas fa-trash"></i>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please check console.');
                    if (row) row.style.opacity = '1';
                    btn.disabled = false;
                    btn.classList.remove('confirm-delete');
                    btn.innerHTML = '<i class="fas fa-trash"></i>';
                });
        }

        // Simple notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 20px;background:' +
                (type === 'success' ? '#50B848' : '#E74C3C') + ';color:white;border-radius:8px;z-index:10000;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(function () {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(function () {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>

</html>