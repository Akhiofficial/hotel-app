<?php
// admin/api.php - handles booking creation (used by public form), events, stats, and admin AJAX actions
session_start();

// Suppress error output for AJAX requests
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../db.php';
$config = require __DIR__ . '/../config.php';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Only set JSON header if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Function to send error response
function sendError($message, $statusCode = 400)
{
    sendJsonResponse(['success' => false, 'error' => $message], $statusCode);
}

if ($action === 'create_booking') {
    try {
        // public booking form handled here (multipart if bank proof)
        $room_id = intval($_POST['room_id'] ?? 0);
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $checkin = trim($_POST['checkin'] ?? '');
        $checkout = trim($_POST['checkout'] ?? '');
        $payment = $_POST['payment_method'] ?? 'cash';

        // Debug: Log what we received
        error_log("Received checkin: " . $checkin);
        error_log("Received checkout: " . $checkout);

        // Validate and Format Dates
        try {
            if (empty($checkin) || empty($checkout)) {
                throw new Exception("Dates required");
            }
            $dtCheckin = new DateTime($checkin);
            $dtCheckout = new DateTime($checkout);
            $checkin = $dtCheckin->format('Y-m-d');
            $checkout = $dtCheckout->format('Y-m-d');
        } catch (Exception $e) {
            error_log("API: Invalid date format: $checkin - $checkout");
            if ($isAjax) {
                sendError('Invalid date format. Please select valid dates.');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Invalid date format'));
                exit;
            }
        }

        // Ensure dates are not empty after validation
        if (empty($checkin) || empty($checkout)) {
            if ($isAjax) {
                sendError('Invalid date format. Please select valid check-in and check-out dates.');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Invalid date format'));
                exit;
            }
        }

        // Validate dates are valid using DateTime
        try {
            $checkinDate = new DateTime($checkin);
            $checkoutDate = new DateTime($checkout);

            // Ensure checkout is after checkin
            if ($checkoutDate <= $checkinDate) {
                if ($isAjax) {
                    sendError('Check-out date must be after check-in date');
                } else {
                    header('Location: /public/index.php?error=' . urlencode('Check-out date must be after check-in date'));
                    exit;
                }
            }

            // IMPORTANT: Re-format dates to Y-m-d before saving
            $checkin = $checkinDate->format('Y-m-d');
            $checkout = $checkoutDate->format('Y-m-d');

            error_log("API: Saving booking - checkin: $checkin, checkout: $checkout");

        } catch (Exception $e) {
            error_log("API: Date validation error: " . $e->getMessage());
            if ($isAjax) {
                sendError('Invalid date format: ' . $e->getMessage());
            } else {
                header('Location: /public/index.php?error=' . urlencode('Invalid date format'));
                exit;
            }
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isAjax) {
                sendError('Invalid email address');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Invalid email address'));
                exit;
            }
        }

        // compute nights
        $d1 = new DateTime($checkin);
        $d2 = new DateTime($checkout);
        $diff = $d1->diff($d2);
        $nights = max(1, intval($diff->days));

        if ($nights <= 0) {
            if ($isAjax) {
                sendError('Check-out date must be after check-in date');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Check-out date must be after check-in date'));
                exit;
            }
        }

        // fetch room price
        $room = $DB->query("SELECT * FROM rooms WHERE id=" . $DB->real_escape_string($room_id))->fetch_assoc();
        if (!$room) {
            if ($isAjax) {
                sendError('Room not found');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Room not found'));
                exit;
            }
        }

        $room_price = floatval($room['price']);
        $subtotal = $room_price * $nights;
        $gst = floatval($config['default_gst']);
        $gst_amount = round($subtotal * $gst / 100, 2);
        $total = round($subtotal + $gst_amount, 2);

        // Concurrency-safe availability check using transaction
        $DB->begin_transaction();
        $roomRow = $DB->query("SELECT id, quantity FROM rooms WHERE id=" . $DB->real_escape_string($room_id) . " AND status='active' FOR UPDATE")->fetch_assoc();
        if (!$roomRow) {
            $DB->rollback();
            if ($isAjax) {
                sendError('Room not available');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Room not available'));
                exit;
            }
        }
        $total_quantity = intval($roomRow['quantity'] ?? 1);

        $conflictsRes = $DB->query("SELECT id, checkin, checkout, status FROM bookings 
                                    WHERE room_id=" . $DB->real_escape_string($room_id) . "
                                    AND (status <> 'cancelled' OR status IS NULL)
                                    AND ((checkin <= '$checkin' AND checkout > '$checkin')
                                     OR (checkin < '$checkout' AND checkout >= '$checkout')
                                     OR (checkin >= '$checkin' AND checkout <= '$checkout'))
                                    FOR UPDATE");
        $conflicts = [];
        while ($row = $conflictsRes->fetch_assoc()) {
            $conflicts[] = $row;
        }
        $booked_count = count($conflicts);
        $available_count = max(0, $total_quantity - $booked_count);

        if ($available_count <= 0) {
            $DB->rollback();
            $conflictMsg = 'Room is currently occupied for the selected dates.';
            $details = array_map(function ($c) {
                return '#' . $c['id'] . ' [' . $c['status'] . '] ' . $c['checkin'] . ' → ' . $c['checkout'];
            }, array_slice($conflicts, 0, 3));
            $payload = ['success' => false, 'error' => $conflictMsg, 'status' => 'occupied', 'conflicts' => $details, 'http_status' => 409];
            if ($isAjax) {
                http_response_code(409);
                sendJsonResponse($payload, 409);
            } else {
                header('Location: /public/index.php?error=' . urlencode($conflictMsg . (count($details) ? (' Conflicts: ' . implode('; ', $details)) : '')));
                exit;
            }
        }

        // bank proof upload if given
        $bank_proof_path = null;
        if ($payment === 'bank_transfer' && !empty($_FILES['bank_proof']['tmp_name'])) {
            $updir = __DIR__ . '/../' . trim($config['upload_dir'], '/');
            if (!is_dir($updir))
                mkdir($updir, 0755, true);
            $fname = time() . '_' . basename($_FILES['bank_proof']['name']);
            $dest = $updir . '/' . $fname;
            if (move_uploaded_file($_FILES['bank_proof']['tmp_name'], $dest)) {
                $bank_proof_path = 'uploads/' . $fname;
            }
        }

        // Identity card upload (optional) - Check if column exists
        $identity_card_path = null;
        $hasIdentityColumn = false;
        $result = $DB->query("SHOW COLUMNS FROM bookings LIKE 'identity_card'");
        if ($result && $result->num_rows > 0) {
            $hasIdentityColumn = true;
            if (!empty($_FILES['identity_card']['tmp_name'])) {
                $updir = __DIR__ . '/../' . trim($config['upload_dir'], '/');
                if (!is_dir($updir))
                    mkdir($updir, 0755, true);
                $ext = pathinfo($_FILES['identity_card']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (in_array(strtolower($ext), $allowed)) {
                    $fname = 'id_' . time() . '_' . basename($_FILES['identity_card']['name']);
                    $dest = $updir . '/' . $fname;
                    if (move_uploaded_file($_FILES['identity_card']['tmp_name'], $dest)) {
                        $identity_card_path = 'uploads/' . $fname;
                    }
                }
            }
        }

        // Ensure checkout is not empty before saving
        if (empty($checkout)) {
            error_log("ERROR: Checkout is empty before saving!");
            if ($isAjax) {
                sendError('Check-out date is required');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Check-out date is required'));
                exit;
            }
        }

        // Prepare normal INSERT under the lock
        $status = ($payment === 'cash') ? 'paid' : 'pending';
        if ($hasIdentityColumn) {
            $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method,bank_proof,identity_card) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$stmt) {
                error_log('Prepare failed: ' . $DB->error);
                $DB->rollback();
                sendError('Server error', 500);
            }
            $stmt->bind_param('isssssidddssss', $room_id, $name, $email, $phone, $checkin, $checkout, $nights, $total, $gst, $gst_amount, $status, $payment, $bank_proof_path, $identity_card_path);
        } else {
            $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method,bank_proof) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$stmt) {
                error_log('Prepare failed: ' . $DB->error);
                $DB->rollback();
                sendError('Server error', 500);
            }
            $stmt->bind_param('isssssidddsss', $room_id, $name, $email, $phone, $checkin, $checkout, $nights, $total, $gst, $gst_amount, $status, $payment, $bank_proof_path);
        }

        // Debug: Log before execute
        error_log("About to save booking with checkout: " . $checkout);

        if ($stmt->execute()) {
            $booking_id = $DB->insert_id;
            $DB->commit();
            error_log("Booking saved successfully with ID: " . $booking_id);
            if ($isAjax) {
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Booking confirmed successfully!',
                    'booking_id' => $booking_id
                ]);
            } else {
                header('Location: /public/index.php?success=1&booking_id=' . $booking_id);
                exit;
            }
        } else {
            $DB->rollback();
            $error_msg = 'Database error: ' . $DB->error;
            if ($isAjax) {
                sendError($error_msg);
            } else {
                header('Location: /public/index.php?error=' . urlencode($error_msg));
                exit;
            }
        }
    } catch (Exception $e) {
        if ($isAjax) {
            sendError('An error occurred: ' . $e->getMessage());
        } else {
            header('Location: /public/index.php?error=' . urlencode('An error occurred. Please try again.'));
            exit;
        }
    }
}

