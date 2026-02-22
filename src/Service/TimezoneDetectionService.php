<?php

namespace App\Service;

/**
 * Timezone Detection Service
 * Detects user timezone based on IP geolocation or browser timezone
 */
class TimezoneDetectionService
{
    /**
     * Detect timezone from IP address using geolocation
     * 
     * @param string $ip IP address
     * @return string|null Detected timezone or null if not found
     */
    public function detectFromIp(string $ip): ?string
    {
        // Skip localhost/private IPs
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        try {
            // Use ip-api.com free service (no API key required for basic usage)
            $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=timezone';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'Mozilla/5.0 (compatible; InventorySystem/1.0)'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['timezone']) && $data['timezone'] !== '' && in_array($data['timezone'], timezone_identifiers_list())) {
                return $data['timezone'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Detect timezone from browser Accept-Language header
     * 
     * @param string $acceptLanguage Accept-Language header value
     * @return string|null Detected timezone or null if not found
     */
    public function detectFromAcceptLanguage(string $acceptLanguage): ?string
    {
        if (empty($acceptLanguage)) {
            return null;
        }
        
        // Parse Accept-Language header to get country code
        // Format: en-US,en;q=0.9,fil;q=0.8
        $parts = explode(',', $acceptLanguage);
        $primaryLocale = trim(explode(';', $parts[0])[0]);
        
        // Extract country code (e.g., 'US' from 'en-US')
        if (strpos($primaryLocale, '-') !== false) {
            $countryCode = strtoupper(explode('-', $primaryLocale)[1]);
            
            // Map country codes to timezones
            $timezoneMap = [
                'US' => 'America/New_York',
                'GB' => 'Europe/London',
                'CA' => 'America/Toronto',
                'AU' => 'Australia/Sydney',
                'DE' => 'Europe/Berlin',
                'FR' => 'Europe/Paris',
                'IT' => 'Europe/Rome',
                'ES' => 'Europe/Madrid',
                'NL' => 'Europe/Amsterdam',
                'JP' => 'Asia/Tokyo',
                'KR' => 'Asia/Seoul',
                'CN' => 'Asia/Shanghai',
                'SG' => 'Asia/Singapore',
                'HK' => 'Asia/Hong_Kong',
                'PH' => 'Asia/Manila',
                'MY' => 'Asia/Kuala_Lumpur',
                'ID' => 'Asia/Jakarta',
                'TH' => 'Asia/Bangkok',
                'IN' => 'Asia/Kolkata',
                'AE' => 'Asia/Dubai',
                'BR' => 'America/Sao_Paulo',
                'MX' => 'America/Mexico_City',
                'RU' => 'Europe/Moscow',
            ];
            
            if (isset($timezoneMap[$countryCode])) {
                return $timezoneMap[$countryCode];
            }
        }
        
        return null;
    }
    
    /**
     * Detect timezone using multiple methods
     * Priority: IP geolocation > Accept-Language > Default
     * 
     * @param string|null $ip IP address (optional, will detect if not provided)
     * @param string|null $acceptLanguage Accept-Language header (optional)
     * @return string Detected timezone
     */
    public function detect(?string $ip = null, ?string $acceptLanguage = null): string
    {
        // Get IP if not provided
        if ($ip === null) {
            $ip = $this->getClientIp();
        }
        
        // Get Accept-Language if not provided
        if ($acceptLanguage === null) {
            $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        }
        
        // Try IP geolocation first
        $timezone = $this->detectFromIp($ip);
        if ($timezone !== null) {
            return $timezone;
        }
        
        // Fall back to Accept-Language
        $timezone = $this->detectFromAcceptLanguage($acceptLanguage);
        if ($timezone !== null) {
            return $timezone;
        }
        
        // Final fallback to UTC
        return 'UTC';
    }
    
    /**
     * Check if IP is private/local
     * 
     * @param string $ip IP address
     * @return bool True if IP is private
     */
    private function isPrivateIp(string $ip): bool
    {
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '::1/128',
            'fc00::/7',
            'fe80::/10'
        ];
        
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            // IPv6
            return in_array($ip, ['::1', 'localhost', '127.0.0.1']);
        }
        
        foreach ($privateRanges as $range) {
            $parts = explode('/', $range);
            $rangeIp = $parts[0];
            $mask = isset($parts[1]) ? (int)$parts[1] : 32;
            
            // Validate mask is in valid range
            if ($mask < 0 || $mask > 32) {
                continue;
            }
            
            $rangeLong = ip2long($rangeIp);
            $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask));
            
            if (($ipLong & $maskLong) === ($rangeLong & $maskLong)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return (string)$_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim((string)$forwardedFor[0]);
        }

        return (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
