<?php

namespace App\Helper;

use App\Service\CurrencyService;

/**
 * Currency Helper
 * Global helper functions for currency formatting
 */
class CurrencyHelper
{
    /**
     * Get current user's currency preference
     */
    public static function getCurrentCurrency()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['currency'] ?? CurrencyService::detectCurrencyFromIP();
    }

    /**
     * Format amount with user's currency
     */
    public static function format($amount, $decimals = 2)
    {
        $currency = self::getCurrentCurrency();
        return CurrencyService::format($amount, $currency, $decimals);
    }

    /**
     * Get symbol for current currency
     */
    public static function symbol()
    {
        $currency = self::getCurrentCurrency();
        return CurrencyService::getSymbol($currency);
    }

    /**
     * Set user's currency preference
     */
    public static function setCurrency($currencyCode)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['currency'] = $currencyCode;
        return true;
    }
}
