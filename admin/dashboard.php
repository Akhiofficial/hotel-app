<?php
session_start();
require_once __DIR__ . '/../db.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Get statistics
$totalBookings = $DB->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'];
$pendingBookings = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE status='pending'")->fetch_assoc()['c'];
$paidBookings = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE status='paid'")->fetch_assoc()['c'];
$totalRevenue = $DB->query("SELECT SUM(total) as s FROM bookings WHERE status IN ('paid','confirmed')")->fetch_assoc()['s'] ?? 0;
$totalRooms = $DB->query("SELECT COUNT(*) as c FROM rooms WHERE status='active'")->fetch_assoc()['c'];

// Current billing (today's revenue)
$today = date('Y-m-d');
$currentBilling = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) = '$today' AND status IN ('paid','confirmed')")->fetch_assoc()['s'] ?? 0;

// Get rooms with occupancy status
$todayDate = date('Y-m-d');
$rooms = $DB->query("SELECT r.*, 
                     (SELECT COUNT(*) FROM bookings b 
                      WHERE b.room_id = r.id 
                      AND b.status <> 'cancelled' 
                      AND '$todayDate' >= b.checkin 
                      AND '$todayDate' < b.checkout) as is_occupied
                     FROM rooms r 
                     WHERE r.status='active' 
                     ORDER BY r.code")->fetch_all(MYSQLI_ASSOC);

$recentBookings = $DB->query("SELECT b.*, r.title as room_title FROM bookings b LEFT JOIN rooms r ON r.id=b.room_id ORDER BY b.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

function safeDateDisplay($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return 'N/A';
    }
    $ts = strtotime($date);
    return $ts ? date('M d, Y', $ts) : 'N/A';
}
function displayCheckout($checkin, $checkout, $nights)
{
    if (!empty($checkout) && $checkout !== '0000-00-00') {
        return safeDateDisplay($checkout);
    }
    if (!empty($checkin) && !empty($nights) && intval($nights) >= 1) {
        $base = strtotime($checkin);
        if ($base) {
            return date('M d, Y', strtotime('+' . intval($nights) . ' day', $base));
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
    <title>Admin Dashboard - Hotel Reservation System</title>
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet"
        onerror="this.onerror=null; this.href='https://unpkg.com/fullcalendar@6.1.8/main.min.css';">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Remove scripts from head - move to bottom -->
</head>

<body>
    <?php include 'admin-header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include 'admin-sidebar.php'; ?>
        </div>

        <div class="admin-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Welcome back, <?= esc($_SESSION['admin']) ?>!</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalBookings ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $pendingBookings ?></h3>
                        <p>Pending Payments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $paidBookings ?></h3>
                        <p>Confirmed Bookings</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <span class="icon-text">Rs</span>
                    </div>
                    <div class="stat-info">
                        <h3>₹<?= number_format($totalRevenue, 2) ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalRooms ?></h3>
                        <p>Active Rooms</p>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column -->
                <div class="dashboard-left">
                    <!-- Occupancy Calendar -->
                    <div class="calendar-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-calendar-alt"></i> Occupancy Calendar</h3>
                            <div>
                                <select id="roomFilter" class="period-select" style="margin-right: 10px;">
                                    <option value="">All Rooms</option>
                                    <?php
                                    $allRooms = $DB->query("SELECT * FROM rooms WHERE status='active' ORDER BY code")->fetch_all(MYSQLI_ASSOC);
                                    foreach ($allRooms as $room):
                                        ?>
                                        <option value="<?= esc($room['id']) ?>"><?= esc($room['code']) ?> -
                                            <?= esc($room['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-primary" onclick="openBookingModal()">
                                    <i class="fas fa-plus"></i> New Booking
                                </button>
                            </div>
                        </div>
                        <div id="calendar" style="min-height: 500px;"></div>
                        <div id="calendarError"
                            style="display:none; padding: 20px; text-align: center; color: #666; background: #f8f9fa; border-radius: 8px; margin-top: 10px;">
                            <i class="fas fa-info-circle"></i> <span id="calendarErrorText">Loading calendar...</span>
                        </div>
                    </div>

                    <!-- Room Management Table -->
                    <!-- <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bed"></i> Room Management</h3>
                            <a href="rooms.php" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Room
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Room</th>
                                            <th>Status</th>
                                            <th>Price/Night</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rooms)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No rooms found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($rooms as $room): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= esc($room['code']) ?></strong><br>
                                                    <small><?= esc($room['title']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($room['is_occupied'] > 0): ?>
                                                        <span class="status-badge status-occupied">Occupied</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-vacant">Vacant</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>₹<?= number_format($room['price'], 2) ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="rooms.php?edit=<?= esc($room['id']) ?>" class="btn-icon btn-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="post" action="rooms.php" style="display:inline;" onsubmit="return confirm('Delete this room?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= esc($room['id']) ?>">
                                                            <button type="submit" class="btn-icon btn-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div> -->
                </div>

                <!-- Right Column -->
                <div class="dashboard-right">
                    <!-- Weekly/Monthly Revenue Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3><i class="fas fa-chart-line"></i> Weekly / Monthly Revenue</h3>
                            <select id="revenuePeriod" class="period-select">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <canvas id="revenueChart"></canvas>
                    </div>

                    <!-- Current Billing Card -->
                    <div class="billing-card">
                        <div class="billing-header">
                            <h3>Current Billing</h3>
                            <span class="billing-date"><?= date('M d, Y') ?></span>
                        </div>
                        <div class="billing-amount">
                            <span class="currency">₹</span>
                            <span class="amount"><?= number_format($currentBilling, 2) ?></span>
                        </div>
                        <p class="billing-label">Tax Billing</p>
                    </div>

                    <!-- GST Billing / Add Room Card -->
                    <div class="gst-billing-card">
                        <div class="gst-billing-content">
                            <div class="gst-billing-left">
                                <h3><i class="fas fa-receipt"></i> GST Billing</h3>
                                <p>View and manage invoices</p>
                            </div>
                            <div class="gst-billing-right">
                                <a href="bookings.php" class="btn-primary">
                                    <i class="fas fa-file-invoice"></i> View Bills
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="recent-bookings">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Recent Bookings</h3>
                    <a href="bookings.php" class="btn-view-all">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentBookings)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No bookings found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td>#<?= esc($b['id']) ?></td>
                                        <td>
                                            <strong><?= esc($b['customer_name']) ?></strong><br>
                                            <small><?= esc($b['customer_email']) ?></small>
                                        </td>
                                        <td><?= esc($b['room_title'] ?? 'N/A') ?></td>
                                        <td><?= safeDateDisplay($b['checkin']) ?></td>
                                        <td><?= displayCheckout($b['checkin'], $b['checkout'], $b['nights']) ?></td>
                                        <td>₹<?= number_format($b['total'], 2) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= esc($b['status']) ?>">
                                                <?= ucfirst(esc($b['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($b['status'] === 'pending'): ?>
                                                    <button class="btn-icon btn-success" onclick="quickVerify(<?= esc($b['id']) ?>)"
                                                        title="Mark Paid">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="invoice.php?id=<?= esc($b['id']) ?>" target="_blank"
                                                    class="btn-icon btn-info" title="View Bill">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                <a href="bookings.php#booking-<?= esc($b['id']) ?>" class="btn-icon btn-primary"
                                                    title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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
    </div>

    <!-- Load scripts at the bottom, in correct order -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="admin-scripts.js?v=<?=time()?>"></script>

    <!-- Load FullCalendar with proper callback -->
    <script>
        // Initialize charts
        let revenueChart;
        let calendar;

        // Function to load FullCalendar dynamically with multiple CDN fallbacks
        const fullCalendarCDNs = [
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js',
            'https://unpkg.com/fullcalendar@6.1.8/main.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.8/index.global.min.js',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.js',
            'https://unpkg.com/fullcalendar@6.1.10/main.min.js'
        ];

        let currentCDNIndex = 0;

        function loadFullCalendar(callback) {
            // Check if already loaded
            if (typeof FullCalendar !== 'undefined') {
                console.log('FullCalendar already loaded');
                callback();
                return;
            }

            // Try local file first
            const script = document.createElement('script');
            script.src = 'js/fullcalendar/main.min.js';
            script.async = true;

            script.onload = function () {
                setTimeout(function () {
                    if (typeof FullCalendar !== 'undefined') {
                        console.log('FullCalendar loaded from local file');
                        callback();
                    } else {
                        console.error('Local FullCalendar failed, trying CDN...');
                        tryCDN(callback);
                    }
                }, 100);
            };

            script.onerror = function () {
                console.warn('Local FullCalendar not found, trying CDN...');
                tryCDN(callback);
            };

            document.head.appendChild(script);
        }

        function tryCDN(callback) {
            if (currentCDNIndex >= fullCalendarCDNs.length) {
                console.error('All FullCalendar CDNs exhausted');
                showCalendarError('Calendar library failed to load from all CDN sources. Please check your internet connection or download FullCalendar locally.');
                return;
            }

            const cdnUrl = fullCalendarCDNs[currentCDNIndex];
            console.log(`Attempting to load FullCalendar from CDN ${currentCDNIndex + 1}/${fullCalendarCDNs.length}: ${cdnUrl}`);

            const script = document.createElement('script');
            script.src = cdnUrl;
            script.async = true;
            script.crossOrigin = 'anonymous';

            script.onload = function () {
                // Wait a moment for the library to initialize
                setTimeout(function () {
                    if (typeof FullCalendar !== 'undefined') {
                        console.log('FullCalendar loaded successfully from:', cdnUrl);
                        callback();
                    } else {
                        console.warn('FullCalendar script loaded but object not available, trying next CDN...');
                        currentCDNIndex++;
                        loadFullCalendar(callback);
                    }
                }, 100);
            };

            script.onerror = function () {
                console.error(`CDN ${currentCDNIndex + 1} failed:`, cdnUrl);
                currentCDNIndex++;
                loadFullCalendar(callback);
            };

            document.head.appendChild(script);
        }

        function showCalendarError(message) {
            const errorDiv = document.getElementById('calendarError');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message +
                    '<br><small style="margin-top: 10px; display: block;">If this persists, you may need to download FullCalendar locally or check your firewall settings.</small>';
            }
        }

        function loadRevenueChart(period) {
            fetch(`api.php?action=revenue_stats&period=${period}`)
                .then(r => r.json())
                .then(data => {
                    const ctx = document.getElementById('revenueChart');
                    if (!ctx) return;

                    const chartCtx = ctx.getContext('2d');
                    if (revenueChart) revenueChart.destroy();

                    revenueChart = new Chart(chartCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Revenue',
                                    data: data.values,
                                    backgroundColor: 'rgba(26, 77, 46, 0.6)',
                                    borderColor: 'rgba(26, 77, 46, 1)',
                                    borderWidth: 1,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Trend',
                                    data: data.values,
                                    type: 'line',
                                    borderColor: 'rgba(80, 184, 72, 1)',
                                    backgroundColor: 'rgba(80, 184, 72, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    yAxisID: 'y'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return '₹' + value.toLocaleString();
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading revenue chart:', error);
                });
        }

        // Initialize calendar
        function initCalendar() {
            // Double check FullCalendar is loaded
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar library not loaded');
                showCalendarError('Calendar library failed to load. Please refresh the page.');
                return;
            }

            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) {
                console.error('Calendar element not found');
                return;
            }

            try {
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    events: function (fetchInfo, successCallback, failureCallback) {
                        const roomId = document.getElementById('roomFilter')?.value || '';
                        const url = `api.php?action=list_events&room_id=${roomId}`;

                        console.log('Fetching calendar events from:', url);

                        fetch(url)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok: ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Calendar events received:', data);
                                if (data && Array.isArray(data)) {
                                    successCallback(data);
                                    // Show/hide error message
                                    const errorDiv = document.getElementById('calendarError');
                                    if (data.length === 0) {
                                        if (errorDiv) {
                                            errorDiv.style.display = 'block';
                                            errorDiv.innerHTML = '<i class="fas fa-info-circle"></i> No bookings to display. Create a booking to see it on the calendar.';
                                        }
                                    } else {
                                        if (errorDiv) errorDiv.style.display = 'none';
                                    }
                                } else {
                                    throw new Error('Invalid data format: ' + JSON.stringify(data));
                                }
                            })
                            .catch(error => {
                                console.error('Error loading calendar events:', error);
                                failureCallback(error);
                                const errorDiv = document.getElementById('calendarError');
                                if (errorDiv) {
                                    errorDiv.style.display = 'block';
                                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error loading calendar events. Please check console for details.';
                                }
                            });
                    },
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek'
                    },
                    eventClick: function (info) {
                        window.location.href = 'bookings.php#booking-' + info.event.id;
                    },
                    dateClick: function (info) {
                        openBookingModal(info.dateStr);
                    },
                    eventMouseEnter: function (info) {
                        info.el.style.cursor = 'pointer';
                    },
                    eventDisplay: 'block'
                });

                calendar.render();
                console.log('Calendar initialized successfully');
            } catch (error) {
                console.error('Error initializing calendar:', error);
                showCalendarError('Error initializing calendar: ' + error.message);
            }
        }

        // Setup event handlers
        function setupEventHandlers() {
            // Room filter change
            const roomFilter = document.getElementById('roomFilter');
            if (roomFilter) {
                roomFilter.addEventListener('change', function () {
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                });
            }

            // Load initial chart
            loadRevenueChart('weekly');

            // Period change handler
            const revenuePeriod = document.getElementById('revenuePeriod');
            if (revenuePeriod) {
                revenuePeriod.addEventListener('change', function () {
                    loadRevenueChart(this.value);
                });
            }
        }

        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function () {
            // Load FullCalendar first, then initialize
            loadFullCalendar(function () {
                // FullCalendar is now loaded, initialize calendar
                initCalendar();
                // Setup other event handlers
                setupEventHandlers();
            });
        });

        function refreshCalendar() {
            if (calendar) {
                calendar.refetchEvents();
            } else {
                console.warn('Calendar not initialized yet');
            }
        }

        function openBookingModal(date = null) {
            window.location.href = 'create-booking.php' + (date ? '?date=' + date : '');
        }

        function quickVerify(id) {
            if (!confirm('Mark booking #' + id + ' as paid?')) return;
            fetch('api.php?action=mark_paid', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.msg || 'Failed to update'));
                    }
                });
        }
    </script>
</body>

</html>