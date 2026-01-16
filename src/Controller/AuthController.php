<?php

namespace App\Controller;

use App\Model\User;
use App\Service\SessionService;

/**
 * Auth Controller
 * This class handles authentication-related operations
 */
class AuthController
{
    private User $userModel;
    private SessionService $sessionService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->sessionService = new SessionService();
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function hasAnyUser(): bool
    {
        return $this->userModel->hasAnyUser();
    }

    public function createInitialAdmin(array $data): array
    {
        try {
            if ($this->userModel->hasAnyUser()) {
                return [
                    'success' => false,
                    'message' => 'An account already exists. Please sign in.'
                ];
            }

            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';
            $email = trim($data['email'] ?? '');
            $fullName = trim($data['full_name'] ?? '');

            if ($username === '' || $password === '') {
                return [
                    'success' => false,
                    'message' => 'Username and password are required'
                ];
            }

            if ($password !== $confirmPassword) {
                return [
                    'success' => false,
                    'message' => 'Password and confirmation do not match'
                ];
            }

            if (strlen($password) < 6) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least 6 characters'
                ];
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Please enter a valid email address'
                ];
            }

            $userData = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'full_name' => $fullName !== '' ? $fullName : $username,
                'access_level' => 'admin',
                'role' => 'admin'
            ];

            $id = $this->userModel->create($userData);
            if (!$id) {
                return [
                    'success' => false,
                    'message' => 'Failed to create admin account'
                ];
            }

            $user = $this->userModel->findById($id);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Admin account created but could not be loaded'
                ];
            }

            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['username'] = $user['username'];

            $fullNameSession = isset($user['full_name']) ? trim((string)$user['full_name']) : '';
            if ($fullNameSession === '') {
                $fullNameSession = $user['username'];
            }
            $_SESSION['full_name'] = $fullNameSession;

            $_SESSION['access_level'] = $user['access_level'] ?? 'admin';
            $_SESSION['last_activity'] = time();

            $this->sessionService->createSession(
                (string)$user['_id'],
                $user['username']
            );

            return [
                'success' => true,
                'message' => 'Admin account created',
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'access_level' => $_SESSION['access_level']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function register(array $data): array
    {
        $registerController = new RegisterController($this->userModel, $this->sessionService);
        return $registerController->register($data);
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

            $fullName = isset($user['full_name']) ? trim((string)$user['full_name']) : '';
            if ($fullName === '') {
                $fullName = $user['username'];
            }
            $_SESSION['full_name'] = $fullName;

            $_SESSION['access_level'] = $user['access_level'] ?? 'user';
            $_SESSION['last_activity'] = time();

            // Track session login
            $this->sessionService->createSession(
                (string)$user['_id'],
                $user['username']
            );

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
        // Track session end before destroying
        if (isset($_SESSION['user_id'])) {
            $sessionId = session_id();
            $this->sessionService->endSession($sessionId);
        }
        
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
     * Get the currently logged-in user
     * 
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return $this->userModel->findById($_SESSION['user_id']);
    }

    /**
     * Update user profile information
     * 
     * @param string $userId
     * @param array $data Profile data to update
     * @return array Response with status and message
     */
    public function updateUserProfile(string $userId, array $data): array
    {
        try {
            // Validate email
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Valid email address is required'
                ];
            }

            // Check if username is taken by another user
            if (!empty($data['username'])) {
                $existingUser = $this->userModel->findByUsername($data['username']);
                if ($existingUser && $existingUser['_id'] !== $userId) {
                    return [
                        'success' => false,
                        'message' => 'Username is already taken'
                    ];
                }
            }

            // Update user data
            $updateData = [
                'username' => $data['username'] ?? '',
                'email' => $data['email'] ?? '',
                'firstname' => $data['firstname'] ?? '',
                'lastname' => $data['lastname'] ?? '',
                'nickname' => $data['nickname'] ?? '',
                'display_name' => $data['display_name'] ?? '',
                'role' => $data['role'] ?? 'user',
                'whatsapp' => $data['whatsapp'] ?? '',
                'website' => $data['website'] ?? '',
                'telegram' => $data['telegram'] ?? '',
                'bio' => $data['bio'] ?? '',
                'full_name' => trim(($data['firstname'] ?? '') . ' ' . ($data['lastname'] ?? ''))
            ];

            $result = $this->userModel->updateUser($userId, $updateData);

            if ($result) {
                // Update session data
                $_SESSION['username'] = $updateData['username'];
                $_SESSION['full_name'] = $updateData['full_name'];

                return [
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update profile'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change user password
     * 
     * @param string $userId
     * @param string $oldPassword
     * @param string $newPassword
     * @return array Response with status and message
     */
    public function changePassword(string $userId, string $oldPassword, string $newPassword): array
    {
        try {
            // Get current user
            $user = $this->userModel->findById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Verify old password
            if (!password_verify($oldPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }

            // Validate new password
            if (strlen($newPassword) < 6) {
                return [
                    'success' => false,
                    'message' => 'New password must be at least 6 characters'
                ];
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = $this->userModel->updateUser($userId, ['password' => $hashedPassword]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to change password'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Upload profile photo
     * 
     * @param string $userId
     * @param array $file Uploaded file from $_FILES
     * @return array Response with status and message
     */
    public function uploadProfilePhoto(string $userId, array $file): array
    {
        try {
            // Validate file exists and no upload errors
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                error_log('Upload error: No valid file uploaded');
                return [
                    'success' => false,
                    'message' => 'No valid file uploaded'
                ];
            }

            // Validate file
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $file['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                return [
                    'success' => false,
                    'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'
                ];
            }

            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return [
                    'success' => false,
                    'message' => 'File size must be less than 5MB'
                ];
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid file type detected'
                ];
            }

            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../public/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log('Failed to create upload directory: ' . $uploadDir);
                    return [
                        'success' => false,
                        'message' => 'Failed to create upload directory'
                    ];
                }
            }

            // Check if directory is writable
            if (!is_writable($uploadDir)) {
                error_log('Upload directory not writable: ' . $uploadDir);
                return [
                    'success' => false,
                    'message' => 'Upload directory is not writable'
                ];
            }

            // Generate unique filename
            $newFilename = 'profile_' . preg_replace('/[^a-zA-Z0-9]/', '', $userId) . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $newFilename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Get current user to check for old photo
                $user = $this->userModel->findById($userId);
                if ($user && !empty($user['profile_photo'])) {
                    // Delete old photo
                    $oldPhoto = __DIR__ . '/../../public/' . $user['profile_photo'];
                    if (file_exists($oldPhoto)) {
                        @unlink($oldPhoto); // @ to suppress warnings if file doesn't exist
                    }
                }

                // Update database
                $photoPath = 'uploads/profiles/' . $newFilename;
                $result = $this->userModel->updateUser($userId, ['profile_photo' => $photoPath]);

                if ($result) {
                    return [
                        'success' => true,
                        'message' => 'Profile photo uploaded successfully',
                        'photo_path' => $photoPath
                    ];
                } else {
                    error_log('Failed to update user photo in database for user: ' . $userId);
                    return [
                        'success' => false,
                        'message' => 'Failed to update database'
                    ];
                }
            } else {
                $error = error_get_last();
                error_log('Failed to move uploaded file: ' . ($error['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'message' => 'Failed to upload file'
                ];
            }
        } catch (\Exception $e) {
            error_log('Profile photo upload exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove profile photo
     * 
     * @param string $userId
     * @return array Response with status and message
     */
    public function removeProfilePhoto(string $userId): array
    {
        try {
            // Get current user
            $user = $this->userModel->findById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            if (!empty($user['profile_photo'])) {
                // Delete photo file
                $photoPath = __DIR__ . '/../../public/' . $user['profile_photo'];
                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }

                // Update database
                $result = $this->userModel->updateUser($userId, ['profile_photo' => '']);

                if ($result) {
                    return [
                        'success' => true,
                        'message' => 'Profile photo removed successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Failed to update database'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'No profile photo to remove'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Require login - redirect if not logged in
     * This method is used to protect pages that require authentication
     * 
     * @param string $redirectUrl
     */
    public function requireLogin(string $redirectUrl = '/login'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: $redirectUrl");
            exit();
        }
    }

    /**
     * Update user password
     * 
     * @param string $userId
     * @param string $newPassword
     * @return array Response with status and message
     */
    public function updatePassword(string $userId, string $newPassword): array
    {
        try {
            // Validate new password
            if (strlen($newPassword) < 6) {
                return [
                    'success' => false,
                    'message' => 'New password must be at least 6 characters'
                ];
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = $this->userModel->updateUser($userId, ['password' => $hashedPassword]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Password updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update password'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
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
        
        // Update session activity in database
        if (isset($_SESSION['user_id'])) {
            $sessionId = session_id();
            $this->sessionService->updateActivity($sessionId);
        }
        
        return true;
    }
}
