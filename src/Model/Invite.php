<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Invite Model
 * Manages invite links for team member invitations
 */
class Invite
{
    private Collection $collection;
    private $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
        $collectionName = $this->db->getCollectionName('invites');
        $this->collection = $this->db->getCollection($collectionName);
    }

    /**
     * Create a new invite link
     */
    public function create(array $data): string
    {
        try {
            $token = $this->generateToken();

            $invite = [
                'token' => $token,
                'inviter_id' => $data['inviter_id'],
                'inviter_name' => $data['inviter_name'] ?? '',
                'inviter_email' => $data['inviter_email'] ?? '',
                'team_id' => $data['team_id'] ?? null,
                'role' => $data['role'] ?? 'user', // user, manager, viewer
                'email' => $data['email'] ?? null, // Optional: restrict to specific email
                'max_uses' => (int)($data['max_uses'] ?? 1),
                'uses' => 0,
                'expires_at' => $this->calculateExpiry($data['expires_in'] ?? '7d'),
                'created_at' => new UTCDateTime(),
                'updated_at' => new UTCDateTime(),
                'status' => 'active', // active, used, expired, revoked
                'metadata' => $data['metadata'] ?? []
            ];

            $result = $this->collection->insertOne($invite);
            
            if (!$result->getInsertedId()) {
                throw new \Exception('Failed to insert invite document');
            }
            
            return $token;
        } catch (\Exception $e) {
            error_log('Invite create error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Find invite by token
     */
    public function findByToken(string $token): ?array
    {
        $invite = $this->collection->findOne(['token' => $token]);
        return $invite ? (array)$invite : null;
    }

    /**
     * Validate invite link
     */
    public function validate(string $token): array
    {
        $invite = $this->findByToken($token);

        if (!$invite) {
            return ['valid' => false, 'reason' => 'Invite link not found'];
        }

        $expiresAt = null;
        if (!empty($invite['expires_at']) && $invite['expires_at'] instanceof UTCDateTime) {
            $expiresAt = $invite['expires_at']->toDateTime();
        }

        // Check if expired
        if ($expiresAt && $expiresAt->getTimestamp() <= time()) {
            $this->updateStatus($invite['_id'], 'expired');
            return ['valid' => false, 'reason' => 'Invite link has expired'];
        }

        // Check if revoked
        if ($invite['status'] === 'revoked') {
            return ['valid' => false, 'reason' => 'Invite link has been revoked'];
        }

        // Check if max uses reached
        $maxUses = isset($invite['max_uses']) ? (int)$invite['max_uses'] : 1;
        $uses = isset($invite['uses']) ? (int)$invite['uses'] : 0;
        if ($maxUses > 0 && $uses >= $maxUses) {
            $this->updateStatus($invite['_id'], 'used');
            return ['valid' => false, 'reason' => 'Invite link has reached maximum uses'];
        }

        // Check email restriction
        if (!empty($invite['email'])) {
            return ['valid' => true, 'email_required' => $invite['email']];
        }

        return ['valid' => true];
    }

    /**
     * Use invite link (increment uses)
     */
    public function useInvite(string $token): bool
    {
        $invite = $this->findByToken($token);
        if (!$invite) {
            return false;
        }

        $maxUses = isset($invite['max_uses']) ? (int)$invite['max_uses'] : 1;
        $uses = isset($invite['uses']) ? (int)$invite['uses'] : 0;
        $nextUses = $uses + 1;
        $nextStatus = ($maxUses > 0 && $nextUses >= $maxUses) ? 'used' : 'active';

        $result = $this->collection->updateOne(
            ['_id' => $invite['_id']],
            [
                '$inc' => ['uses' => 1],
                '$set' => [
                    'updated_at' => new UTCDateTime(),
                    'status' => $nextStatus
                ]
            ]
        );

        return $result->getModifiedCount() > 0;
    }

    /**
     * Revoke invite link
     */
    public function revoke(string $token): bool
    {
        $result = $this->collection->updateOne(
            ['token' => $token],
            ['$set' => ['status' => 'revoked', 'updated_at' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }

    /**
     * Get all invites for a user
     */
    public function getByInviter(string $inviterId): array
    {
        $invites = $this->collection->find(
            ['inviter_id' => $inviterId],
            ['sort' => ['created_at' => -1]]
        )->toArray();
        return array_map(fn($i) => (array)$i, $invites);
    }

    /**
     * Delete invite
     */
    public function delete(string $token): bool
    {
        $result = $this->collection->deleteOne(['token' => $token]);
        return $result->getDeletedCount() > 0;
    }

    /**
     * Update status
     */
    private function updateStatus(ObjectId $id, string $status): void
    {
        $this->collection->updateOne(
            ['_id' => $id],
            ['$set' => ['status' => $status, 'updated_at' => new UTCDateTime()]]
        );
    }

    /**
     * Generate secure token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Calculate expiry date
     */
    private function calculateExpiry(string $duration): UTCDateTime
    {
        $now = time();
        $multipliers = [
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000
        ];

        $seconds = $multipliers[$duration] ?? 604800; // Default 7 days
        return new UTCDateTime(($now + $seconds) * 1000);
    }
}
