<?php
/**
 * Logout Handler
 * This file handles user logout
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;

$authController = new AuthController();
$authController->logout();

header("Location: login.php");
exit();
