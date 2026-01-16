<?php

namespace App\Service;

use App\Helper\SessionHelper;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Operation\FindOneAndUpdate;

class RateLimitService
{
    private $collection;
    private array $config;
    private SecurityEventService $securityEventService;

    public function __construct(?array $rateLimitConfig = null)
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        $this->config = $rateLimitConfig ?? ($appConfig['api']['rate_limit'] ?? []);

        $db = DatabaseService::getInstance();
        $this->collection = $db->getCollection('rate_limits');

        $this->securityEventService = new SecurityEventService();

        $this->ensureIndexes();
    }

    private function ensureIndexes(): void
    {
        try {
            $this->collection->createIndex(['expires_at' => 1], ['expireAfterSeconds' => 0]);
            $this->collection->createIndex(['action' => 1, 'ip' => 1, 'window_start' => -1]);
        } catch (\Throwable $e) {
        }
    }

    public function check(string $action, ?string $identifier = null, ?int $maxRequests = null, ?int $perMinutes = null): array
    {
        $enabled = (bool)($this->config['enabled'] ?? true);

        $maxRequests = (int)($maxRequests ?? ($this->config['max_requests'] ?? 100));
        $perMinutes = (int)($perMinutes ?? ($this->config['per_minutes'] ?? 60));

        $windowSeconds = max(1, $perMinutes * 60);
        $now = time();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
        $resetAt = $windowStart + $windowSeconds;
        $expiresAt = $resetAt + 5;

        $ip = $identifier ?? $this->getClientIp();

        if (!$enabled) {
            return [
                'allowed' => true,
                'remaining' => PHP_INT_MAX,
                'retry_after' => 0,
                'limit' => $maxRequests,
                'reset_at' => $resetAt,
                'ip' => $ip,
                'key' => '',
            ];
        }

        SessionHelper::start();
        $sessionId = session_id();

        $keyRaw = $action . '|' . $ip . '|' . $windowStart;
        $docId = 'rl:' . hash('sha256', $keyRaw);

        try {
            $doc = $this->collection->findOneAndUpdate(
                ['_id' => $docId],
                [
                    '$inc' => ['count' => 1],
                    '$setOnInsert' => [
                        'action' => $action,
                        'ip' => $ip,
                        'session_id' => $sessionId,
                        'window_start' => new UTCDateTime($windowStart * 1000),
                        'reset_at' => new UTCDateTime($resetAt * 1000),
                        'expires_at' => new UTCDateTime($expiresAt * 1000),
                        'created_at' => new UTCDateTime(),
                    ],
                    '$set' => [
                        'updated_at' => new UTCDateTime(),
                    ],
                ],
                [
                    'upsert' => true,
                    'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                ]
            );

            $count = (int)($doc['count'] ?? 0);
            $allowed = ($count <= $maxRequests);
            $remaining = max(0, $maxRequests - $count);
            $retryAfter = $allowed ? 0 : max(0, $resetAt - $now);

            if (!$allowed) {
                $this->securityEventService->log('anonymous', 'Rate limit exceeded', 'security', [
                    'action' => $action,
                    'ip' => $ip,
                    'path' => $_SERVER['REQUEST_URI'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'limit' => $maxRequests,
                    'window_seconds' => $windowSeconds,
                    'reset_at' => $resetAt,
                ]);
            }

            return [
                'allowed' => $allowed,
                'remaining' => $remaining,
                'retry_after' => $retryAfter,
                'limit' => $maxRequests,
                'reset_at' => $resetAt,
                'ip' => $ip,
                'key' => $docId,
            ];
        } catch (\Throwable $e) {
            $this->securityEventService->log('anonymous', 'Rate limiting error', 'security', [
                'action' => $action,
                'ip' => $ip,
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'error' => $e->getMessage(),
            ]);

            return [
                'allowed' => true,
                'remaining' => $maxRequests,
                'retry_after' => 0,
                'limit' => $maxRequests,
                'reset_at' => $resetAt,
                'ip' => $ip,
                'key' => $docId,
            ];
        }
    }

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
