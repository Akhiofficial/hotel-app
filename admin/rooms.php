<?php
session_start();
require_once __DIR__ . '/../db.php';
if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; }

// Auto-add image and quantity columns if they don't exist
$check_image = $DB->query("SHOW COLUMNS FROM rooms LIKE 'image'");
if($check_image->num_rows == 0){
    $DB->query("ALTER TABLE rooms ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER status");
}

$check_quantity = $DB->query("SHOW COLUMNS FROM rooms LIKE 'quantity'");
if($check_quantity->num_rows == 0){
    $DB->query("ALTER TABLE rooms ADD COLUMN quantity INT DEFAULT 1 AFTER capacity");
    $DB->query("UPDATE rooms SET quantity = 1 WHERE quantity IS NULL");
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'save'){
        $id = intval($_POST['id'] ?? 0);
        $code = trim($_POST['code']);
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $cap = intval($_POST['capacity']);
        $quantity = intval($_POST['quantity'] ?? 1);
        $status = $_POST['status'] ?? 'active';
        
        // Check if columns exist
        $check_image = $DB->query("SHOW COLUMNS FROM rooms LIKE 'image'");
        $has_image = $check_image->num_rows > 0;
        
        $check_quantity = $DB->query("SHOW COLUMNS FROM rooms LIKE 'quantity'");
        $has_quantity = $check_quantity->num_rows > 0;
        
        // Add missing columns
        if(!$has_image){
            $DB->query("ALTER TABLE rooms ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER status");
            $has_image = true;
        }
        if(!$has_quantity){
            $DB->query("ALTER TABLE rooms ADD COLUMN quantity INT DEFAULT 1 AFTER capacity");
            $DB->query("UPDATE rooms SET quantity = 1 WHERE quantity IS NULL");
            $has_quantity = true;
        }
        
        // Handle image upload
        $image = null;
        if(!empty($_FILES['image']['tmp_name'])){
            $updir = __DIR__ . '/../public/uploads/';
            if(!is_dir($updir)) mkdir($updir,0755,true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fname = 'room_' . time() . '.' . $ext;
            $dest = $updir . $fname;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $dest)){
                $image = 'uploads/' . $fname;
            }
        }
        
        if($id){
            if($image && $has_image){
                $stmt = $DB->prepare("UPDATE rooms SET code=?,title=?,description=?,price=?,capacity=?,quantity=?,status=?,image=? WHERE id=?");
                $stmt->bind_param('sssdiissi',$code,$title,$desc,$price,$cap,$quantity,$status,$image,$id);
            } else {
                $stmt = $DB->prepare("UPDATE rooms SET code=?,title=?,description=?,price=?,capacity=?,quantity=?,status=? WHERE id=?");
                $stmt->bind_param('sssdiisi',$code,$title,$desc,$price,$cap,$quantity,$status,$id);
            }
            $stmt->execute();
        } else {
            if($image && $has_image){
                $stmt = $DB->prepare("INSERT INTO rooms (code,title,description,price,capacity,quantity,status,image) VALUES(?,?,?,?,?,?,?,?)");
                $stmt->bind_param('sssdiiss',$code,$title,$desc,$price,$cap,$quantity,$status,$image);
            } else {
                $stmt = $DB->prepare("INSERT INTO rooms (code,title,description,price,capacity,quantity,status) VALUES(?,?,?,?,?,?,?)");
                $stmt->bind_param('sssdiis',$code,$title,$desc,$price,$cap,$quantity,$status);
            }
            $stmt->execute();
        }
        header('Location: rooms.php?success=1');
        exit;
    } elseif($action === 'delete'){
        $id = intval($_POST['id']);
        $DB->query("DELETE FROM rooms WHERE id=$id");
        header('Location: rooms.php?success=1');
        exit;
    }
}

