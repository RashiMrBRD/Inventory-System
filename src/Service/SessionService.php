<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class SessionService
{
    private $db;
    private $sessionsCollection;

    public function __construct()
    {
        $client = new Client("mongodb://localhost:27017");
        $this->db = $client->selectDatabase('inventory_system');
        $this->sessionsCollection = $this->db->selectCollection('user_sessions');
        
        // Create indexes for better performance
        $this->createIndexes();
    }

    private function createIndexes()
    {
        try {
            // Index on user_id for faster queries
            $this->sessionsCollection->createIndex(['user_id' => 1]);
            // Index on session_id for lookups
            $this->sessionsCollection->createIndex(['session_id' => 1], ['unique' => true]);
            // Index on is_active for filtering active sessions
            $this->sessionsCollection->createIndex(['is_active' => 1]);
            // Compound index for user's active sessions
            $this->sessionsCollection->createIndex(['user_id' => 1, 'is_active' => 1]);
        } catch (\Exception $e) {
            // Indexes might already exist
        }
    }

    /**
     * Get user's IP address
     */
    private function getUserIP(): string
    {
        $ip = '';
        
        // Try to get IP from various headers
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        // If localhost IP detected, try to get public IP
        if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            $publicIP = $this->getPublicIP();
            if ($publicIP) {
                return $publicIP . ' (via localhost)';
            }
        }
        
        return $ip;
    }
    
    /**
     * Get public IP address for localhost development
     */
    private function getPublicIP(): ?string
    {
        try {
            $services = [
                'https://api.ipify.org',
                'https://icanhazip.com',
                'https://ipinfo.io/ip',
                'https://ifconfig.me/ip'
            ];
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'user_agent' => 'PHP-Session-Tracker/1.0'
                ]
            ]);
            
            foreach ($services as $service) {
                $publicIP = @file_get_contents($service, false, $context);
                if ($publicIP !== false) {
                    $publicIP = trim($publicIP);
                    // Validate IP format
                    if (filter_var($publicIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $publicIP;
                    }
                }
            }
        } catch (\Exception $e) {
            // If all services fail, return null
        }
        
        return null;
    }

    /**
     * Get location from IP address using ip-api.com (free, no key required)
     */
    private function getLocationFromIP(string $ip): array
    {
        // Extract actual IP if it contains "(via localhost)"
        $actualIP = $ip;
        if (strpos($ip, ' (via localhost)') !== false) {
            $actualIP = str_replace(' (via localhost)', '', $ip);
        }
        
        // Check if it's still a localhost/private IP after extraction
        $isLocalhost = ($actualIP === '127.0.0.1' || $actualIP === '::1' || 
                       strpos($actualIP, '192.168.') === 0 || strpos($actualIP, '10.') === 0);
        
        // If localhost and couldn't get public IP, return default
        if ($isLocalhost) {
            return [
                'country' => 'Local',
                'city' => 'Localhost',
                'region' => 'Development',
                'latitude' => 14.5995,  // Default to Manila
                'longitude' => 120.9842,
                'timezone' => 'Asia/Manila',
                'isp' => 'Local Network'
            ];
        }
        
        // Use the actual public IP for geolocation
        $ip = $actualIP;

        try {
            $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,timezone,isp,query";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'PHP-Session-Tracker/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new \Exception('Failed to fetch geolocation data');
            }
            
            $data = json_decode($response, true);
            
            if ($data && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? 'Unknown',
                    'country_code' => $data['countryCode'] ?? '',
                    'city' => $data['city'] ?? 'Unknown',
                    'region' => $data['regionName'] ?? '',
                    'latitude' => $data['lat'] ?? 0,
                    'longitude' => $data['lon'] ?? 0,
                    'timezone' => $data['timezone'] ?? 'UTC',
                    'isp' => $data['isp'] ?? 'Unknown'
                ];
            }
        } catch (\Exception $e) {
            // Fallback to default location
        }

        return [
            'country' => 'Unknown',
            'city' => 'Unknown',
            'region' => '',
            'latitude' => 0,
            'longitude' => 0,
            'timezone' => 'UTC',
            'isp' => 'Unknown'
        ];
    }

    /**
     * Get browser and OS information
     */
    private function getBrowserInfo(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Detect browser
        $browser = 'Unknown';
        if (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        }

        // Detect OS
        $os = 'Unknown';
        if (preg_match('/Windows NT 10/i', $userAgent)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 6.3/i', $userAgent)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/Windows NT 6.2/i', $userAgent)) {
            $os = 'Windows 8';
        } elseif (preg_match('/Windows NT 6.1/i', $userAgent)) {
            $os = 'Windows 7';
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
            $os = 'iOS';
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'user_agent' => $userAgent
        ];
    }

    /**
     * Create a new session record
     */
    public function createSession(string $userId, string $username): array
    {
        $sessionId = session_id();
        $ip = $this->getUserIP();
        $location = $this->getLocationFromIP($ip);
        $browserInfo = $this->getBrowserInfo();
        
        $sessionData = [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'username' => $username,
            'ip_address' => $ip,
            'location' => $location,
            'browser' => $browserInfo['browser'],
            'os' => $browserInfo['os'],
            'user_agent' => $browserInfo['user_agent'],
            'login_time' => new UTCDateTime(),
            'last_activity' => new UTCDateTime(),
            'logout_time' => null,
            'is_active' => true,
            'created_at' => new UTCDateTime()
        ];

        try {
            $result = $this->sessionsCollection->insertOne($sessionData);
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'location' => $location,
                'browser' => $browserInfo['browser'],
                'os' => $browserInfo['os']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update session activity
     */
    public function updateActivity(string $sessionId): bool
    {
        try {
            $result = $this->sessionsCollection->updateOne(
                ['session_id' => $sessionId, 'is_active' => true],
                ['$set' => ['last_activity' => new UTCDateTime()]]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * End a session (logout)
     */
    public function endSession(string $sessionId): bool
    {
        try {
            $result = $this->sessionsCollection->updateOne(
                ['session_id' => $sessionId, 'is_active' => true],
                [
                    '$set' => [
                        'logout_time' => new UTCDateTime(),
                        'is_active' => false
                    ]
                ]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get active sessions for a user
     */
    public function getActiveSessions(string $userId): array
    {
        try {
            $sessions = $this->sessionsCollection->find(
                ['user_id' => $userId, 'is_active' => true],
                ['sort' => ['login_time' => -1]]
            );
            
            $result = [];
            foreach ($sessions as $session) {
                $result[] = [
                    'session_id' => $session['session_id'],
                    'ip_address' => $session['ip_address'],
                    'location' => $session['location'],
                    'browser' => $session['browser'],
                    'os' => $session['os'],
                    'login_time' => $session['login_time']->toDateTime()->format('Y-m-d H:i:s'),
                    'last_activity' => $session['last_activity']->toDateTime()->format('Y-m-d H:i:s'),
                    'is_current' => $session['session_id'] === session_id()
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all sessions for a user (active and inactive)
     */
    public function getAllSessions(string $userId, int $limit = 10): array
    {
        try {
            $sessions = $this->sessionsCollection->find(
                ['user_id' => $userId],
                [
                    'sort' => ['login_time' => -1],
                    'limit' => $limit
                ]
            );
            
            $result = [];
            foreach ($sessions as $session) {
                $loginTime = $session['login_time']->toDateTime();
                $lastActivity = $session['last_activity']->toDateTime();
                $logoutTime = $session['logout_time'] ? $session['logout_time']->toDateTime() : null;
                
                // Calculate session duration
                $duration = $logoutTime 
                    ? $logoutTime->getTimestamp() - $loginTime->getTimestamp()
                    : time() - $loginTime->getTimestamp();
                
                $result[] = [
                    'session_id' => $session['session_id'],
                    'ip_address' => $session['ip_address'],
                    'location' => $session['location'],
                    'browser' => $session['browser'],
                    'os' => $session['os'],
                    'login_time' => $loginTime->format('Y-m-d H:i:s'),
                    'last_activity' => $lastActivity->format('Y-m-d H:i:s'),
                    'logout_time' => $logoutTime ? $logoutTime->format('Y-m-d H:i:s') : null,
                    'duration_seconds' => $duration,
                    'duration_formatted' => $this->formatDuration($duration),
                    'is_active' => $session['is_active'],
                    'is_current' => $session['session_id'] === session_id()
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Terminate other sessions for a user (keep only current session)
     */
    public function terminateOtherSessions(string $userId): int
    {
        $currentSessionId = session_id();
        
        try {
            $result = $this->sessionsCollection->updateMany(
                [
                    'user_id' => $userId,
                    'is_active' => true,
                    'session_id' => ['$ne' => $currentSessionId]
                ],
                [
                    '$set' => [
                        'logout_time' => new UTCDateTime(),
                        'is_active' => false
                    ]
                ]
            );
            
            return $result->getModifiedCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Clean up old inactive sessions (older than 30 days)
     */
    public function cleanupOldSessions(): int
    {
        $thirtyDaysAgo = new UTCDateTime((time() - (30 * 24 * 60 * 60)) * 1000);
        
        try {
            $result = $this->sessionsCollection->deleteMany([
                'is_active' => false,
                'logout_time' => ['$lt' => $thirtyDaysAgo]
            ]);
            
            return $result->getDeletedCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format duration in human-readable format
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . 
                   ($minutes > 0 ? ' ' . $minutes . ' min' : '');
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return $days . ' day' . ($days > 1 ? 's' : '') . 
                   ($hours > 0 ? ' ' . $hours . ' hour' . ($hours > 1 ? 's' : '') : '');
        }
    }
}
