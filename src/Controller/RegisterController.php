<?php

namespace App\Controller;

use App\Model\User;
use App\Service\DatabaseService;
use App\Service\SecurityEventService;
use App\Service\SessionService;
use MongoDB\BSON\Regex;
use MongoDB\Collection;

class RegisterController
{
    private User $userModel;
    private SessionService $sessionService;
    private SecurityEventService $securityEventService;
    private Collection $usersCollection;

    public function __construct(?User $userModel = null, ?SessionService $sessionService = null, ?SecurityEventService $securityEventService = null)
    {
        $this->userModel = $userModel ?? new User();
        $this->sessionService = $sessionService ?? new SessionService();
        $this->securityEventService = $securityEventService ?? new SecurityEventService();

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
            $inviteToken = trim((string)($data['invite_token'] ?? ''));

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

            $teams = [];
            if ($inviteToken !== '') {
                $invite = $this->resolveTeamInvite($inviteToken);
                if (!$invite) {
                    $this->securityEventService->log('anonymous', 'Registration failed: invalid invite token', 'security', $this->buildMeta([
                        'email' => $email,
                        'invite_token' => $inviteToken,
                    ]));

                    return [
                        'success' => false,
                        'message' => 'Invalid invite token'
                    ];
                }

                $teams[] = $invite;
            }

            $userData = [
                'username' => $username,
                'password' => $password,
                'email' => $email,
                'full_name' => $fullName,
                'access_level' => 'user',
                'role' => 'user'
            ];

            if (!empty($teams)) {
                $userData['teams'] = $teams;
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
