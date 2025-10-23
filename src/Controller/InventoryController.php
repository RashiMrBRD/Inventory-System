<?php

namespace App\Controller;

use App\Model\Inventory;

/**
 * Inventory Controller
 * This class handles inventory-related business logic
 */
class InventoryController
{
    private Inventory $inventoryModel;

    public function __construct()
    {
        $this->inventoryModel = new Inventory();
    }

    /**
     * Get all inventory items
     * 
     * @return array
     */
    public function getAllItems(): array
    {
        try {
            $items = $this->inventoryModel->getAll();
            
            return [
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve inventory items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get a single item by ID
     * 
     * @param string $id
     * @return array
     */
    public function getItem(string $id): array
    {
        try {
            $item = $this->inventoryModel->findById($id);
            
            if ($item) {
                return [
                    'success' => true,
                    'data' => $item
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Item not found'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve item: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new inventory item
     * 
     * @param array $data
     * @return array
     */
    public function createItem(array $data): array
    {
        // Validate required fields
        $requiredFields = ['barcode', 'name', 'type', 'quantity'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Field '$field' is required"
                ];
            }
        }

        // Check if barcode already exists
        $existing = $this->inventoryModel->findByBarcode($data['barcode']);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'An item with this barcode already exists'
            ];
        }

        try {
            $itemId = $this->inventoryModel->create($data);
            
            if ($itemId) {
                return [
                    'success' => true,
                    'message' => 'Item created successfully',
                    'id' => $itemId
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create item'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create item: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an inventory item
     * 
     * @param string $id
     * @param array $data
     * @return array
     */
    public function updateItem(string $id, array $data): array
    {
        // Check if item exists
        $existing = $this->inventoryModel->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'message' => 'Item not found'
            ];
        }

        // If barcode is being updated, check if it's already used by another item
        if (isset($data['barcode']) && $data['barcode'] !== $existing['barcode']) {
            $duplicateBarcode = $this->inventoryModel->findByBarcode($data['barcode']);
            if ($duplicateBarcode && (string)$duplicateBarcode['_id'] !== $id) {
                return [
                    'success' => false,
                    'message' => 'Another item with this barcode already exists'
                ];
            }
        }

        try {
            $success = $this->inventoryModel->update($id, $data);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Item updated successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No changes were made'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete an inventory item
     * 
     * @param string $id
     * @return array
     */
    public function deleteItem(string $id): array
    {
        try {
            $success = $this->inventoryModel->delete($id);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Item deleted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Item not found or already deleted'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete item: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get low stock items
     * 
     * @param int $threshold
     * @return array
     */
    public function getLowStockItems(int $threshold = 5): array
    {
        try {
            $items = $this->inventoryModel->getLowStock($threshold);
            
            return [
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve low stock items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search inventory items
     * 
     * @param string $query
     * @return array
     */
    public function searchItems(string $query): array
    {
        if (empty($query)) {
            return [
                'success' => false,
                'message' => 'Search query is required'
            ];
        }

        try {
            $items = $this->inventoryModel->search($query);
            
            return [
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to search items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get inventory statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $stats = $this->inventoryModel->getStatistics();
            
            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ];
        }
    }
}