$rooms = $DB->query("SELECT * FROM rooms ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$editId = $_GET['edit'] ?? 0;
$editRoom = $editId ? $DB->query("SELECT * FROM rooms WHERE id=" . intval($editId))->fetch_assoc() : null;

// Get today's date for occupancy check
$today = date('Y-m-d');

// Enhance rooms with occupancy status
foreach($rooms as &$room) {
    // Check if any rooms of this type are occupied today
    $occupied_count = $DB->query("SELECT COUNT(*) as count FROM bookings b 
                                  WHERE b.room_id = {$room['id']} 
                                  AND b.status <> 'cancelled' 
                                  AND '$today' >= b.checkin 
                                  AND '$today' < b.checkout")->fetch_assoc()['count'];
    
    $total_quantity = intval($room['quantity'] ?? 1);
    $room['occupied_count'] = intval($occupied_count);
    $room['available_count'] = max(0, $total_quantity - intval($occupied_count));
    $room['is_fully_occupied'] = ($room['available_count'] <= 0 && $total_quantity > 0);
    $room['is_available'] = ($room['available_count'] > 0);
}
unset($room); // Break reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms Management - Admin Panel</title>
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
                <h1><i class="fas fa-bed"></i> Rooms Management</h1>
                <button class="btn-primary" onclick="document.getElementById('roomForm').scrollIntoView()">
                    <i class="fas fa-plus"></i> Add New Room
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">Room saved successfully!</div>
            <?php endif; ?>

            <!-- Room Form -->
            <div class="card" id="roomForm">
                <div class="card-header">
                    <h3><?=$editRoom ? 'Edit Room' : 'Add New Room'?></h3>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?=$editRoom['id'] ?? ''?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Room Code *</label>
                                <input type="text" name="code" value="<?=esc($editRoom['code'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Room Title *</label>
                                <input type="text" name="title" value="<?=esc($editRoom['title'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Price per Night (₹) *</label>
                                <input type="number" name="price" step="0.01" value="<?=esc($editRoom['price'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Capacity (Guests) *</label>
                                <input type="number" name="capacity" value="<?=esc($editRoom['capacity'] ?? '1')?>" required min="1">
                            </div>
                            <div class="form-group">
                                <label>Quantity (Number of Rooms) *</label>
                                <input type="number" name="quantity" value="<?=esc($editRoom['quantity'] ?? 1)?>" required min="1" placeholder="How many rooms of this type?">
                                <small style="color: #808080; font-size: 12px; margin-top: 5px; display: block;">
                                    Total number of rooms available in this category
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Status *</label>
                                <select name="status" required>
                                    <option value="active" <?=($editRoom['status'] ?? '') == 'active' ? 'selected' : ''?>>Active</option>
                                    <option value="inactive" <?=($editRoom['status'] ?? '') == 'inactive' ? 'selected' : ''?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Room Image</label>
                                <input type="file" name="image" accept="image/*">
                                <?php if(!empty($editRoom['image'])): ?>
                                    <small>Current: <a href="../public/<?=esc($editRoom['image'])?>" target="_blank">View Image</a></small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group full-width">
                                <label>Description</label>
                                <textarea name="description" rows="4"><?=esc($editRoom['description'] ?? '')?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Room
                            </button>
                            <?php if($editRoom): ?>
                                <a href="rooms.php" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rooms List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Rooms (<?=count($rooms)?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Code</th>
                                    <th>Title</th>
                                    <th>Price/Night</th>
                                    <th>Capacity</th>
                                    <th>Quantity</th>
                                    <th>Occupancy Status</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($rooms)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">No rooms found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($rooms as $r): ?>
                                    <tr class="<?=$r['is_fully_occupied'] ? 'room-occupied' : ($r['is_available'] ? 'room-available' : '')?>">
                                        <td><?=esc($r['id'])?></td>
                                        <td>
                                            <?php if(!empty($r['image'])): ?>
                                                <img src="../public/<?=esc($r['image'])?>" alt="Room" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                                            <?php else: ?>
                                                <div style="width:60px;height:60px;background:#ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?=esc($r['code'])?></strong></td>
                                        <td><?=esc($r['title'])?></td>
                                        <td>₹<?=number_format($r['price'], 2)?></td>
                                        <td><?=esc($r['capacity'])?> Guest<?=esc($r['capacity']) > 1 ? 's' : ''?></td>
                                        <td>
                                            <strong><?=esc($r['quantity'] ?? 1)?></strong> Room<?=esc($r['quantity'] ?? 1) > 1 ? 's' : ''?>
                                        </td>
                                        <td>
                                            <?php if($r['is_fully_occupied']): ?>
                                                <span class="occupancy-badge occupancy-booked" title="All rooms booked">
                                                    <i class="fas fa-bed"></i> Booked (<?=$r['occupied_count']?>/<?=$r['quantity'] ?? 1?>)
                                                </span>
                                            <?php elseif($r['is_available']): ?>
                                                <span class="occupancy-badge occupancy-available" title="Available rooms">
                                                    <i class="fas fa-check-circle"></i> Available (<?=$r['available_count']?>/<?=$r['quantity'] ?? 1?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="occupancy-badge occupancy-inactive" title="Inactive">
                                                    <i class="fas fa-ban"></i> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?=esc($r['status'])?>">
                                                <?=ucfirst(esc($r['status']))?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?=esc($r['id'])?>" class="btn-icon btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this room?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?=esc($r['id'])?>">
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
            </div>
        </div>
    </div>

    <script src="admin-scripts.js"></script>
</body>
</html>