// List calendar events for FullCalendar
if ($action === 'list_events') {
    header('Content-Type: application/json');
    $room_id = intval($_GET['room_id'] ?? 0);
    $whereClause = "WHERE b.status <> 'cancelled'";
    if ($room_id > 0) {
        $whereClause .= " AND b.room_id = $room_id";
    }

    $events = [];
    $today = date('Y-m-d');

    $res = $DB->query("SELECT b.id,b.checkin,b.checkout,b.status,r.title,r.code,r.quantity,
                       (SELECT COUNT(*) FROM bookings b2 
                        WHERE b2.room_id = b.room_id 
                        AND b2.status <> 'cancelled' 
                        AND b2.checkin <= '$today' 
                        AND b2.checkout > '$today'
                        AND b2.id = b.id) as is_currently_occupied
                       FROM bookings b 
                       LEFT JOIN rooms r ON r.id=b.room_id 
                       $whereClause
                       ORDER BY b.checkin");

    while ($row = $res->fetch_assoc()) {
        // Determine if this booking is currently active (today is between checkin and checkout)
        $checkinDate = new DateTime($row['checkin']);
        $checkoutDate = new DateTime($row['checkout']);
        $todayDate = new DateTime($today);

        $isActive = ($todayDate >= $checkinDate && $todayDate < $checkoutDate);

        // Red for occupied/active bookings, green for future bookings
        $backgroundColor = $isActive ? '#E74C3C' : '#50B848';
        $borderColor = $isActive ? '#C0392B' : '#1A4D2E';

        $events[] = [
            'id' => $row['id'],
            'title' => ($row['code'] ?? '') . ' - ' . ($row['title'] ?: "Booking #" . $row['id']),
            'start' => $row['checkin'],
            'end' => (new DateTime($row['checkout']))->modify('+1 day')->format('Y-m-d'),
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $row['status'],
                'isOccupied' => $isActive
            ]
        ];
    }
    echo json_encode($events);
    exit;
}

