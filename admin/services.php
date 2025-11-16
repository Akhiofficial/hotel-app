<?php
session_start();
require_once __DIR__ . '/../db.php';
if(empty($_SESSION['admin'])){ header('Location: login.php'); exit; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'save'){
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $qty = intval($_POST['qty']);
        
        if($id){
            $stmt = $DB->prepare("UPDATE services SET name=?,price=?,qty=? WHERE id=?");
            $stmt->bind_param('sdii',$name,$price,$qty,$id);
        } else {
            $stmt = $DB->prepare("INSERT INTO services (name,price,qty) VALUES(?,?,?)");
            $stmt->bind_param('sdi',$name,$price,$qty);
        }
        $stmt->execute();
        header('Location: services.php?success=1');
        exit;
    } elseif($action === 'delete'){
        $id = intval($_POST['id']);
        $DB->query("DELETE FROM services WHERE id=$id");
        header('Location: services.php?success=1');
        exit;
    }
}

$services = $DB->query("SELECT * FROM services ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$editId = $_GET['edit'] ?? 0;
$editService = $editId ? $DB->query("SELECT * FROM services WHERE id=" . intval($editId))->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services & Inventory - Admin Panel</title>
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
                <h1><i class="fas fa-concierge-bell"></i> Services & Inventory Management</h1>
                <button class="btn-primary" onclick="document.getElementById('serviceForm').scrollIntoView()">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success">Service saved successfully!</div>
            <?php endif; ?>

            <!-- Service Form -->
            <div class="card" id="serviceForm">
                <div class="card-header">
                    <h3><?=$editService ? 'Edit Service' : 'Add New Service'?></h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?=$editService['id'] ?? ''?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Service Name *</label>
                                <input type="text" name="name" value="<?=esc($editService['name'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Price (₹) *</label>
                                <input type="number" name="price" step="0.01" value="<?=esc($editService['price'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity/Stock *</label>
                                <input type="number" name="qty" value="<?=esc($editService['qty'] ?? '0')?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Service
                            </button>
                            <?php if($editService): ?>
                                <a href="services.php" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Services List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Services (<?=count($services)?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Name</th>
                                    <th>Price</th>
                                    <th>Stock/Quantity</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($services)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No services found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($services as $s): ?>
                                    <tr>
                                        <td><?=esc($s['id'])?></td>
                                        <td><strong><?=esc($s['name'])?></strong></td>
                                        <td>₹<?=number_format($s['price'], 2)?></td>
                                        <td>
                                            <span class="<?=$s['qty'] < 10 ? 'text-danger' : ''?>">
                                                <?=esc($s['qty'])?>
                                            </span>
                                        </td>
                                        <td><?=date('M d, Y', strtotime($s['created_at']))?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?=esc($s['id'])?>" class="btn-icon btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this service?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?=esc($s['id'])?>">
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
