<?php

namespace App\Helper;

class CsrfHelper
{
    private const DEFAULT_SESSION_KEY = '_csrf_token';

    public static function getToken(string $sessionKey = self::DEFAULT_SESSION_KEY): string
    {
        SessionHelper::start();

        $token = $_SESSION[$sessionKey] ?? '';
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$sessionKey] = $token;
        }

        return $token;
    }

    public static function validate(?string $token, string $sessionKey = self::DEFAULT_SESSION_KEY): bool
    {
        SessionHelper::start();

        $expected = $_SESSION[$sessionKey] ?? '';
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function rotate(string $sessionKey = self::DEFAULT_SESSION_KEY): string
    {
        SessionHelper::start();

        $token = bin2hex(random_bytes(32));
        $_SESSION[$sessionKey] = $token;

        return $token;
    }

    public static function clear(string $sessionKey = self::DEFAULT_SESSION_KEY): void
    {
        SessionHelper::start();

        unset($_SESSION[$sessionKey]);
    }

    public static function setTokenCookie(string $cookieName = 'csrf_token', ?string $token = null): void
    {
        $token = $token ?? self::getToken();

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie($cookieName, $token, [
            'expires' => 0,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}
