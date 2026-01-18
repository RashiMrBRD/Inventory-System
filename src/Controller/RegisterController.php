<?php

namespace App\Controller;

use App\Model\User;
use App\Model\Invite;
use App\Service\DatabaseService;
use App\Service\SecurityEventService;
use App\Service\SessionService;
use App\Service\TimezoneDetectionService;
use MongoDB\BSON\Regex;
use MongoDB\Collection;

class RegisterController
{
    private User $userModel;
    private SessionService $sessionService;
    private SecurityEventService $securityEventService;
    private Collection $usersCollection;
    private TimezoneDetectionService $timezoneDetection;
    private Invite $inviteModel;
    private array $appConfig;

    public function __construct(?User $userModel = null, ?SessionService $sessionService = null, ?SecurityEventService $securityEventService = null)
    {
        $this->userModel = $userModel ?? new User();
        $this->sessionService = $sessionService ?? new SessionService();
        $this->securityEventService = $securityEventService ?? new SecurityEventService();
        $this->timezoneDetection = new TimezoneDetectionService();
        $this->inviteModel = new Invite();
        $this->appConfig = require __DIR__ . '/../../../config/app.php';

        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('users');
        $this->usersCollection = $db->getCollection($collectionName);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function register(array $data): array
    {
        try {
            if (!$this->userModel->hasAnyUser()) {
                return [
                    'success' => false,
                    'message' => 'The first admin account must be created before registrations are allowed.'
                ];
            }

            $email = trim((string)($data['email'] ?? ''));
            $username = trim((string)($data['username'] ?? ''));
            $password = (string)($data['password'] ?? '');
            $confirmPassword = (string)($data['confirm_password'] ?? '');
            $inviteToken = trim((string)($data['invite_token'] ?? $data['invite'] ?? ''));

            if ($username === '') {
                $username = $email;
            }

            $fullName = trim((string)($data['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = $username;
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->securityEventService->log('anonymous', 'Registration failed: invalid email', 'security', $this->buildMeta([
                    'email' => $email,
                ]));

                return [
                    'success' => false,
                    'message' => 'Valid email address is required'
                ];
            }

            if ($username === '') {
                $this->securityEventService->log('anonymous', 'Registration failed: missing username', 'security', $this->buildMeta([
                    'email' => $email,
                ]));

                return [
                    'success' => false,
                    'message' => 'Username is required'
                ];
            }

            if ($password === '') {
                $this->securityEventService->log('anonymous', 'Registration failed: missing password', 'security', $this->buildMeta([
                    'username' => $username,
                ]));

                return [
                    'success' => false,
                    'message' => 'Password is required'
                ];
            }

            if ($confirmPassword !== '' && !hash_equals($password, $confirmPassword)) {
                $this->securityEventService->log('anonymous', 'Registration failed: password confirmation mismatch', 'security', $this->buildMeta([
                    'username' => $username,
                ]));

                return [
                    'success' => false,
                    'message' => 'Password and confirmation do not match'
                ];
            }

            if (strlen($password) < 6) {
                $this->securityEventService->log('anonymous', 'Registration failed: weak password', 'security', $this->buildMeta([
                    'username' => $username,
                ]));

                return [
                    'success' => false,
                    'message' => 'Password must be at least 6 characters'
                ];
            }

            $existingUser = $this->userModel->findByUsername($username);
            if ($existingUser) {
                $this->securityEventService->log('anonymous', 'Registration failed: username already taken', 'security', $this->buildMeta([
                    'username' => $username,
                ]));

                return [
                    'success' => false,
                    'message' => 'Username is already taken'
                ];
            }

            $existingEmailUser = $this->findByEmail($email);
            if ($existingEmailUser) {
                $this->securityEventService->log('anonymous', 'Registration failed: email already in use', 'security', $this->buildMeta([
                    'email' => $email,
                ]));

                return [
                    'success' => false,
                    'message' => 'Email is already in use'
                ];
            }

            // Handle invite token
            $inviteData = null;
            $teams = [];
            $userRole = 'user';

            $allowInvitations = $this->appConfig['security']['allow_invitations'] ?? false;

            if ($allowInvitations) {
                if ($inviteToken === '') {
                    return [
                        'success' => false,
                        'message' => 'Invitation key is required'
                    ];
                }

                // Validate invite token
                $validation = $this->inviteModel->validate($inviteToken);

                if (!$validation['valid']) {
                    $message = $validation['reason'] ?? 'Invalid invitation key';
                    $invalidMessage = 'Invalid invitation key';
                    $lowerMessage = strtolower($message);
                    if (strpos($lowerMessage, 'expired') !== false
                        || strpos($lowerMessage, 'revoked') !== false
                        || strpos($lowerMessage, 'maximum uses') !== false
                        || strpos($lowerMessage, 'not found') !== false) {
                        $message = $invalidMessage;
                    }
                    $this->securityEventService->log('anonymous', 'Registration failed: invalid invite token', 'security', $this->buildMeta([
                        'email' => $email,
                        'invite_token' => $inviteToken,
                    ]));

                    return [
                        'success' => false,
                        'message' => $message
                    ];
                }

                // Get invite details
                $invite = $this->inviteModel->findByToken($inviteToken);

                // Check email restriction
                if (!empty($invite['email']) && $invite['email'] !== $email) {
                    return [
                        'success' => false,
                        'message' => 'This invite is restricted to a specific email address'
                    ];
                }

                // Set user role from invite
                $userRole = $invite['role'] ?? 'user';

                // Add team association if specified
                if (!empty($invite['team_id'])) {
                    $teams[] = [
                        'team_id' => $invite['team_id'],
                        'invited_by' => $invite['inviter_id'],
                        'invited_at' => new \MongoDB\BSON\UTCDateTime(),
                        'role' => $userRole
                    ];
                }

                // Store invite data for later use
                $inviteData = $invite;
            } elseif ($inviteToken !== '') {
                // If invitations are disabled but invite token is provided, ignore it
                $inviteToken = '';
            }

            // Split full_name into firstname and lastname
            $nameParts = explode(' ', trim($fullName), 2);
            $firstname = $nameParts[0] ?? '';
            $lastname = isset($nameParts[1]) ? $nameParts[1] : '';

            $userData = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'full_name' => $fullName,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'access_level' => 'user',
                'role' => $userRole
            ];

            if (!empty($teams)) {
                $userData['teams'] = $teams;
            }

            // Detect and save timezone automatically
            $detectedTimezone = $this->timezoneDetection->detect();
            if ($detectedTimezone !== null && in_array($detectedTimezone, timezone_identifiers_list())) {
                $userData['timezone'] = $detectedTimezone;
            }

            $id = $this->userModel->create($userData);
            if (!$id) {
                $this->securityEventService->log('anonymous', 'Registration failed: user create failed', 'security', $this->buildMeta([
                    'username' => $username,
                    'email' => $email,
                ]));

                return [
                    'success' => false,
                    'message' => 'Failed to create account'
                ];
            }

            // Use the invite token if provided
            if ($inviteToken !== '' && $inviteData) {
                $this->inviteModel->useInvite($inviteToken);
            }

            $user = $this->userModel->findById($id);
            if (!$user) {
                $this->securityEventService->log('anonymous', 'Registration failed: created user could not be loaded', 'security', $this->buildMeta([
                    'user_id' => $id,
                ]));

                return [
                    'success' => false,
                    'message' => 'Account created but could not be loaded'
                ];
            }

            $_SESSION['user_id'] = (string)$user['_id'];
            $_SESSION['username'] = (string)($user['username'] ?? $username);

            $fullNameSession = isset($user['full_name']) ? trim((string)$user['full_name']) : '';
            if ($fullNameSession === '') {
                $fullNameSession = (string)($_SESSION['username'] ?? $username);
            }

            $_SESSION['full_name'] = $fullNameSession;
            $_SESSION['access_level'] = $user['access_level'] ?? 'user';
            $_SESSION['last_activity'] = time();

            // Load timezone from user profile into session
            if (isset($user['timezone']) && in_array($user['timezone'], timezone_identifiers_list())) {
                $_SESSION['timezone'] = $user['timezone'];
                date_default_timezone_set($user['timezone']);
            }

            $this->sessionService->createSession((string)$user['_id'], (string)($user['username'] ?? $username));

            $this->securityEventService->log((string)$user['_id'], 'User registered', 'security', $this->buildMeta([
                'email' => $email,
                'invite_used' => ($inviteToken !== ''),
            ]));

            return [
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'full_name' => $_SESSION['full_name'],
                    'access_level' => $_SESSION['access_level']
                ]
            ];
        } catch (\Throwable $e) {
            $this->securityEventService->log('anonymous', 'Registration error', 'security', $this->buildMeta([
                'error' => $e->getMessage(),
            ]));

            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    private function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        try {
            $pattern = '^' . preg_quote($email, '/') . '$';
            $doc = $this->usersCollection->findOne(['email' => new Regex($pattern, 'i')]);
            return $doc ? (array)$doc : null;
        } catch (\Throwable $e) {
            $doc = $this->usersCollection->findOne(['email' => $email]);
            return $doc ? (array)$doc : null;
        }
    }

    private function resolveTeamInvite(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $owner = $this->usersCollection->findOne(['teams.token' => $token]);
        if (!$owner) {
            return null;
        }

        $ownerId = isset($owner['_id']) ? (string)$owner['_id'] : '';
        $teams = $owner['teams'] ?? [];
        if (!is_array($teams)) {
            return null;
        }

        foreach ($teams as $team) {
            if (!is_array($team)) {
                continue;
            }

            $teamToken = (string)($team['token'] ?? '');
            if ($teamToken !== '' && hash_equals($teamToken, $token)) {
                $teamId = (string)($team['id'] ?? '');
                $teamName = (string)($team['name'] ?? 'Team');
                $teamOwner = (string)($team['owner'] ?? $ownerId);

                if ($teamId === '') {
                    $teamId = bin2hex(random_bytes(6));
                }

                return [
                    'id' => $teamId,
                    'name' => $teamName,
                    'owner' => $teamOwner,
                    'joined_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        return null;
    }

    private function buildMeta(array $extra = []): array
    {
        $meta = [
            'ip' => $this->getClientIp(),
            'path' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        foreach ($extra as $k => $v) {
            $meta[$k] = $v;
        }

        return $meta;
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
