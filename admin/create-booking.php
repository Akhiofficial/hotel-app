<?php
session_start();
require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$rooms = $DB->query("SELECT * FROM rooms WHERE status='active' ORDER BY code")->fetch_all(MYSQLI_ASSOC);
$selectedDate = $_GET['date'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = intval($_POST['room_id'] ?? 0);
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $checkin = trim($_POST['checkin'] ?? '');
    $checkout = trim($_POST['checkout'] ?? '');

    // Validate formats
    if (empty($checkin) || empty($checkout)) {
        $error = "Check-in and Check-out dates are required";
    } else {
        try {
            // Validate using DateTime to ensure they are real dates
            $dtCheckin = new DateTime($checkin);
            $dtCheckout = new DateTime($checkout);

            // Re-format to strict Y-m-d for DB
            $checkin = $dtCheckin->format('Y-m-d');
            $checkout = $dtCheckout->format('Y-m-d');
        } catch (Exception $e) {
            $checkin = '';
            $checkout = '';
            $error = "Invalid date format provided";
        }
    }

    $payment = $_POST['payment_method'] ?? 'cash';

    // Handle identity card upload
    $identity_card_path = null;
    if (!empty($_FILES['identity_card']['tmp_name'])) {
        $updir = __DIR__ . '/../public/uploads/';
        if (!is_dir($updir))
            mkdir($updir, 0755, true);
        $ext = pathinfo($_FILES['identity_card']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (in_array(strtolower($ext), $allowed)) {
            $fname = 'id_' . time() . '_' . basename($_FILES['identity_card']['name']);
            $dest = $updir . $fname;
            if (move_uploaded_file($_FILES['identity_card']['tmp_name'], $dest)) {
                $identity_card_path = 'uploads/' . $fname;
            }
        }
    }

    // Validate
    if (empty($room_id) || empty($name) || empty($phone) || empty($checkin) || empty($checkout)) {
        $error = "All fields are required";
    } else {
        // Validate dates are valid using DateTime
        try {
            $checkinDate = new DateTime($checkin);
            $checkoutDate = new DateTime($checkout);

            // Ensure checkout is after checkin
            if ($checkoutDate <= $checkinDate) {
                $error = "Check-out date must be after check-in date";
            } else {
                // IMPORTANT: Re-format dates to Y-m-d before saving
                $checkin = $checkinDate->format('Y-m-d');
                $checkout = $checkoutDate->format('Y-m-d');

                // Debug: Log the dates before saving
                error_log("Saving booking - checkin: $checkin, checkout: $checkout");

                // Check availability considering quantity
                $room_info = $DB->query("SELECT quantity FROM rooms WHERE id=$room_id AND status='active'")->fetch_assoc();
                if (!$room_info) {
                    $error = "Room not found or inactive";
                } else {
                    $total_quantity = intval($room_info['quantity'] ?? 1);

                    // Count booked rooms for the date range
                    // Use robust query execution and checking
                    $countQuery = "SELECT COUNT(*) as count FROM bookings 
                                   WHERE room_id=$room_id 
                                   AND (status <> 'cancelled' OR status IS NULL)
                                   AND ((checkin <= '$checkin' AND checkout > '$checkin') 
                                   OR (checkin < '$checkout' AND checkout >= '$checkout')
                                   OR (checkin >= '$checkin' AND checkout <= '$checkout'))";

                    $countResult = $DB->query($countQuery);
                    if ($countResult) {
                        $booked_count = intval($countResult->fetch_assoc()['count']);
                    } else {
                        // If query failed, assume fully booked (safety) and log error
                        error_log("DB Query Failed in create-booking: " . $DB->error);
                        $booked_count = $total_quantity; // Force block
                        $error = "System error checking availability.";
                    }

                    // Remove debug log
                    // error_log("Overbooking Debug: ...");

                    error_log("Overbooking Debug: Room $room_id, Qty $total_quantity, Booked $booked_count, Checkin $checkin, Checkout $checkout");

                    $available_count = $total_quantity - intval($booked_count);

                    if ($available_count <= 0) {
                        $error = "No rooms available for selected dates. Only $total_quantity room(s) in this category.";
                    } else {
                        // Calculate nights and total
                        $d1 = new DateTime($checkin);
                        $d2 = new DateTime($checkout);
                        $diff = $d1->diff($d2);
                        $nights = max(1, intval($diff->days));

                        $room = $DB->query("SELECT * FROM rooms WHERE id=$room_id")->fetch_assoc();
                        $room_price = floatval($room['price']);
                        $subtotal = $room_price * $nights;
                        // GST Logic Update (Effective Sept 22, 2025)
                        $gst = floatval($config['default_gst']);
                        if ($checkin >= '2025-09-22') {
                            $gst = 5.00;
                        }
                        $gst_amount = round($subtotal * $gst / 100, 2);
                        $total = round($subtotal + $gst_amount, 2);

                        // Check if identity_card column exists
                        $check_column = $DB->query("SHOW COLUMNS FROM bookings LIKE 'identity_card'");
                        $has_identity_column = $check_column->num_rows > 0;

                        // Ensure checkout is not empty before binding
                        if (empty($checkout)) {
                            $error = "Check-out date is required";
                        } else {
                            // Default status to paid so it shows in glance view immediately
                            $status = 'paid';

                            // Insert booking with or without identity card
                            if ($has_identity_column) {
                                $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method,identity_card) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                                $stmt->bind_param('issssiidddsss', $room_id, $name, $email, $phone, $checkin, $checkout, $nights, $total, $gst, $gst_amount, $status, $payment, $identity_card_path);
                            } else {
                                $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                                $stmt->bind_param('issssiidddss', $room_id, $name, $email, $phone, $checkin, $checkout, $nights, $total, $gst, $gst_amount, $status, $payment);
                            }

                            if ($stmt->execute()) {
                                $booking_id = $DB->insert_id;
                                header('Location: bookings.php?success=1&id=' . $booking_id);
                                exit;
                            } else {
                                $error = "Failed to create booking: " . $DB->error;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Invalid date format: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Booking - Admin Panel</title>
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }
    </style>
</head>

<body>
    <?php include 'admin-header.php'; ?>
    <script src="admin-scripts.js?v=<?= time() ?>"></script>
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

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Booking Information</h3>
            </div>
            <div class="card-body">
                <form method="post" id="bookingForm" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Room *</label>
                            <select name="room_id" id="room_id" required
                                onchange="checkAvailability(); calculateTotal();">
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room):
                                    // Calculate available quantity for today
                                    $today = date('Y-m-d');
                                    $booked_today = $DB->query("SELECT COUNT(*) as count FROM bookings 
                                                                   WHERE room_id={$room['id']} 
                                                                   AND (status <> 'cancelled' OR status IS NULL)
                                                                   AND checkin <= '$today' 
                                                                   AND checkout > '$today'")->fetch_assoc()['count'];
                                    $available_qty = max(0, intval($room['quantity'] ?? 1) - intval($booked_today));
                                    ?>
                                    <option value="<?= esc($room['id']) ?>" data-price="<?= esc($room['price']) ?>"
                                        data-quantity="<?= esc($room['quantity'] ?? 1) ?>">
                                        <?= esc($room['code']) ?> - <?= esc($room['title']) ?>
                                        (₹<?= number_format($room['price'], 2) ?>/night)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Check-in Date *</label>
                            <input type="date" name="checkin" id="checkin" required value="<?= $selectedDate ?>"
                                min="<?= date('Y-m-d') ?>"
                                onchange="updateCheckoutMin(); calculateTotal(); fetchAvailableRooms();">
                        </div>

                        <div class="form-group">
                            <label>Check-out Date *</label>
                            <input type="date" name="checkout" id="checkout" required
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                onchange="calculateTotal(); fetchAvailableRooms();">
                        </div>

                        <div class="form-group">
                            <label>Customer Name *</label>
                            <input type="text" name="customer_name" required placeholder="Enter customer name">
                        </div>

                        <div class="form-group">
                            <label>Customer Email</label>
                            <input type="email" name="customer_email" placeholder="customer@example.com">
                        </div>

                        <div class="form-group">
                            <label>Customer Phone *</label>
                            <div style="display: flex; gap: 10px;">
                                <select id="country_code"
                                    style="width: 80px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="+91">+91</option>
                                    <option value="+1">+1</option>
                                    <option value="+44">+44</option>
                                    <option value="+971">+971</option>
                                </select>
                                <input type="tel" id="details_phone" placeholder="10-digit number" maxlength="10"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="flex: 1;">
                                <input type="hidden" name="customer_phone" id="full_phone">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Identity Card (Optional)</label>
                            <input type="file" name="identity_card" accept="image/*,application/pdf">
                            <small style="color: #808080; font-size: 12px; margin-top: 5px; display: block;">
                                Upload Aadhaar, PAN, Passport, or any valid ID (JPG, PNG, PDF)
                            </small>
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
                    <div id="bookingSummary" style="display:none;" class="booking-summary-card">
                        <h4><i class="fas fa-calculator"></i> Booking Summary</h4>
                        <div class="summary-row">
                            <span>Room Rate (per night):</span>
                            <span id="roomRate">₹0.00</span>
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
                            <span>GST <span id="gstLabel">(<?= $config['default_gst'] ?>%)</span>:</span>
                            <span id="gstAmount">₹0.00</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Total Amount:</span>
                            <span id="totalAmount">₹0.00</span>
                        </div>
                    </div>

                    <div id="availabilityStatus" style="margin: 20px 0;"></div>

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

    <script>
        function updateCheckoutMin() {
            const checkin = document.getElementById('checkin').value;
            if (checkin) {
                const checkout = document.getElementById('checkout');
                const minDate = new Date(checkin);
                minDate.setDate(minDate.getDate() + 1);
                checkout.setAttribute('min', minDate.toISOString().split('T')[0]);
            }
        }

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
            let gstRate = <?= $config['default_gst'] ?>;
            if (checkin >= '2025-09-22') {
                gstRate = 5.00;
            }

            document.getElementById('gstLabel').textContent = '(' + gstRate + '%)';
            const gstAmount = (subtotal * gstRate) / 100;
            const total = subtotal + gstAmount;

            document.getElementById('roomRate').textContent = '₹' + roomPrice.toFixed(2);
            document.getElementById('nightsCount').textContent = nights;
            document.getElementById('subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('gstAmount').textContent = '₹' + gstAmount.toFixed(2);
            document.getElementById('totalAmount').textContent = '₹' + total.toFixed(2);

            summary.style.display = 'block';
        }

        function checkAvailability() {
            // This function is now redundant for the dropdown, but useful for final check
            // Kept for backward compatibility if needed, or we can integrate logic into fetchAvailableRooms
            const roomId = document.getElementById('room_id').value;
            // ... existing logic can stay or be ignored ...
            // We will rely on fetchAvailableRooms to disable invalid options
        }

        function fetchAvailableRooms() {
            const checkin = document.getElementById('checkin').value;
            const checkout = document.getElementById('checkout').value;
            const roomSelect = document.getElementById('room_id');
            const statusDiv = document.getElementById('availabilityStatus');

            if (!checkin || !checkout) return;

            // Visual feedback
            const originalText = roomSelect.options[0].text;
            roomSelect.options[0].text = "Checking availability...";

            fetch(`api.php?action=availability_status&checkin=${checkin}&checkout=${checkout}`)
                .then(r => r.json())
                .then(data => {
                    roomSelect.options[0].text = "Select Room"; // Restore default text

                    if (data.rooms) {
                        // Reset availability status message
                        statusDiv.innerHTML = '';

                        data.rooms.forEach(room => {
                            // Find option by value (room_id)
                            for (let i = 0; i < roomSelect.options.length; i++) {
                                let opt = roomSelect.options[i];
                                if (opt.value == room.room_id) {
                                    // Update text and disabled state
                                    const price = parseFloat(opt.getAttribute('data-price')).toFixed(2);
                                    opt.text = `${room.code} - ${room.title} (₹${price}/night) - ${room.available} available`;

                                    if (room.available <= 0) {
                                        opt.disabled = true;
                                        opt.style.color = '#dc3545'; // Red color for unavailable

                                        // If this unavailable room was selected, deselect it
                                        if (roomSelect.value == room.room_id) {
                                            roomSelect.value = "";
                                            calculateTotal(); // Reset summary
                                            alert(`Room ${room.code} is not available for the selected dates. Please choose another.`);
                                        }
                                    } else {
                                        opt.disabled = false;
                                        opt.style.color = ''; // Reset color
                                    }
                                }
                            }
                        });
                    }
                });
        }

        // Form Validation for Admin
        document.getElementById('bookingForm').addEventListener('submit', function (e) {
            const countryCode = document.getElementById('country_code').value;
            const phonePart = document.getElementById('details_phone').value;

            if (!/^\d{10}$/.test(phonePart)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number.');
                return;
            }

            // Populate hidden input
            document.getElementById('full_phone').value = countryCode + ' ' + phonePart;
        });
    </script>

    <style>
        .booking-summary-card {
            background: #F8F9FA;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #1A4D2E;
        }

        .booking-summary-card h4 {
            color: #1A4D2E;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #E0E0E0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total-row {
            border-top: 2px solid #1A4D2E;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 700;
            font-size: 18px;
            color: #1A4D2E;
        }
    </style>
</body>

</html>