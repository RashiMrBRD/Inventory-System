<?php

namespace App\Helper;

/**
 * Session Helper
 * Centralized session management to prevent duplicate session_start() calls
 */
class SessionHelper
{
    /**
     * Start session if not already started
     * 
     * @return bool True if session was started, false if already active
     */
    public static function start(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            return true;
        }
        return false;
    }

    /**
     * Check if session is active
     * 
     * @return bool
     */
    public static function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Set flash message
     * 
     * @param string $message
     * @param string $type success|error|warning|info
     */
    public static function setFlash(string $message, string $type = 'success'): void
    {
        self::start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    /**
     * Get and clear flash message
     * 
     * @return array|null ['message' => string, 'type' => string] or null
     */
    public static function getFlash(): ?array
    {
        self::start();
        
        if (isset($_SESSION['flash_message'])) {
            $flash = [
                'message' => $_SESSION['flash_message'],
                'type' => $_SESSION['flash_type'] ?? 'info'
            ];
            
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            
            return $flash;
        }
        
        return null;
    }

    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     * 
     * @return string|null
     */
    public static function getUserId(): ?string
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get session value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        if (self::isActive()) {
            session_destroy();
        }
    }
}
