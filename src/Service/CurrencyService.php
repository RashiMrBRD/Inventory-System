<?php

namespace App\Service;

/**
 * Currency Service
 * Handles currency detection, conversion, and formatting
 */
class CurrencyService
{
    // Comprehensive currency list with symbols
    private static $currencies = [
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'country' => 'Philippines'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'country' => 'United States'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'country' => 'European Union'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'country' => 'United Kingdom'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'country' => 'Japan'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'country' => 'China'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'country' => 'Australia'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'country' => 'Canada'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'country' => 'Switzerland'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'country' => 'India'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'country' => 'Singapore'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'country' => 'Malaysia'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'country' => 'Thailand'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'country' => 'Indonesia'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫', 'country' => 'Vietnam'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'country' => 'South Korea'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'country' => 'Hong Kong'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'country' => 'New Zealand'],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'country' => 'Sweden'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'country' => 'Norway'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'country' => 'Denmark'],
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'Mex$', 'country' => 'Mexico'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'country' => 'Brazil'],
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'country' => 'South Africa'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'country' => 'UAE'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼', 'country' => 'Saudi Arabia'],
    ];

    // Country to currency mapping
    private static $countryToCurrency = [
        'PH' => 'PHP', 'US' => 'USD', 'GB' => 'GBP', 'JP' => 'JPY',
        'CN' => 'CNY', 'AU' => 'AUD', 'CA' => 'CAD', 'CH' => 'CHF',
        'IN' => 'INR', 'SG' => 'SGD', 'MY' => 'MYR', 'TH' => 'THB',
        'ID' => 'IDR', 'VN' => 'VND', 'KR' => 'KRW', 'HK' => 'HKD',
        'NZ' => 'NZD', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK',
        'MX' => 'MXN', 'BR' => 'BRL', 'ZA' => 'ZAR', 'AE' => 'AED',
        'SA' => 'SAR',
    ];

    /**
     * Get all available currencies
     */
    public static function getAllCurrencies()
    {
        return self::$currencies;
    }

    /**
     * Get currency info by code
     */
    public static function getCurrency($code)
    {
        return self::$currencies[$code] ?? null;
    }

    /**
     * Get currency symbol
     */
    public static function getSymbol($code)
    {
        return self::$currencies[$code]['symbol'] ?? $code;
    }

    /**
     * Detect currency from IP address using free geolocation API
     */
    public static function detectCurrencyFromIP($ip = null)
    {
        // Use visitor's IP if not provided
        if ($ip === null) {
            $ip = self::getClientIP();
        }

        // Skip detection for local IPs
        if (self::isLocalIP($ip)) {
            return 'PHP'; // Default to PHP for local development
        }

        try {
            // Use ip-api.com (free, no key required)
            $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close() is deprecated in PHP 8.5 and has no effect since PHP 8.0

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if ($data['status'] === 'success' && isset($data['countryCode'])) {
                    $countryCode = $data['countryCode'];
                    
                    // Map country code to currency
                    return self::$countryToCurrency[$countryCode] ?? 'USD';
                }
            }
        } catch (\Exception $e) {
            // Silent fail - return default
        }

        return 'PHP'; // Default to Philippine Peso
    }

    /**
     * Get client IP address
     */
    private static function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }

    /**
     * Check if IP is local/private
     */
    private static function isLocalIP($ip)
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost']) 
            || strpos($ip, '192.168.') === 0
            || strpos($ip, '10.') === 0
            || strpos($ip, '172.') === 0;
    }

    /**
     * Format amount with currency
     */
    public static function format($amount, $currency = 'PHP', $decimals = 2)
    {
        $symbol = self::getSymbol($currency);
        $formatted = number_format($amount, $decimals);
        
        // Place symbol before or after based on currency
        $symbolAfter = ['PHP', 'THB', 'VND', 'IDR'];
        
        if (in_array($currency, $symbolAfter)) {
            return "{$symbol}{$formatted}";
        } else {
            return "{$symbol}{$formatted}";
        }
    }

    /**
     * Get exchange rate (placeholder - implement with real API)
     */
    public static function getExchangeRate($from, $to)
    {
        // TODO: Implement with real exchange rate API
        // For now, return 1.0
        return 1.0;
    }

    /**
     * Convert amount between currencies
     */
    public static function convert($amount, $from, $to)
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = self::getExchangeRate($from, $to);
        return $amount * $rate;
    }
}
