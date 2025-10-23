<?php

namespace App\Controller;

use App\Model\User;

/**
 * Auth Controller
 * This class handles authentication-related operations
 */
class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Login a user
     * This method validates credentials and creates a session
     * 
     * @param string $username
     * @param string $password
     * @return array Response with status and message
     */
    public function login(string $username, string $password): array
    {
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required'
            ];
        }

        $user = $this->userModel->verifyCredentials($username, $password);

        if ($user) {
            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
            $_SESSION['access_level'] = $user['access_level'] ?? 'user';
            $_SESSION['last_activity'] = time();

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'access_level' => $_SESSION['access_level']
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }

    /**
     * Logout the current user
     * This method destroys the session
     * 
     * @return array Response with status
     */
    public function logout(): array
    {
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }

    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current logged-in user
     * 
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return $this->userModel->findById($_SESSION['user_id']);
    }

    /**
     * Require login - redirect if not logged in
     * This method is used to protect pages that require authentication
     * 
     * @param string $redirectUrl
     */
    public function requireLogin(string $redirectUrl = '/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: $redirectUrl");
            exit();
        }
    }

    /**
     * Check session timeout
     * This method validates if the session is still active
     * 
     * @param int $timeout Timeout in seconds (default: 7200 = 2 hours)
     * @return bool
     */
    public function checkSessionTimeout(int $timeout = 7200): bool
    {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            
            if ($elapsed > $timeout) {
                $this->logout();
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}
