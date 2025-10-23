<?php
require_once 'config.php';
requireLogin();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'] ?? '';
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? '';
    $lifespan = $_POST['lifespan'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $date_added = $_POST['date_added'] ?? date('Y-m-d');
    
    if (!empty($barcode) && !empty($name)) {
        $existing = $inventoryCollection->findOne(['barcode' => $barcode]);
        
        if ($existing) {
            $error = "Barcode already exists!";
        } else {
            $inventoryCollection->insertOne([
                'barcode' => $barcode,
                'name' => $name,
                'type' => $type,
                'lifespan' => $lifespan,
                'quantity' => $quantity,
                'date_added' => new MongoDB\BSON\UTCDateTime(strtotime($date_added) * 1000)
            ]);
            header("Location: index.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Add New Item</h1>
        <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <label>Barcode:</label>
            <input type="text" name="barcode" required>
            <label>Name:</label>
            <input type="text" name="name" required>
            <label>Type:</label>
            <select name="type" required>
                <option value="Packed Goods">Packed Goods</option>
                <option value="Fruits">Fruits</option>
                <option value="Pastries">Pastries</option>
            </select>
            <label>Lifespan:</label>
            <select name="lifespan" required>
                <option value="1 week">1 week</option>
                <option value="3 days">3 days</option>
                <option value="2 to 10 years">2 to 10 years</option>
            </select>
            <label>Quantity:</label>
            <input type="number" name="quantity" value="0" required>
            <label>Date Added:</label>
            <input type="date" name="date_added" value="<?= date('Y-m-d') ?>" required>
            <button type="submit">Add Item</button>
            <a href="index.php" class="btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
