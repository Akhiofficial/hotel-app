<?php
session_start();
require_once __DIR__ . '/../db.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Auto-archive old bookings (checkout older than 7 days)
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
// Only archive paid or confirmed bookings. Pending might need attention. Cancelled maybe?
$DB->query("UPDATE bookings SET status='archived' WHERE checkout < '$sevenDaysAgo' AND status IN ('paid', 'confirmed') AND status <> 'cancelled' AND checkout <> '0000-00-00'");

$statusFilter = $_GET['status'] ?? 'all';

if ($statusFilter === 'all') {
    // Show everything EXCEPT archived
    $whereClause = "WHERE (b.status != 'archived' OR b.status IS NULL)";
} else {
    // Show specific status (including 'archived' if selected)
    $whereClause = "WHERE b.status='" . $DB->real_escape_string($statusFilter) . "'";
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
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

                    <div class="status-filters" style="display:flex; gap:5px; align-items:center;">
                        <?php
                        $statuses = [
                            'all' => ['label' => 'All', 'icon' => 'fa-list', 'color' => '#6c757d'],
                            'pending' => ['label' => 'Pending', 'icon' => 'fa-clock', 'color' => '#f39c12'],
                            'confirmed' => ['label' => 'Confirmed', 'icon' => 'fa-check-double', 'color' => '#3498db'],
                            'paid' => ['label' => 'Paid', 'icon' => 'fa-check', 'color' => '#27ae60'],
                            'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'color' => '#666'],
                            'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-times', 'color' => '#e74c3c']
                        ];

                        foreach ($statuses as $key => $s):
                            $isActive = ($statusFilter === $key);
                            $bg = $isActive ? $s['color'] : '#f8f9fa';
                            $fg = $isActive ? 'white' : '#333';
                            $border = $isActive ? $s['color'] : '#ddd';
                            ?>
                            <a href="?status=<?= $key ?>" class="btn-filter" style="
                            padding: 8px 12px; 
                            border-radius: 4px; 
                            text-decoration: none; 
                            background: <?= $bg ?>; 
                            color: <?= $fg ?>; 
                            border: 1px solid <?= $border ?>;
                            font-size: 0.9rem;
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            transition: all 0.2s;
                        ">
                                <i class="fas <?= $s['icon'] ?>"></i> <?= $s['label'] ?>
                            </a>
                        <?php endforeach; ?>
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
                                        <?php if (!empty($b['customer_email'])): ?>
                                            <small><i class="fas fa-envelope"></i> <?= esc($b['customer_email']) ?></small><br>
                                        <?php endif; ?>
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
                                        <div class="action-buttons" style="display:flex; gap:5px;">

                                            <div class="status-dropdown" style="position:relative; display:inline-block;">
                                                <button onclick="toggleStatusDropdown(<?= esc($b['id']) ?>)"
                                                    class="btn-icon btn-secondary" title="Change Status">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div id="status-dropdown-<?= esc($b['id']) ?>" class="dropdown-content" style="
                                                    display: none;
                                                    background-color: #fff;
                                                    min-width: 150px;
                                                    box-shadow: 0px 5px 15px rgba(0,0,0,0.3);
                                                    border-radius: 6px;
                                                    overflow: hidden;
                                                    text-align: left;
                                                    border: 1px solid #eee;
                                                ">
                                                    <a href="javascript:void(0)"
                                                        onclick="updateStatus(<?= esc($b['id']) ?>, 'pending')"
                                                        style="color: #f39c12; padding: 10px 15px; text-decoration: none; display: block; border-bottom:1px solid #eee;"><i
                                                            class="fas fa-clock"></i> Pending</a>
                                                    <a href="javascript:void(0)"
                                                        onclick="updateStatus(<?= esc($b['id']) ?>, 'confirmed')"
                                                        style="color: #3498db; padding: 10px 15px; text-decoration: none; display: block; border-bottom:1px solid #eee;"><i
                                                            class="fas fa-check-double"></i> Confirm</a>
                                                    <a href="javascript:void(0)"
                                                        onclick="updateStatus(<?= esc($b['id']) ?>, 'paid')"
                                                        style="color: #27ae60; padding: 10px 15px; text-decoration: none; display: block; border-bottom:1px solid #eee;"><i
                                                            class="fas fa-check"></i> Mark Paid</a>
                                                    <a href="javascript:void(0)"
                                                        onclick="updateStatus(<?= esc($b['id']) ?>, 'archived')"
                                                        style="color: #666; padding: 10px 15px; text-decoration: none; display: block; border-bottom:1px solid #eee;"><i
                                                            class="fas fa-archive"></i> Archive</a>
                                                    <a href="javascript:void(0)"
                                                        onclick="updateStatus(<?= esc($b['id']) ?>, 'cancelled')"
                                                        style="color: #e74c3c; padding: 10px 15px; text-decoration: none; display: block;"><i
                                                            class="fas fa-times"></i> Cancel</a>
                                                </div>
                                            </div>

                                            <a href="invoice.php?id=<?= esc($b['id']) ?>" target="_blank"
                                                class="btn-icon btn-info" title="Invoice">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                            <button class="btn-icon btn-danger" onclick="deleteBooking(<?= esc($b['id']) ?>)"
                                                title="Delete Booking">
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

    <script src="admin-scripts.js?v=<?= time() ?>"></script>
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
                        if (status === 'archived') {
                            window.location.href = '?status=archived';
                        } else {
                            location.reload();
                        }
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
            if (!confirm('Are you sure you want to delete booking #' + id + '?\n\nThis action cannot be undone!')) {
                return;
            }

            // Show loading state
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
                    if (data.success) {
                        // Remove row with animation
                        if (row) {
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-20px)';
                            setTimeout(function () {
                                row.remove();
                                // Show success message
                                showNotification('Booking deleted successfully', 'success');
                                if (window.CapacityUpdates) { window.CapacityUpdates.emit(); }
                            }, 300);
                        } else {
                            if (window.CapacityUpdates) { window.CapacityUpdates.emit(); }
                            location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.msg || 'Failed to delete booking'));
                        if (row) {
                            row.style.opacity = '1';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the booking. Please try again.');
                    if (row) {
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
            setTimeout(function () {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(function () {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        function toggleStatusDropdown(id) {
            // Close all others
            document.querySelectorAll('.dropdown-content').forEach(el => {
                if (el.id !== 'status-dropdown-' + id) el.style.display = 'none';
            });

            var dropdown = document.getElementById('status-dropdown-' + id);
            var btn = event.currentTarget || event.target.closest('button');

            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            } else {
                // Calculate position
                var rect = btn.getBoundingClientRect();

                dropdown.style.display = 'block';
                dropdown.style.position = 'fixed'; // Break out of table container
                dropdown.style.top = (rect.bottom + 5) + 'px';
                dropdown.style.left = (rect.left - 100) + 'px'; // Shift left to align
                dropdown.style.zIndex = '10001';

                // Adjust if goes off screen
                var dropRect = dropdown.getBoundingClientRect();
                if (dropRect.right > window.innerWidth) {
                    dropdown.style.left = (window.innerWidth - dropRect.width - 20) + 'px';
                }
                if (dropRect.bottom > window.innerHeight) {
                    // formatting upward if too low
                    dropdown.style.top = (rect.top - dropRect.height - 5) + 'px';
                }
            }
        }

        // Close dropdowns when clicking outside
        window.onclick = function (event) {
            if (!event.target.closest('.btn-icon') && !event.target.closest('.dropdown-content')) {
                document.querySelectorAll('.dropdown-content').forEach(el => {
                    el.style.display = 'none';
                });
            }
        }

    </script>
</body>

</html>