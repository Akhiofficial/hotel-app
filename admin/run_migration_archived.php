<?php
require_once __DIR__ . '/../db.php';

try {
    $sql = file_get_contents('migrate_add_archived_status.sql');
    $DB->query($sql);
    echo "Migration Successful: Added 'archived' to status ENUM.";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage();
}
?>