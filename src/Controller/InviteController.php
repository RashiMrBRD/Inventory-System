<?php

namespace App\Controller;

use App\Model\Invite;
use App\Model\User;
use App\Controller\AuthController;

/**
 * Invite Controller
 * Manages invite link generation, validation, and usage
 */
class InviteController
{
    private $inviteModel;
    private $userModel;

    public function __construct()
    {
        $this->inviteModel = new Invite();
        $this->userModel = new User();
    }

    /**
     * Generate a new invite link
     */
    public function generateInvite(array $data): array
    {
        try {
            // Check if user is logged in
            if (!isset($_SESSION['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User not authenticated'
                ];
            }

            $inviterId = $_SESSION['user_id'];
            $inviter = $this->userModel->findById($inviterId);

            if (!$inviter) {
                return [
                    'success' => false,
                    'message' => 'Inviter not found'
                ];
            }

            // Check if user can invite (admin or user role only)
            $inviterRole = $inviter['role'] ?? 'user';
            if (!in_array($inviterRole, ['admin', 'user'])) {
                return [
                    'success' => false,
                    'message' => 'Only Admin and User roles can create invite links'
                ];
            }

            // Validate role to assign
            $allowedRoles = ['user', 'manager', 'viewer'];
            $role = $data['role'] ?? 'user';
            if (!in_array($role, $allowedRoles)) {
                $role = 'user';
            }

            // Admin can invite any role, User can only invite users
            if ($inviterRole === 'user' && $role !== 'user') {
                return [
                    'success' => false,
                    'message' => 'Users can only invite others with User role'
                ];
            }

            // Prepare invite data
            $inviteData = [
                'inviter_id' => $inviterId,
                'inviter_name' => $inviter['full_name'] ?? $inviter['username'] ?? '',
                'inviter_email' => $inviter['email'] ?? '',
                'team_id' => $data['team_id'] ?? null,
                'role' => $role,
                'email' => $data['email'] ?? null,
                'max_uses' => (int)($data['max_uses'] ?? 1),
                'expires_in' => $data['expires_in'] ?? '7d',
                'metadata' => [
                    'message' => $data['message'] ?? '',
                    'custom_data' => $data['custom_data'] ?? []
                ]
            ];

            // Validate max_uses
            if ($inviteData['max_uses'] < 1 || $inviteData['max_uses'] > 100) {
                $inviteData['max_uses'] = 1;
            }

            // Generate invite token
            $token = $this->inviteModel->create($inviteData);

            // Generate full invite URL
            $baseUrl = $this->getBaseUrl();
            $inviteUrl = $baseUrl . '/register?invite=' . $token;

            return [
                'success' => true,
                'message' => 'Invite link generated successfully',
                'data' => [
                    'token' => $token,
                    'invite_url' => $inviteUrl,
                    'role' => $role,
                    'expires_in' => $inviteData['expires_in'],
                    'max_uses' => $inviteData['max_uses'],
                    'email_restricted' => !empty($inviteData['email'])
                ]
            ];
        } catch (\Exception $e) {
            error_log('Generate invite error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to generate invite link: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate an invite link
     */
    public function validateInvite(string $token): array
    {
        try {
            $validation = $this->inviteModel->validate($token);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['reason']
                ];
            }

            // Get invite details
            $invite = $this->inviteModel->findByToken($token);

            return [
                'success' => true,
                'message' => 'Invite link is valid',
                'data' => [
                    'token' => $token,
                    'inviter_name' => $invite['inviter_name'] ?? '',
                    'role' => $invite['role'] ?? 'user',
                    'email_required' => $validation['email_required'] ?? null,
                    'expires_at' => $invite['expires_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'remaining_uses' => ($invite['max_uses'] - $invite['uses'])
                ]
            ];
        } catch (\Exception $e) {
            error_log('Validate invite error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to validate invite link'
            ];
        }
    }

    /**
     * Get all invites for the current user
     */
    public function getMyInvites(): array
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User not authenticated'
                ];
            }

            $inviterId = $_SESSION['user_id'];
            $invites = $this->inviteModel->getByInviter($inviterId);

            // Format invites for display
            $formattedInvites = array_map(function($invite) {
                $baseUrl = $this->getBaseUrl();
                return [
                    'token' => $invite['token'],
                    'invite_url' => $baseUrl . '/register?invite=' . $invite['token'],
                    'role' => $invite['role'],
                    'email' => $invite['email'] ?? null,
                    'max_uses' => $invite['max_uses'],
                    'uses' => $invite['uses'],
                    'remaining_uses' => $invite['max_uses'] - $invite['uses'],
                    'status' => $invite['status'],
                    'expires_at' => $invite['expires_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'created_at' => $invite['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                    'inviter_name' => $invite['inviter_name'] ?? ''
                ];
            }, $invites);

            return [
                'success' => true,
                'data' => $formattedInvites
            ];
        } catch (\Exception $e) {
            error_log('Get invites error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve invites'
            ];
        }
    }

    /**
     * Revoke an invite link
     */
    public function revokeInvite(string $token): array
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User not authenticated'
                ];
            }

            $inviterId = $_SESSION['user_id'];

            // Check if invite belongs to user
            $invite = $this->inviteModel->findByToken($token);
            if (!$invite) {
                return [
                    'success' => false,
                    'message' => 'Invite link not found'
                ];
            }

            if ($invite['inviter_id'] !== $inviterId) {
                return [
                    'success' => false,
                    'message' => 'You can only revoke your own invite links'
                ];
            }

            $result = $this->inviteModel->revoke($token);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Invite link revoked successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to revoke invite link'
                ];
            }
        } catch (\Exception $e) {
            error_log('Revoke invite error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to revoke invite link'
            ];
        }
    }

    /**
     * Delete an invite link
     */
    public function deleteInvite(string $token): array
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                return [
                    'success' => false,
                    'message' => 'User not authenticated'
                ];
            }

            $inviterId = $_SESSION['user_id'];

            // Check if invite belongs to user
            $invite = $this->inviteModel->findByToken($token);
            if (!$invite) {
                return [
                    'success' => false,
                    'message' => 'Invite link not found'
                ];
            }

            if ($invite['inviter_id'] !== $inviterId) {
                return [
                    'success' => false,
                    'message' => 'You can only delete your own invite links'
                ];
            }

            $result = $this->inviteModel->delete($token);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Invite link deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete invite link'
                ];
            }
        } catch (\Exception $e) {
            error_log('Delete invite error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete invite link'
            ];
        }
    }

    /**
     * Use invite link (called during registration)
     */
    public function useInvite(string $token, string $userId): array
    {
        try {
            // Validate invite first
            $validation = $this->inviteModel->validate($token);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['reason']
                ];
            }

            // Get invite details
            $invite = $this->inviteModel->findByToken($token);

            // Check email restriction
            if (!empty($invite['email'])) {
                $user = $this->userModel->findById($userId);
                if ($user['email'] !== $invite['email']) {
                    return [
                        'success' => false,
                        'message' => 'This invite is restricted to a specific email address'
                    ];
                }
            }

            // Use the invite
            $result = $this->inviteModel->useInvite($token);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Invite used successfully',
                    'data' => [
                        'inviter_id' => $invite['inviter_id'],
                        'role' => $invite['role'],
                        'team_id' => $invite['team_id'] ?? null
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to use invite link'
                ];
            }
        } catch (\Exception $e) {
            error_log('Use invite error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to use invite link'
            ];
        }
    }

    /**
     * Get base URL for invite links
     */
    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
