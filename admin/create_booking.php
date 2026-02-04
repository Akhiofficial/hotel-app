<?php
session_start();
require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = intval($_POST['room_id'] ?? 0);
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $checkin = $_POST['checkin'] ?? null;
    $checkout = $_POST['checkout'] ?? null;
    $payment = $_POST['payment_method'] ?? 'cash';

    if (empty($room_id) || empty($name) || empty($email) || empty($phone) || empty($checkin) || empty($checkout)) {
        $error = 'All fields are required';
    } else {
        // Validate date order
        try {
            $d1 = new DateTime($checkin);
            $d2 = new DateTime($checkout);
            if ($d2 <= $d1) {
                $error = 'Check-out date must be after check-in date';
            }
        } catch (Exception $e) {
            $error = 'Invalid dates provided';
        }

        if (!$error) {
            // Calculate nights and totals
            $diff = $d1->diff($d2);
            $nights = max(1, intval($diff->days));

            // Concurrency-safe availability check and insert
            $DB->begin_transaction();
            $roomRow = $DB->query("SELECT id, quantity, price FROM rooms WHERE id=" . $DB->real_escape_string($room_id) . " AND status='active' FOR UPDATE")->fetch_assoc();
            if (!$roomRow) {
                $DB->rollback();
                $error = 'Room not found or inactive';
            } else {
                $total_quantity = intval($roomRow['quantity'] ?? 1);
                $conflictsRes = $DB->query("SELECT id FROM bookings 
                                            WHERE room_id=" . $DB->real_escape_string($room_id) . "
                                              AND status <> 'cancelled'
                                              AND ((checkin <= '" . $DB->real_escape_string($checkin) . "' AND checkout > '" . $DB->real_escape_string($checkin) . "')
                                               OR (checkin < '" . $DB->real_escape_string($checkout) . "' AND checkout >= '" . $DB->real_escape_string($checkout) . "')
                                               OR (checkin >= '" . $DB->real_escape_string($checkin) . "' AND checkout <= '" . $DB->real_escape_string($checkout) . "'))
                                            FOR UPDATE");
                $booked_count = $conflictsRes ? $conflictsRes->num_rows : 0;
                $available_count = max(0, $total_quantity - $booked_count);

                if ($available_count <= 0) {
                    $DB->rollback();
                    $error = 'Room is not available for the selected dates';
                } else {
                    $room_price = floatval($roomRow['price']);
                    $subtotal = $room_price * $nights;
                    // GST Logic Update (Effective Sept 22, 2025)
                    $gst = floatval($config['default_gst']);
                    if ($checkin >= '2025-09-22') {
                        $gst = ($room_price > 7500) ? 18.00 : 5.00;
                    }
                    $gst_amount = round($subtotal * $gst / 100, 2);
                    $total = round($subtotal + $gst_amount, 2);

                    $status = ($payment === 'cash') ? 'paid' : 'pending';
                    $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                    if (!$stmt) {
                        $DB->rollback();
                        $error = 'Database error: ' . $DB->error;
                    } else {
                        $stmt->bind_param('issssiidddss', $room_id, $name, $email, $phone, $checkin, $checkout, $nights, $total, $gst, $gst_amount, $status, $payment);
                        if ($stmt->execute()) {
                            $booking_id = $DB->insert_id;
                            $DB->commit();
                            error_log("Admin booking created: room=" . $room_id . " checkin=" . $checkin . " checkout=" . $checkout . " id=" . $booking_id);
                            $success = true;
                            header('Location: bookings.php?success=1&capacity_updated=1&id=' . $booking_id);
                            exit;
                        } else {
                            $DB->rollback();
                            $error = 'Database error: ' . $DB->error;
                        }
                    }
                }
            }
        }
    }
}

