<?php
/**
 * Login Page
 * This page handles user authentication
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;

$authController = new AuthController();
$error = '';

// If already logged in, redirect to dashboard
if ($authController->isLoggedIn()) {
    header("Location: /dashboard");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $authController->login($username, $password);
    
    if ($result['success']) {
        header("Location: /dashboard");
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    
    <!-- Minimalist Design System CSS -->
    <link rel="stylesheet" href="assets/css/core.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/utilities.css">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
    /* Minimalist Login Page - LedgerSMB Style */
    body {
        background-color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        padding: var(--space-4);
    }
    
    .login-wrapper {
        width: 100%;
        max-width: 400px;
    }
    
    .login-header {
        text-align: center;
        margin-bottom: var(--space-8);
    }
    
    .login-title {
        font-size: var(--text-2xl);
        font-weight: var(--font-semibold);
        color: var(--text-primary);
        margin: 0 0 var(--space-2) 0;
    }
    
    .login-subtitle {
        font-size: var(--text-sm);
        color: var(--text-secondary);
        margin: 0;
    }
    
    .login-form {
        background-color: var(--bg-primary);
        border: var(--border-width) solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: var(--space-8);
        box-shadow: var(--shadow-sm);
    }
    
    .demo-info {
        margin-top: var(--space-6);
        padding: var(--space-4);
        background-color: var(--bg-secondary);
        border: var(--border-width) solid var(--border-color);
        border-radius: var(--radius-md);
        font-size: var(--text-sm);
        color: var(--text-secondary);
    }
    
    .demo-info strong {
        color: var(--text-primary);
        display: block;
        margin-bottom: var(--space-2);
    }
    
    .demo-info code {
        display: block;
        font-family: var(--font-mono);
        font-size: var(--text-xs);
        background-color: var(--bg-primary);
        padding: var(--space-1) var(--space-2);
        border-radius: var(--radius-sm);
        margin: var(--space-1) 0;
    }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-header">
            <h1 class="login-title">Inventory Management</h1>
            <p class="login-subtitle">Sign in to your account</p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="Enter username"
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Enter password"
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary w-full">
                    Sign In
                </button>
            </form>
        </div>
        
        <div class="demo-info">
            <strong>Demo Credentials</strong>
            <code>Username: admin</code>
            <code>Password: admin123</code>
        </div>
    </div>
</body>
</html>
