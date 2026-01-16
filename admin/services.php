<?php
session_start();
require_once __DIR__ . '/../db.php';
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $qty = intval($_POST['qty']);

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadDir = '../public/uploads/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = 'service_' . time() . '_' . rand(100, 999) . '.' . $ext;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = 'uploads/' . $fileName;
            }
        }

        if ($id) {
            if ($imagePath) {
                $stmt = $DB->prepare("UPDATE services SET name=?, description=?, price=?, qty=?, image=? WHERE id=?");
                $stmt->bind_param('ssdisi', $name, $description, $price, $qty, $imagePath, $id);
            } else {
                $stmt = $DB->prepare("UPDATE services SET name=?, description=?, price=?, qty=? WHERE id=?");
                $stmt->bind_param('ssdii', $name, $description, $price, $qty, $id);
            }
        } else {
            $img = $imagePath ?? '';
            $stmt = $DB->prepare("INSERT INTO services (name, description, price, qty, image) VALUES(?,?,?,?,?)");
            $stmt->bind_param('ssdis', $name, $description, $price, $qty, $img);
        }
        $stmt->execute();
        header('Location: services.php?success=1');
        exit;
    } elseif ($action === 'delete') {
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
    <link rel="stylesheet" href="admin-styles.css?v=<?= time() ?>">
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

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Service saved successfully!</div>
            <?php endif; ?>

            <!-- Service Form -->
            <div class="card" id="serviceForm">
                <div class="card-header">
                    <h3><?= $editService ? 'Edit Service' : 'Add New Service' ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $editService['id'] ?? '' ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Service Name *</label>
                                <input type="text" name="name" value="<?= esc($editService['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Short Description</label>
                                <textarea name="description"
                                    rows="2"><?= esc($editService['description'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Service Image</label>
                                <?php if (!empty($editService['image'])): ?>
                                    <div class="mb-2">
                                        <img src="../public/<?= esc($editService['image']) ?>"
                                            style="height:50px; border-radius:4px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="image" accept="image/*">
                            </div>
                            <div class="form-group">
                                <label>Price (₹) *</label>
                                <input type="number" name="price" step="0.01"
                                    value="<?= esc($editService['price'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Quantity/Stock *</label>
                                <input type="number" name="qty" value="<?= esc($editService['qty'] ?? '0') ?>" required
                                    min="0">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Service
                            </button>
                            <?php if ($editService): ?>
                                <a href="services.php" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Services List -->
            <div class="card">
                <div class="card-header">
                    <h3>All Services (<?= count($services) ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Service Name</th>
                                    <th>Price</th>
                                    <th>Stock/Quantity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($services)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No services found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($services as $s): ?>
                                        <tr>
                                            <td><?= esc($s['id']) ?></td>
                                            <td>
                                                <?php if (!empty($s['image'])):
                                                    $displayImg = $s['image'];
                                                    if (strpos($displayImg, 'http') !== 0 && strpos($displayImg, 'uploads/') !== 0) {
                                                        $displayImg = 'uploads/' . $displayImg;
                                                    }
                                                    // Add ../public/ only if it's local
                                                    if (strpos($displayImg, 'http') !== 0) {
                                                        $displayImg = '../public/' . $displayImg;
                                                    }
                                                    ?>
                                                    <img src="<?= esc($displayImg) ?>"
                                                        style="width:50px; height:50px; object-fit:cover; border-radius:4px;">
                                                <?php else: ?>
                                                    <span class="text-muted">No Img</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= esc($s['name']) ?></strong>
                                                <?php if (!empty($s['description'])): ?>
                                                    <div
                                                        style="font-size:0.85em; color:#666; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                        <?= esc($s['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>₹<?= number_format($s['price'], 2) ?></td>
                                            <td>
                                                <span class="<?= $s['qty'] < 10 ? 'text-danger' : '' ?>">
                                                    <?= esc($s['qty']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?edit=<?= esc($s['id']) ?>" class="btn-icon btn-primary"
                                                        title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="post" style="display:inline;"
                                                        onsubmit="return confirm('Delete this service?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= esc($s['id']) ?>">
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

    <script src="admin-scripts.js?v=<?= time() ?>"></script>
</body>

</html>