// Centralized availability: returns available counts per room for a date range
if ($action === 'availability_status') {
    header('Content-Type: application/json');
    $checkin = $_GET['checkin'] ?? date('Y-m-d');
    $checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+1 day'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
        sendJsonResponse(['error' => 'invalid_date'], 400);
    }
    $rooms = $DB->query("SELECT id, code, title, quantity FROM rooms WHERE status='active' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    $result = [];
    foreach ($rooms as $room) {
        $rid = intval($room['id']);
        $total_quantity = intval($room['quantity'] ?? 1);
        $conflicts = $DB->query("SELECT id FROM bookings 
                                  WHERE room_id=$rid 
                                  AND status <> 'cancelled' 
                                  AND ((checkin <= '$checkin' AND checkout > '$checkin') 
                                   OR (checkin < '$checkout' AND checkout >= '$checkout')
                                   OR (checkin >= '$checkin' AND checkout <= '$checkout'))")->num_rows;
        $available_count = max(0, $total_quantity - intval($conflicts));
        $result[] = [
            'room_id' => $rid,
            'code' => $room['code'],
            'title' => $room['title'],
            'available' => $available_count,
            'total' => $total_quantity
        ];
    }
    echo json_encode(['checkin' => $checkin, 'checkout' => $checkout, 'generated_at' => date('c'), 'rooms' => $result]);
    exit;
}

// Check room availability considering quantity
if ($action === 'check_availability') {
    header('Content-Type: application/json');
    $room_id = intval($_GET['room_id'] ?? 0);
    $checkin = $_GET['checkin'] ?? '';
    $checkout = $_GET['checkout'] ?? '';

    if (!$room_id || !$checkin || !$checkout) {
        sendJsonResponse(['available' => false, 'msg' => 'Missing parameters']);
    }

    // Get room quantity
    $room = $DB->query("SELECT quantity FROM rooms WHERE id=$room_id AND status='active'")->fetch_assoc();
    if (!$room) {
        sendJsonResponse(['available' => false, 'msg' => 'Room not found']);
    }

    $total_quantity = intval($room['quantity'] ?? 1);

    // Collect conflicting bookings
    $conflicts = $DB->query("SELECT id, checkin, checkout, status FROM bookings 
                                WHERE room_id=$room_id 
                                AND status <> 'cancelled' 
                                AND ((checkin <= '$checkin' AND checkout > '$checkin') 
                                OR (checkin < '$checkout' AND checkout >= '$checkout')
                                OR (checkin >= '$checkin' AND checkout <= '$checkout'))")->fetch_all(MYSQLI_ASSOC);
    $booked_count = count($conflicts);

    $available = ($total_quantity - intval($booked_count)) > 0;
    $available_count = max(0, $total_quantity - intval($booked_count));

    sendJsonResponse([
        'available' => $available,
        'available_count' => $available_count,
        'total_quantity' => $total_quantity,
        'booked_count' => intval($booked_count),
        'conflicts' => array_map(function ($c) {
            return ['id' => $c['id'], 'status' => $c['status'], 'checkin' => $c['checkin'], 'checkout' => $c['checkout']];
        }, $conflicts)
    ]);
}

// Stats endpoint - simple daily bookings count for last 7 days
if ($action === 'stats') {
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'daily';
    $labels = [];
    $values = [];

    if ($period === 'daily') {
        for ($i = 6; $i >= 0; $i--) {
            $d = new DateTime("-$i days");
            $labels[] = $d->format('M d');
            $day = $d->format('Y-m-d');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) = '" . $DB->real_escape_string($day) . "'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif ($period === 'weekly') {
        for ($i = 3; $i >= 0; $i--) {
            $d = new DateTime("-$i weeks");
            $d->modify('monday this week');
            $labels[] = 'Week ' . $d->format('M d');
            $weekStart = $d->format('Y-m-d');
            $weekEnd = (clone $d)->modify('+6 days')->format('Y-m-d');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$weekStart' AND '$weekEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif ($period === 'monthly') {
        for ($i = 11; $i >= 0; $i--) {
            $d = new DateTime("-$i months");
            $labels[] = $d->format('M Y');
            $monthStart = $d->format('Y-m-01');
            $monthEnd = $d->format('Y-m-t');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif ($period === 'yearly') {
        for ($i = 4; $i >= 0; $i--) {
            $d = new DateTime("-$i years");
            $labels[] = $d->format('Y');
            $yearStart = $d->format('Y-01-01');
            $yearEnd = $d->format('Y-12-31');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$yearStart' AND '$yearEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    }

    echo json_encode(['labels' => $labels, 'values' => $values]);
    exit;
}

// Revenue statistics
if ($action === 'revenue_stats') {
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'daily';
    $labels = [];
    $values = [];

    if ($period === 'daily') {
        for ($i = 6; $i >= 0; $i--) {
            $d = new DateTime("-$i days");
            $labels[] = $d->format('M d');
            $day = $d->format('Y-m-d');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) = '" . $DB->real_escape_string($day) . "' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    } elseif ($period === 'weekly') {
        for ($i = 3; $i >= 0; $i--) {
            $d = new DateTime("-$i weeks");
            $d->modify('monday this week');
            $labels[] = 'Week ' . $d->format('M d');
            $weekStart = $d->format('Y-m-d');
            $weekEnd = (clone $d)->modify('+6 days')->format('Y-m-d');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) BETWEEN '$weekStart' AND '$weekEnd' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    } elseif ($period === 'monthly') {
        for ($i = 11; $i >= 0; $i--) {
            $d = new DateTime("-$i months");
            $labels[] = $d->format('M Y');
            $monthStart = $d->format('Y-m-01');
            $monthEnd = $d->format('Y-m-t');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    }

    echo json_encode(['labels' => $labels, 'values' => $values]);
    exit;
}

// Admin AJAX: mark_paid
if ($action === 'mark_paid') {
    header('Content-Type: application/json');
    if (empty($_SESSION['admin'])) {
        echo json_encode(['success' => false, 'msg' => 'not authorized']);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    $DB->query("UPDATE bookings SET status='paid' WHERE id=$id");
    echo json_encode(['success' => true]);
    exit;
}

// Delete booking
if ($action === 'delete_booking') {
    header('Content-Type: application/json');
    if (empty($_SESSION['admin'])) {
        sendJsonResponse(['success' => false, 'msg' => 'Not authorized']);
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        sendJsonResponse(['success' => false, 'msg' => 'Invalid booking ID']);
    }

    // Delete the booking
    $result = $DB->query("DELETE FROM bookings WHERE id=$id");

    if ($result) {
        error_log("Booking deleted id=" . $id);
        sendJsonResponse(['success' => true, 'msg' => 'Booking deleted successfully']);
    } else {
        sendJsonResponse(['success' => false, 'msg' => 'Failed to delete booking: ' . $DB->error]);
    }
}

// Update booking status
if ($action === 'update_status') {
    header('Content-Type: application/json');
    if (empty($_SESSION['admin'])) {
        echo json_encode(['success' => false, 'msg' => 'not authorized']);
        exit;
    }
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['pending', 'paid', 'confirmed', 'cancelled', 'archived'])) {
        $DB->query("UPDATE bookings SET status='" . $DB->real_escape_string($status) . "' WHERE id=$id");
        error_log("Booking status updated id=" . $id . " status=" . $status);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Invalid status']);
    }
    exit;
}



// Default response
header('Content-Type: application/json');
echo json_encode(['error' => 'unknown_action']);
