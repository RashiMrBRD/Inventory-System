<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Fetch all inventory items
$items = $inventoryCollection->find([], ['sort' => ['date_added' => -1]])->toArray();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Sheet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            background: #7a9eb0;
            padding: 30px;
            color: #333;
        }
        
        .header h1 {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item label {
            color: #555;
            font-weight: 500;
        }
        
        .info-item .value {
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            background: #4caf50;
            border-radius: 50%;
            display: inline-block;
        }
        
        .access-badge {
            background: white;
            padding: 5px 15px;
            border-radius: 4px;
            color: #333;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-add {
            background: #333;
            color: white;
        }
        
        .btn-add:hover {
            background: #555;
        }
        
        .btn-remove {
            background: #333;
            color: white;
        }
        
        .btn-remove:hover {
            background: #555;
        }
        
        .btn-edit {
            background: #333;
            color: white;
        }
        
        .btn-edit:hover {
            background: #555;
        }
        
        .btn-logout {
            background: #666;
            color: white;
            padding: 8px 20px;
            font-size: 13px;
        }
        
        .btn-logout:hover {
            background: #888;
        }
        
        .table-container {
            padding: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f8f8;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
            font-size: 14px;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        tr.low-stock {
            background: #ffe6e6;
        }
        
        tr.low-stock:hover {
            background: #ffd6d6;
        }
        
        .quantity-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .quantity-badge.low {
            background: #ffebee;
            color: #c62828;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .row-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit-row {
            background: #2196F3;
            color: white;
        }
        
        .btn-edit-row:hover {
            background: #1976D2;
        }
        
        .btn-delete-row {
            background: #f44336;
            color: white;
        }
        
        .btn-delete-row:hover {
            background: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Inventory Sheet</h1>
            <div class="header-info">
                <div class="user-info">
                    <div class="info-item">
                        <label>Employee:</label>
                        <div class="value">
                            <span class="status-indicator"></span>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Access Level:</label>
                        <div class="value">
                            <span class="access-badge"><?php echo htmlspecialchars($user['access_level']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="add_item.php" class="btn btn-add">ADD ITEMS</a>
                    <button onclick="toggleRemoveMode()" class="btn btn-remove" id="removeBtn">REMOVE ITEMS</button>
                    <button onclick="toggleEditMode()" class="btn btn-edit" id="editBtn">EDIT ITEMS</button>
                    <a href="logout.php" class="btn btn-logout">LOGOUT</a>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Lifespan</th>
                        <th>Quantity (boxes)</th>
                        <th>DATE ADDED</th>
                        <th class="actions-column" style="display: none;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="no-data">No inventory items found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr class="<?php echo $item['quantity'] <= 5 ? 'low-stock' : ''; ?>">
                                <td><?php echo htmlspecialchars($item['barcode']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['type']); ?></td>
                                <td><?php echo htmlspecialchars($item['lifespan']); ?></td>
                                <td>
                                    <span class="quantity-badge <?php echo $item['quantity'] <= 5 ? 'low' : ''; ?>">
                                        <?php echo htmlspecialchars($item['quantity']); ?>
                                    </span>
                                </td>
                                <td><?php echo $item['date_added']->toDateTime()->format('d M Y'); ?></td>
                                <td class="actions-column" style="display: none;">
                                    <div class="row-actions">
                                        <a href="edit_item.php?id=<?php echo $item['_id']; ?>" class="btn-small btn-edit-row edit-action" style="display:none;">Edit</a>
                                        <a href="delete_item.php?id=<?php echo $item['_id']; ?>" class="btn-small btn-delete-row remove-action" style="display:none;" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        let removeMode = false;
        let editMode = false;
        
        function toggleRemoveMode() {
            removeMode = !removeMode;
            editMode = false;
            
            const removeActions = document.querySelectorAll('.remove-action');
            const editActions = document.querySelectorAll('.edit-action');
            const actionsColumns = document.querySelectorAll('.actions-column');
            const removeBtn = document.getElementById('removeBtn');
            const editBtn = document.getElementById('editBtn');
            
            if (removeMode) {
                actionsColumns.forEach(col => col.style.display = 'table-cell');
                removeActions.forEach(action => action.style.display = 'inline-block');
                editActions.forEach(action => action.style.display = 'none');
                removeBtn.style.background = '#f44336';
                removeBtn.textContent = 'CANCEL REMOVE';
                editBtn.style.background = '#333';
                editBtn.textContent = 'EDIT ITEMS';
            } else {
                actionsColumns.forEach(col => col.style.display = 'none');
                removeActions.forEach(action => action.style.display = 'none');
                removeBtn.style.background = '#333';
                removeBtn.textContent = 'REMOVE ITEMS';
            }
        }
        
        function toggleEditMode() {
            editMode = !editMode;
            removeMode = false;
            
            const removeActions = document.querySelectorAll('.remove-action');
            const editActions = document.querySelectorAll('.edit-action');
            const actionsColumns = document.querySelectorAll('.actions-column');
            const removeBtn = document.getElementById('removeBtn');
            const editBtn = document.getElementById('editBtn');
            
            if (editMode) {
                actionsColumns.forEach(col => col.style.display = 'table-cell');
                editActions.forEach(action => action.style.display = 'inline-block');
                removeActions.forEach(action => action.style.display = 'none');
                editBtn.style.background = '#2196F3';
                editBtn.textContent = 'CANCEL EDIT';
                removeBtn.style.background = '#333';
                removeBtn.textContent = 'REMOVE ITEMS';
            } else {
                actionsColumns.forEach(col => col.style.display = 'none');
                editActions.forEach(action => action.style.display = 'none');
                editBtn.style.background = '#333';
                editBtn.textContent = 'EDIT ITEMS';
            }
        }
    </script>
</body>
</html>
