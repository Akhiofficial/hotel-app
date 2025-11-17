<?php
session_start();
require_once __DIR__ . '/../db.php';
if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; }

// Get today's date
$today = date('Y-m-d');

// Get all active rooms with occupancy status
// IMPORTANT: Count ALL confirmed/paid bookings (past, present, and future) to calculate availability
// This ensures that reserved rooms are not shown as available
$rooms = $DB->query("SELECT r.*, 
                     (SELECT COUNT(*) FROM bookings b 
                      WHERE b.room_id = r.id 
                      AND b.status IN ('confirmed', 'paid')
                      AND b.checkin IS NOT NULL 
                      AND b.checkin <> '0000-00-00'
                      -- Count all confirmed/paid bookings regardless of date
                      -- This includes past, current, and future bookings
                      ) as total_confirmed_bookings,
                     (SELECT COUNT(*) FROM bookings b 
                      WHERE b.room_id = r.id 
                      AND b.status IN ('confirmed', 'paid')
                      AND b.checkin IS NOT NULL 
                      AND b.checkin <> '0000-00-00'
                      AND (
                          -- Currently active bookings (checkin <= today < checkout)
                          (b.checkout IS NOT NULL 
                           AND b.checkout <> '0000-00-00'
                           AND DATE(b.checkin) <= DATE('$today')
                           AND DATE(b.checkout) > DATE('$today'))
                          OR
                          -- Invalid checkout but checkin is today or past
                          ((b.checkout IS NULL OR b.checkout = '0000-00-00')
                           AND DATE(b.checkin) <= DATE('$today'))
                      )) as currently_occupied_count
                     FROM rooms r 
                     WHERE r.status='active' 
                     ORDER BY r.code")->fetch_all(MYSQLI_ASSOC);

// Enhance rooms with booking details
foreach($rooms as &$room) {
    $total_quantity = intval($room['quantity'] ?? 1);
    // Use total_confirmed_bookings for availability calculation (includes future bookings)
    $total_confirmed = intval($room['total_confirmed_bookings'] ?? 0);
    // Use currently_occupied_count to determine if room is currently occupied (for display)
    $currently_occupied = intval($room['currently_occupied_count'] ?? 0);
    
    // Available count = total quantity - all confirmed/paid bookings (including future)
    $room['available_count'] = max(0, $total_quantity - $total_confirmed);
    // Room is "occupied" if currently active booking exists OR if available_count is 0 (fully booked)
    $room['is_occupied'] = ($currently_occupied > 0 || $room['available_count'] <= 0);
    $room['is_fully_occupied'] = ($room['available_count'] <= 0 && $total_quantity > 0);
    
    // Get current booking details if currently occupied
    if($currently_occupied > 0) {
        $room['current_booking'] = $DB->query("SELECT b.*, r.title as room_title 
                                               FROM bookings b 
                                               LEFT JOIN rooms r ON r.id = b.room_id 
                                               WHERE b.room_id = {$room['id']} 
                                               AND b.status IN ('confirmed', 'paid')
                                               AND b.checkin IS NOT NULL 
                                               AND b.checkin <> '0000-00-00'
                                               AND (
                                                   -- Currently active
                                                   (b.checkout IS NOT NULL 
                                                    AND b.checkout <> '0000-00-00'
                                                    AND DATE(b.checkin) <= DATE('$today')
                                                    AND DATE(b.checkout) > DATE('$today'))
                                                   OR
                                                   -- Invalid checkout but checkin is today or past
                                                   ((b.checkout IS NULL OR b.checkout = '0000-00-00')
                                                    AND DATE(b.checkin) <= DATE('$today'))
                                               )
                                               ORDER BY b.checkin DESC 
                                               LIMIT 1")->fetch_assoc();
    }
    
    // If fully booked but no current booking, get the next upcoming booking
    if($room['is_fully_occupied'] && empty($room['current_booking'])) {
        $room['next_booking'] = $DB->query("SELECT b.*, r.title as room_title 
                                            FROM bookings b 
                                            LEFT JOIN rooms r ON r.id = b.room_id 
                                            WHERE b.room_id = {$room['id']} 
                                            AND b.status IN ('confirmed', 'paid')
                                            AND b.checkin IS NOT NULL 
                                            AND b.checkin <> '0000-00-00'
                                            ORDER BY b.checkin ASC 
                                            LIMIT 1")->fetch_assoc();
    }
    
    // Get next upcoming booking if not currently occupied and not fully booked
    if(!$room['is_occupied'] && $room['available_count'] > 0) {
        $room['next_booking'] = $DB->query("SELECT b.*, r.title as room_title   
                                            FROM bookings b 
                                            LEFT JOIN rooms r ON r.id = b.room_id 
                                            WHERE b.room_id = {$room['id']} 
                                            AND b.status IN ('confirmed', 'paid')
                                            AND b.checkin IS NOT NULL 
                                            AND b.checkin <> '0000-00-00'
                                            AND DATE(b.checkin) > DATE('$today')
                                            ORDER BY b.checkin ASC 
                                            LIMIT 1")->fetch_assoc();
    }
    
    // Debug: Get all bookings for this room to help troubleshoot
    $room['debug_bookings'] = $DB->query("SELECT id, status, checkin, checkout, 
                                          DATE(checkin) as checkin_date, 
                                          DATE(checkout) as checkout_date,
                                          CASE 
                                            WHEN (checkout IS NULL OR checkout = '0000-00-00') AND DATE(checkin) <= DATE('$today') THEN 'ACTIVE (no checkout)'
                                            WHEN checkout IS NOT NULL AND checkout <> '0000-00-00' AND DATE(checkin) <= DATE('$today') AND DATE(checkout) > DATE('$today') THEN 'ACTIVE'
                                            WHEN DATE(checkin) > DATE('$today') THEN 'FUTURE'
                                            WHEN checkout IS NOT NULL AND checkout <> '0000-00-00' AND DATE(checkout) <= DATE('$today') THEN 'PAST'
                                            ELSE 'UNKNOWN'
                                          END as date_status,
                                          CASE
                                            WHEN status IN ('confirmed', 'paid') THEN 'YES'
                                            ELSE 'NO'
                                          END as will_be_counted
                                          FROM bookings 
                                          WHERE room_id = {$room['id']} 
                                          AND status IN ('confirmed', 'paid', 'pending')
                                          ORDER BY checkin DESC")->fetch_all(MYSQLI_ASSOC);
}
unset($room);

// Separate occupied and available rooms
$occupiedRooms = array_filter($rooms, function($r) { return $r['is_occupied']; });
$availableRooms = array_filter($rooms, function($r) { return !$r['is_occupied']; });

// Debug mode - set to true to see booking details
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Occupancy Glance View - Admin Panel</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .debug-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .debug-panel h4 {
            margin-top: 0;
            color: #495057;
        }
        .debug-booking {
            background: white;
            padding: 8px;
            margin: 5px 0;
            border-left: 3px solid #007bff;
            border-radius: 4px;
        }
        .debug-booking.active { border-left-color: #28a745; }
        .debug-booking.future { border-left-color: #ffc107; }
        .debug-booking.past { border-left-color: #6c757d; }
        .will-be-counted {
            font-weight: bold;
            color: #28a745;
        }
        .will-not-be-counted {
            font-weight: bold;
            color: #dc3545;
        }
        .upcoming-booking {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 8px;
            margin-top: 8px;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include 'admin-header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include 'admin-sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-eye"></i> Room Occupancy Glance View</h1>
                <p>Quick overview of all room statuses - <?=date('F d, Y')?> 
                </p>
            </div>

            <?php if($debug_mode): ?>
            <div class="debug-panel">
                <h4>Debug Information - Today: <?=$today?></h4>
                <p><strong>Note:</strong> All confirmed/paid bookings (past, present, and future) are counted in availability calculation.</p>
                <p><strong>Available Count = Total Quantity - All Confirmed/Paid Bookings</strong></p>
                <?php foreach($rooms as $room): ?>
                    <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 4px;">
                        <strong><?=esc($room['code'])?> - <?=esc($room['title'])?></strong><br>
                        <small>
                            Total Quantity: <?=$room['quantity'] ?? 1?> | 
                            Total Confirmed Bookings: <strong style="color: #28a745;"><?=$room['total_confirmed_bookings'] ?? 0?></strong> | 
                            Currently Occupied: <strong style="color: <?=$room['currently_occupied_count'] > 0 ? '#dc3545' : '#28a745'?>"><?=$room['currently_occupied_count'] ?? 0?></strong> | 
                            Available: <strong style="color: <?=$room['available_count'] > 0 ? '#28a745' : '#dc3545'?>"><?=$room['available_count']?></strong>
                        </small>
                        <?php if(!empty($room['debug_bookings'])): ?>
                            <div style="margin-top: 8px;">
                                <strong>All Bookings:</strong>
                                <?php foreach($room['debug_bookings'] as $dbg): ?>
                                    <div class="debug-booking <?=strtolower(str_replace([' ', '(', ')'], ['-', '', ''], $dbg['date_status']))?>">
                                        ID: <?=$dbg['id']?> | Status: <strong><?=$dbg['status']?></strong> | 
                                        Check-in: <?=$dbg['checkin']?> | Check-out: <?=$dbg['checkout'] == '0000-00-00' ? '<span style="color: red;">INVALID</span>' : $dbg['checkout']?> | 
                                        <strong><?=$dbg['date_status']?></strong> | 
                                        <span class="<?=$dbg['will_be_counted'] == 'YES' ? 'will-be-counted' : 'will-not-be-counted'?>">
                                            <?=$dbg['will_be_counted'] == 'YES' ? '✓ COUNTED IN AVAILABILITY' : '✗ NOT COUNTED'?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #6c757d; margin-top: 5px;">No bookings found</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="availableCount"><?=count($availableRooms)?></h3>
                        <p>Available Rooms</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="occupiedCount"><?=count($occupiedRooms)?></h3>
                        <p>Occupied Rooms</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="totalCount"><?=count($rooms)?></h3>
                        <p>Total Rooms</p>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="glance-tabs">
                <button class="glance-tab active" data-filter="all" onclick="filterRooms('all')">
                    <i class="fas fa-th"></i> All Rooms (<span id="allCount"><?=count($rooms)?></span>)
                </button>
                <button class="glance-tab" data-filter="occupied" onclick="filterRooms('occupied')">
                    <i class="fas fa-bed"></i> Occupied (<span id="occupiedTabCount"><?=count($occupiedRooms)?></span>)
                </button>
                <button class="glance-tab" data-filter="available" onclick="filterRooms('available')">
                    <i class="fas fa-check-circle"></i> Available (<span id="availableTabCount"><?=count($availableRooms)?></span>)
                </button>
                <button class="glance-tab" onclick="refreshGlanceView()" style="margin-left: auto;">
                    <i class="fas fa-sync-alt"></i> Refresh Now
                </button>
            </div>

            <!-- Room Cards Grid -->
            <div class="glance-grid" id="glanceGrid">
                <?php foreach($rooms as $room): ?>
                <div class="glance-room-card <?=$room['is_occupied'] ? 'occupied' : 'available'?>" 
                     data-status="<?=$room['is_occupied'] ? 'occupied' : 'available'?>"
                     data-room-id="<?=esc($room['id'])?>">
                    <div class="glance-card-header">
                        <div class="glance-room-info">
                            <h3><?=esc($room['code'])?></h3>
                            <p><?=esc($room['title'])?></p>
                        </div>
                        <div class="glance-status-badge <?=$room['is_occupied'] ? 'badge-red' : 'badge-green'?>">
                            <i class="fas fa-<?=$room['is_occupied'] ? 'times-circle' : 'check-circle'?>"></i>
                            <?=$room['is_occupied'] ? 'Occupied' : 'Available'?>
                        </div>
                    </div>
                    
                    <div class="glance-card-body">
                        <?php if(intval($room['available_count']) === 0): ?>
                        <div class="glance-detail-row">
                            <i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i>
                            <span><strong style="color:#dc3545;">Fully Booked</strong></span>
                        </div>
                        <?php endif; ?>

                        <div class="glance-detail-row">
                            <i class="fas fa-bed"></i>
                            <span>
                                <strong>Available:</strong>
                                <?=esc($room['available_count'])?> / <?=esc($room['quantity'] ?? 1)?> rooms
                            </span>
                        </div>
                        <div class="glance-detail-row">
                            <i class="fas fa-users"></i>
                            <span><strong>Capacity:</strong> <?=$room['capacity']?> guest(s)</span>
                        </div>
                        <div class="glance-detail-row">
                            <i class="fas fa-rupee-sign"></i>
                            <span><strong>Price:</strong> ₹<?=number_format($room['price'], 2)?>/night</span>
                        </div>
                        <?php if(!empty($room['next_booking'])): 
                            $nextBooking = $room['next_booking'];
                        ?>
                            <div class="upcoming-booking">
                                <i class="fas fa-calendar-alt" style="color: #ffc107;"></i>
                                <strong>Upcoming:</strong> <?=esc($nextBooking['customer_name'])?> on <?=date('M d, Y', strtotime($nextBooking['checkin']))?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="glance-card-footer">
                        <?php if($room['is_occupied'] && !empty($room['current_booking'])): ?>
                            <a href="bookings.php#booking-<?=esc($room['current_booking']['id'])?>" class="btn-view-booking">
                                <i class="fas fa-eye"></i> View Booking
                            </a>
                        <?php elseif($room['is_fully_occupied'] && !empty($room['next_booking'])): ?>
                            <a href="bookings.php#booking-<?=esc($room['next_booking']['id'])?>" class="btn-view-booking">
                                <i class="fas fa-eye"></i> View Next Booking
                            </a>
                        <?php else: ?>
                            <a href="create-booking.php?room_id=<?=esc($room['id'])?>" class="btn-book-room">
                                <i class="fas fa-plus"></i> Book Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="admin-scripts.js"></script>
    <script>
    function filterRooms(filter) {
        // Update active tab
        document.querySelectorAll('.glance-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        const activeTab = document.querySelector(`.glance-tab[data-filter="${filter}"]`);
        if(activeTab) activeTab.classList.add('active');
        
        // Filter cards
        const cards = document.querySelectorAll('.glance-room-card');
        cards.forEach(card => {
            if(filter === 'all') {
                card.style.display = 'block';
            } else {
                const status = card.getAttribute('data-status');
                card.style.display = (status === filter) ? 'block' : 'none';
            }
        });
    }
    
    // Auto-refresh function
    function refreshGlanceView() {
        const refreshBtn = document.querySelector('.glance-tab:last-child');
        if(refreshBtn) {
            const icon = refreshBtn.querySelector('i');
            if(icon) {
                icon.classList.add('fa-spin');
            }
        }
        
        // Reload the page to get fresh data
        setTimeout(() => {
            window.location.reload();
        }, 500);
    }
    
    // Auto-refresh every 30 seconds
    let autoRefreshInterval = setInterval(refreshGlanceView, 30000);
    
    // Update last update time
    function updateLastUpdateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString();
        const lastUpdateEl = document.getElementById('lastUpdate');
        if(lastUpdateEl) {
            lastUpdateEl.innerHTML = `<i class="fas fa-sync-alt"></i> Last updated: ${timeStr}`;
        }
    }
    
    // Update time every minute
    setInterval(updateLastUpdateTime, 60000);
    
    // Clear interval when page is hidden (to save resources)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(autoRefreshInterval);
        } else {
            autoRefreshInterval = setInterval(refreshGlanceView, 30000);
        }
    });
    </script>
</body>
</html>