$rooms = $DB->query("SELECT * FROM rooms WHERE status='active' ORDER BY code")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Admin Panel</title>
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
                <h1><i class="fas fa-plus-circle"></i> Create New Booking</h1>
                <a href="bookings.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Booking Information</h3>
                </div>
                <div class="card-body">
                    <form method="post" id="bookingForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Room *</label>
                                <select name="room_id" id="room_id" required onchange="calculateTotal()">
                                    <option value="">Select Room</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?= esc($room['id']) ?>" data-price="<?= esc($room['price']) ?>">
                                            <?= esc($room['code']) ?> - <?= esc($room['title']) ?>
                                            (₹<?= number_format($room['price'], 2) ?>/night)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Check-in Date *</label>
                                <input type="date" name="checkin" id="checkin" required
                                    onchange="checkAvailability(); calculateTotal();" min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label>Check-out Date *</label>
                                <input type="date" name="checkout" id="checkout" required
                                    onchange="checkAvailability(); calculateTotal();" min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group full-width">
                                <div id="availabilityStatus"></div>
                            </div>

                            <div class="form-group">
                                <label>Customer Name *</label>
                                <input type="text" name="customer_name" required placeholder="Enter customer name">
                            </div>

                            <div class="form-group">
                                <label>Customer Email *</label>
                                <input type="email" name="customer_email" required placeholder="customer@example.com">
                            </div>

                            <div class="form-group">
                                <label>Customer Phone *</label>
                                <input type="tel" name="customer_phone" required placeholder="+91 12345 67890">
                            </div>

                            <div class="form-group">
                                <label>Payment Method *</label>
                                <select name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="online">Online Payment</option>
                                </select>
                            </div>
                        </div>

                        <!-- Booking Summary -->
                        <div class="booking-summary-card" id="bookingSummary" style="display:none;">
                            <h4><i class="fas fa-calculator"></i> Booking Summary</h4>
                            <div class="summary-row">
                                <span>Room Price (per night):</span>
                                <span id="roomPrice">₹0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Number of Nights:</span>
                                <span id="nightsCount">0</span>
                            </div>
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">₹0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>GST <span id="gstLabel">(<?=$config['default_gst']?>%)</span>:</span>
                                <span id="gstAmount">₹0.00</span>
                            </div>
                            <div class="summary-row total-row">
                                <span><strong>Total Amount:</strong></span>
                                <span id="totalAmount"><strong>₹0.00</strong></span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Create Booking
                            </button>
                            <a href="bookings.php" class="btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="admin-scripts.js"></script>
    <script>
        function calculateTotal() {
            const roomSelect = document.getElementById('room_id');
            const checkin = document.getElementById('checkin').value;
            const checkout = document.getElementById('checkout').value;
            const summary = document.getElementById('bookingSummary');

            if (!roomSelect.value || !checkin || !checkout) {
                summary.style.display = 'none';
                return;
            }

            const roomPrice = parseFloat(roomSelect.options[roomSelect.selectedIndex].dataset.price) || 0;
            const checkinDate = new Date(checkin);
            const checkoutDate = new Date(checkout);
            const nights = Math.ceil((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24));

            if (nights <= 0) {
                summary.style.display = 'none';
                return;
            }

            const subtotal = roomPrice * nights;
            let gstRate = <?=$config['default_gst']?>;
            // Convert checkin date string to a comparable format (YYYY-MM-DD)
            const checkinDateStr = checkin; // checkin is already YYYY-MM-DD
            if (checkinDateStr >= '2025-09-22') {
                gstRate = (roomPrice > 7500) ? 18.00 : 5.00;
            }

            document.getElementById('gstLabel').textContent = '(' + gstRate + '%)';
            const gstAmount = (subtotal * gstRate) / 100;
            const total = subtotal + gstAmount;

            document.getElementById('roomPrice').textContent = '₹' + roomPrice.toFixed(2);
            document.getElementById('nightsCount').textContent = nights;
            document.getElementById('subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('gstAmount').textContent = '₹' + gstAmount.toFixed(2);
            document.getElementById('totalAmount').innerHTML = '<strong>₹' + total.toFixed(2) + '</strong>';

            summary.style.display = 'block';
        }

        function checkAvailability() {
            const roomId = document.getElementById('room_id').value;
            const checkin = document.getElementById('checkin').value;
            const checkout = document.getElementById('checkout').value;
            const statusDiv = document.getElementById('availabilityStatus');

            if (!roomId || !checkin || !checkout) {
                statusDiv.innerHTML = '';
                return;
            }

            if (new Date(checkout) <= new Date(checkin)) {
                statusDiv.innerHTML = '<div class="alert alert-error">Check-out date must be after check-in date</div>';
                return;
            }

            fetch(`api.php?action=check_availability&room_id=${roomId}&checkin=${checkin}&checkout=${checkout}`)
                .then(r => r.json())
                .then(data => {
                    if (data.available) {
                        statusDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Room is available for selected dates</div>';
                    } else {
                        statusDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> Room is not available for selected dates</div>';
                    }
                });
        }

        // Set minimum dates
        document.getElementById('checkin').addEventListener('change', function () {
            if (this.value) {
                const nextDay = new Date(this.value);
                nextDay.setDate(nextDay.getDate() + 1);
                document.getElementById('checkout').setAttribute('min', nextDay.toISOString().split('T')[0]);
            }
        });
    </script>
</body>

</html>