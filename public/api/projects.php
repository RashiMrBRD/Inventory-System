<?php
/**
 * Projects API Endpoint
 * Handles CRUD operations for projects
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Project;
use App\Helper\CurrencyHelper;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $projectModel = new Project();
    
    switch ($method) {
        case 'GET':
            // Get all projects or single project by ID
            if (isset($_GET['id'])) {
                $project = $projectModel->getById($_GET['id']);
                if ($project) {
                    echo json_encode(['success' => true, 'data' => $project]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Project not found']);
                }
            } elseif (isset($_GET['summary']) || isset($_GET['stats'])) {
                // Support both 'summary' (new, not blocked) and 'stats' (old, for compatibility)
                $stats = $projectModel->getStats();
                echo json_encode(['success' => true, 'data' => $stats]);
            } elseif (isset($_GET['profitability']) && isset($_GET['project_id'])) {
                // Get profitability report
                $profitability = $projectModel->getProfitability($_GET['project_id']);
                if ($profitability) {
                    echo json_encode(['success' => true, 'data' => $profitability]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Project not found']);
                }
            } elseif (isset($_GET['budget_vs_actual']) && isset($_GET['project_id'])) {
                // Get budget vs actual report
                $report = $projectModel->getBudgetVsActual($_GET['project_id']);
                if ($report) {
                    echo json_encode(['success' => true, 'data' => $report]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Project not found']);
                }
            } elseif (isset($_GET['generate_invoice']) && isset($_GET['project_id'])) {
                // Generate invoice from project
                $invoice = $projectModel->generateInvoice($_GET['project_id']);
                if ($invoice) {
                    echo json_encode(['success' => true, 'data' => $invoice]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Project not found or no billable items']);
                }
            } else {
                $projects = $projectModel->getAll();
                echo json_encode(['success' => true, 'data' => $projects]);
            }
            break;
            
        case 'POST':
            // Create new project
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            // Validate required fields and Handle different POST actions
            $action = $input['action'] ?? 'create';
            
            switch ($action) {
                case 'add_time':
                    // Add time entry
                    if (empty($input['project_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
                        break 2;
                    }
                    
                    $success = $projectModel->addTimeEntry($input['project_id'], $input['time_entry'] ?? []);
                    echo json_encode([
                        'success' => $success,
                        'message' => $success ? 'Time entry added successfully' : 'Failed to add time entry'
                    ]);
                    break 2;
                    
                case 'add_expense':
                    // Add expense
                    if (empty($input['project_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
                        break 2;
                    }
                    
                    $success = $projectModel->addExpense($input['project_id'], $input['expense'] ?? []);
                    echo json_encode([
                        'success' => $success,
                        'message' => $success ? 'Expense added successfully' : 'Failed to add expense'
                    ]);
                    break 2;
                    
                case 'create_template':
                    // Create template from project
                    if (empty($input['project_id']) || empty($input['template_name'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Project ID and template name are required']);
                        break 2;
                    }
                    
                    $templateId = $projectModel->createTemplate($input['project_id'], $input['template_name']);
                    echo json_encode([
                        'success' => $templateId !== null,
                        'message' => $templateId ? 'Template created successfully' : 'Failed to create template',
                        'template_id' => $templateId
                    ]);
                    break 2;
                    
                case 'from_template':
                    // Create project from template
                    if (empty($input['template_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Template ID is required']);
                        break 2;
                    }
                    
                    $projectId = $projectModel->createFromTemplate($input['template_id'], $input['overrides'] ?? []);
                    echo json_encode([
                        'success' => $projectId !== null,
                        'message' => $projectId ? 'Project created from template' : 'Failed to create project',
                        'project_id' => $projectId
                    ]);
                    break 2;
                    
                case 'mark_invoiced':
                    // Mark time/expenses as invoiced
                    if (empty($input['project_id']) || empty($input['invoice_id'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Project ID and Invoice ID are required']);
                        break 2;
                    }
                    
                    $success = $projectModel->markAsInvoiced($input['project_id'], $input['invoice_id']);
                    echo json_encode([
                        'success' => $success,
                        'message' => $success ? 'Marked as invoiced successfully' : 'Failed to mark as invoiced'
                    ]);
                    break 2;
                    
                case 'create':
                default:
                    // Create new project
                    if (empty($input['name'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Project name is required']);
                        break 2;
                    }
                    
                    if (empty($input['client'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Client is required']);
                        break 2;
                    }
                    
                    // Prepare project data
                    $projectData = [
                        'project_id' => $input['project_id'] ?? 'PRJ-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                        'name' => trim($input['name']),
                        'client' => trim($input['client']),
                        'status' => $input['status'] ?? 'active',
                        'start_date' => $input['start_date'] ?? date('Y-m-d'),
                        'end_date' => $input['end_date'] ?? null,
                        'budget' => (float)($input['budget'] ?? 0),
                        'spent' => 0,
                        'hourly_rate' => (float)($input['hourly_rate'] ?? 50),
                        'tasks' => $input['tasks'] ?? [],
                        'milestones' => $input['milestones'] ?? [],
                        'team' => $input['team'] ?? [],
                        'time_entries' => $input['time'] ?? [],
                        'expenses' => [],
                        'created_by' => $user['_id'] ?? null,
                        'created_by_name' => $user['name'] ?? 'Unknown'
                    ];
                    
                    $projectId = $projectModel->create($projectData);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Project created successfully',
                        'project_id' => $projectId
                    ]);
                    break 2;
            }
            break;
            
        case 'PUT':
            // Update existing project
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Project ID is required']);
                break;
            }
            
            $projectId = $input['id'];
            unset($input['id']);
            
            // Prepare update data
            $updateData = [];
            $allowedFields = ['name', 'client', 'status', 'start_date', 'end_date', 'budget', 'spent', 'hourly_rate', 'tasks', 'milestones', 'team', 'time_entries'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No data to update']);
                break;
            }
            
            $success = $projectModel->update($projectId, $updateData);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Project not found or no changes made']);
            }
            break;
            
        case 'DELETE':
            // Delete project
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id']) && !isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Project ID is required']);
                break;
            }
            
            $projectId = $input['id'] ?? $_GET['id'];
            $success = $projectModel->delete($projectId);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Project not found']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
