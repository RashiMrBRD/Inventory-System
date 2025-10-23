<?php
require_once 'config.php';
requireLogin();

$id = $_GET['id'] ?? '';
$error = '';

try {
    $item = $inventoryCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
} catch (Exception $e) {
    $item = null;
}

if (!$item) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['barcode'];
    $name = $_POST['name'];
    $type = $_POST['type'];
    $lifespan = $_POST['lifespan'];
    $quantity = (int)$_POST['quantity'];
    
    $result = $inventoryCollection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => [
            'barcode' => $barcode,
            'name' => $name,
            'type' => $type,
            'lifespan' => $lifespan,
            'quantity' => $quantity
        ]]
    );
    
    if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
        header("Location: index.php");
        exit();
    } else {
        $error = "Error updating item";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Edit Item</h1>
        <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <label>Barcode:</label>
            <input type="text" name="barcode" value="<?= htmlspecialchars($item['barcode']) ?>" required>
            <label>Name:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
            <label>Type:</label>
            <select name="type" required>
                <option value="Packed Goods" <?= $item['type']=='Packed Goods'?'selected':'' ?>>Packed Goods</option>
                <option value="Fruits" <?= $item['type']=='Fruits'?'selected':'' ?>>Fruits</option>
                <option value="Pastries" <?= $item['type']=='Pastries'?'selected':'' ?>>Pastries</option>
            </select>
            <label>Lifespan:</label>
            <select name="lifespan" required>
                <option value="1 week" <?= $item['lifespan']=='1 week'?'selected':'' ?>>1 week</option>
                <option value="3 days" <?= $item['lifespan']=='3 days'?'selected':'' ?>>3 days</option>
                <option value="2 to 10 years" <?= $item['lifespan']=='2 to 10 years'?'selected':'' ?>>2 to 10 years</option>
            </select>
            <label>Quantity:</label>
            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" required>
            <button type="submit">Update Item</button>
            <a href="index.php" class="btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
