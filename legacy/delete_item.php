<?php
require_once 'config.php';
requireLogin();

$id = $_GET['id'] ?? '';

if ($id) {
    try {
        $inventoryCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    } catch (Exception $e) {
        // Invalid ID, ignore
    }
}

header("Location: index.php");
exit();
?>
