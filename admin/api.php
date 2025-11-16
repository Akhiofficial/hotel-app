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
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Function to send error response
function sendError($message, $statusCode = 400) {
    sendJsonResponse(['success' => false, 'error' => $message], $statusCode);
}

if($action === 'create_booking'){
    try {
        // public booking form handled here (multipart if bank proof)
        $room_id = intval($_POST['room_id'] ?? 0);
        $name = trim($_POST['customer_name'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $checkin = trim($_POST['checkin'] ?? '');
        $checkout = trim($_POST['checkout'] ?? '');
        
        // Debug: Log what we received
        error_log("Received checkin: " . $checkin);
        error_log("Received checkout: " . $checkout);
        
        // HTML date inputs already send dates in Y-m-d format
        // Just validate the format, don't convert
        if (!empty($checkin) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin)) {
            error_log("Invalid checkin format: " . $checkin);
            $checkin = '';
        }
        if (!empty($checkout) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) {
            error_log("Invalid checkout format: " . $checkout);
            $checkout = '';
        }
        
        // Ensure dates are not empty after validation
        if (empty($checkin) || empty($checkout)) {
            if($isAjax){
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
                if($isAjax){
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
            if($isAjax){
                sendError('Invalid date format: ' . $e->getMessage());
            } else {
                header('Location: /public/index.php?error=' . urlencode('Invalid date format'));
                exit;
            }
        }
        
        // Validate email
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            if($isAjax){
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
        
        if($nights <= 0){
            if($isAjax){
                sendError('Check-out date must be after check-in date');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Check-out date must be after check-in date'));
                exit;
            }
        }

        // fetch room price
        $room = $DB->query("SELECT * FROM rooms WHERE id=" . $DB->real_escape_string($room_id))->fetch_assoc();
        if(!$room){ 
            if($isAjax){
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

        // bank proof upload if given
        $bank_proof_path = null;
        if($payment === 'bank_transfer' && !empty($_FILES['bank_proof']['tmp_name'])){
            $updir = __DIR__ . '/../' . trim($config['upload_dir'],'/');
            if(!is_dir($updir)) mkdir($updir,0755,true);
            $fname = time() . '_' . basename($_FILES['bank_proof']['name']);
            $dest = $updir . '/' . $fname;
            if(move_uploaded_file($_FILES['bank_proof']['tmp_name'],$dest)){
                $bank_proof_path = 'uploads/' . $fname;
            }
        }

        // Identity card upload (optional) - Check if column exists
        $identity_card_path = null;
        $hasIdentityColumn = false;
        $result = $DB->query("SHOW COLUMNS FROM bookings LIKE 'identity_card'");
        if($result && $result->num_rows > 0){
            $hasIdentityColumn = true;
            if(!empty($_FILES['identity_card']['tmp_name'])){
                $updir = __DIR__ . '/../' . trim($config['upload_dir'],'/');
                if(!is_dir($updir)) mkdir($updir,0755,true);
                $ext = pathinfo($_FILES['identity_card']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if(in_array(strtolower($ext), $allowed)){
                    $fname = 'id_' . time() . '_' . basename($_FILES['identity_card']['name']);
                    $dest = $updir . '/' . $fname;
                    if(move_uploaded_file($_FILES['identity_card']['tmp_name'],$dest)){
                        $identity_card_path = 'uploads/' . $fname;
                    }
                }
            }
        }

        // Ensure checkout is not empty before saving
        if (empty($checkout)) {
            error_log("ERROR: Checkout is empty before saving!");
            if($isAjax){
                sendError('Check-out date is required');
            } else {
                header('Location: /public/index.php?error=' . urlencode('Check-out date is required'));
                exit;
            }
        }
        
        // Prepare SQL based on whether identity_card column exists
        if($hasIdentityColumn){
            $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method,bank_proof,identity_card) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $status = ($payment === 'cash') ? 'paid' : 'pending';
            $stmt->bind_param('issssiidddssss',$room_id,$name,$email,$phone,$checkin,$checkout,$nights,$total,$gst,$gst_amount,$status,$payment,$bank_proof_path,$identity_card_path);
        } else {
            $stmt = $DB->prepare("INSERT INTO bookings (room_id,customer_name,customer_email,customer_phone,checkin,checkout,nights,total,gst_rate,gst_amount,status,payment_method,bank_proof) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $status = ($payment === 'cash') ? 'paid' : 'pending';
            $stmt->bind_param('issssiidddsss',$room_id,$name,$email,$phone,$checkin,$checkout,$nights,$total,$gst,$gst_amount,$status,$payment,$bank_proof_path);
        }
        
        // Debug: Log before execute
        error_log("About to save booking with checkout: " . $checkout);
        
        if($stmt->execute()){
            $booking_id = $DB->insert_id;
            error_log("Booking saved successfully with ID: " . $booking_id);
            if($isAjax){
                sendJsonResponse([
                    'success'=>true, 
                    'message'=>'Booking confirmed successfully!',
                    'booking_id'=>$booking_id
                ]);
            } else {
                header('Location: /public/index.php?success=1&booking_id=' . $booking_id);
                exit;
            }
        } else {
            $error_msg = 'Database error: ' . $DB->error;
            if($isAjax){
                sendError($error_msg);
            } else {
                header('Location: /public/index.php?error=' . urlencode($error_msg));
                exit;
            }
        }
    } catch(Exception $e) {
        if($isAjax){
            sendError('An error occurred: ' . $e->getMessage());
        } else {
            header('Location: /public/index.php?error=' . urlencode('An error occurred. Please try again.'));
            exit;
        }
    }
}

// List calendar events for FullCalendar
if($action === 'list_events'){
    header('Content-Type: application/json');
    $room_id = intval($_GET['room_id'] ?? 0);
    $whereClause = "WHERE b.status <> 'cancelled'";
    if($room_id > 0){
        $whereClause .= " AND b.room_id = $room_id";
    }
    
    $events = [];
    $res = $DB->query("SELECT b.id,b.checkin,b.checkout,r.title,r.code FROM bookings b 
                       LEFT JOIN rooms r ON r.id=b.room_id 
                       $whereClause");
    while($row = $res->fetch_assoc()){
        $events[] = [
            'id' => $row['id'],
            'title' => ($row['code'] ?? '') . ' - ' . ($row['title'] ?: "Booking #".$row['id']),
            'start' => $row['checkin'],
            'end' => (new DateTime($row['checkout']))->modify('+1 day')->format('Y-m-d'),
            'backgroundColor' => '#50B848',
            'borderColor' => '#1A4D2E'
        ];
    }
    echo json_encode($events); exit;
}

// Check room availability considering quantity
if($action === 'check_availability'){
    header('Content-Type: application/json');
    $room_id = intval($_GET['room_id'] ?? 0);
    $checkin = $_GET['checkin'] ?? '';
    $checkout = $_GET['checkout'] ?? '';
    
    if(!$room_id || !$checkin || !$checkout){
        sendJsonResponse(['available' => false, 'msg' => 'Missing parameters']);
    }
    
    // Get room quantity
    $room = $DB->query("SELECT quantity FROM rooms WHERE id=$room_id AND status='active'")->fetch_assoc();
    if(!$room){
        sendJsonResponse(['available' => false, 'msg' => 'Room not found']);
    }
    
    $total_quantity = intval($room['quantity'] ?? 1);
    
    // Count how many rooms are booked for the given dates
    $booked_count = $DB->query("SELECT COUNT(*) as count FROM bookings 
                                WHERE room_id=$room_id 
                                AND status <> 'cancelled' 
                                AND ((checkin <= '$checkin' AND checkout > '$checkin') 
                                OR (checkin < '$checkout' AND checkout >= '$checkout')
                                OR (checkin >= '$checkin' AND checkout <= '$checkout'))")->fetch_assoc()['count'];
    
    $available = ($total_quantity - intval($booked_count)) > 0;
    $available_count = max(0, $total_quantity - intval($booked_count));
    
    sendJsonResponse([
        'available' => $available,
        'available_count' => $available_count,
        'total_quantity' => $total_quantity,
        'booked_count' => intval($booked_count)
    ]);
}

// Stats endpoint - simple daily bookings count for last 7 days
if($action === 'stats'){
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'daily';
    $labels = [];
    $values = [];
    
    if($period === 'daily'){
        for($i=6;$i>=0;$i--){
            $d = new DateTime("-$i days");
            $labels[] = $d->format('M d');
            $day = $d->format('Y-m-d');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) = '".$DB->real_escape_string($day)."'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif($period === 'weekly'){
        for($i=3;$i>=0;$i--){
            $d = new DateTime("-$i weeks");
            $d->modify('monday this week');
            $labels[] = 'Week ' . $d->format('M d');
            $weekStart = $d->format('Y-m-d');
            $weekEnd = (clone $d)->modify('+6 days')->format('Y-m-d');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$weekStart' AND '$weekEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif($period === 'monthly'){
        for($i=11;$i>=0;$i--){
            $d = new DateTime("-$i months");
            $labels[] = $d->format('M Y');
            $monthStart = $d->format('Y-m-01');
            $monthEnd = $d->format('Y-m-t');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    } elseif($period === 'yearly'){
        for($i=4;$i>=0;$i--){
            $d = new DateTime("-$i years");
            $labels[] = $d->format('Y');
            $yearStart = $d->format('Y-01-01');
            $yearEnd = $d->format('Y-12-31');
            $r = $DB->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at) BETWEEN '$yearStart' AND '$yearEnd'")->fetch_assoc();
            $values[] = intval($r['c']);
        }
    }
    
    echo json_encode(['labels'=>$labels,'values'=>$values]); exit;
}

// Revenue statistics
if($action === 'revenue_stats'){
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'daily';
    $labels = [];
    $values = [];
    
    if($period === 'daily'){
        for($i=6;$i>=0;$i--){
            $d = new DateTime("-$i days");
            $labels[] = $d->format('M d');
            $day = $d->format('Y-m-d');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) = '".$DB->real_escape_string($day)."' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    } elseif($period === 'weekly'){
        for($i=3;$i>=0;$i--){
            $d = new DateTime("-$i weeks");
            $d->modify('monday this week');
            $labels[] = 'Week ' . $d->format('M d');
            $weekStart = $d->format('Y-m-d');
            $weekEnd = (clone $d)->modify('+6 days')->format('Y-m-d');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) BETWEEN '$weekStart' AND '$weekEnd' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    } elseif($period === 'monthly'){
        for($i=11;$i>=0;$i--){
            $d = new DateTime("-$i months");
            $labels[] = $d->format('M Y');
            $monthStart = $d->format('Y-m-01');
            $monthEnd = $d->format('Y-m-t');
            $r = $DB->query("SELECT SUM(total) as s FROM bookings WHERE DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd' AND status IN ('paid','confirmed')")->fetch_assoc();
            $values[] = floatval($r['s'] ?? 0);
        }
    }
    
    echo json_encode(['labels'=>$labels,'values'=>$values]); exit;
}

// Admin AJAX: mark_paid
if($action === 'mark_paid'){
    header('Content-Type: application/json');
    if(empty($_SESSION['admin'])) { 
        echo json_encode(['success'=>false,'msg'=>'not authorized']); 
        exit; 
    }
    $id = intval($_POST['id'] ?? 0);
    $DB->query("UPDATE bookings SET status='paid' WHERE id=$id");
    echo json_encode(['success'=>true]); exit;
}

// Update booking status
if($action === 'update_status'){
    header('Content-Type: application/json');
    if(empty($_SESSION['admin'])) { 
        echo json_encode(['success'=>false,'msg'=>'not authorized']); 
        exit; 
    }
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if(in_array($status, ['pending','paid','confirmed','cancelled'])){
        $DB->query("UPDATE bookings SET status='".$DB->real_escape_string($status)."' WHERE id=$id");
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'msg'=>'Invalid status']);
    }
    exit;
}

// Default response
header('Content-Type: application/json');
echo json_encode(['error'=>'unknown_action']);
