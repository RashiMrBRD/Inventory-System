<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

/**
 * User Model
 * This class handles all user-related database operations
 */
class User
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('users');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Check if there is at least one user in the collection
     *
     * @return bool
     */
    public function hasAnyUser(): bool
    {
        try {
            $user = $this->collection->findOne([]);
            return $user !== null;
        } catch (\Exception $e) {
            // Fail closed: assume users exist to avoid exposing setup unintentionally
            return true;
        }
    }

    /**
     * Find a user by username
     * 
     * @param string $username
     * @return array|null
     */
    public function findByUsername(string $username): ?array
    {
        $user = $this->collection->findOne(['username' => $username]);
        return $user ? (array)$user : null;
    }

    /**
     * Find a user by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        try {
            $user = $this->collection->findOne(['_id' => new ObjectId($id)]);
            return $user ? (array)$user : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify user credentials
     * This method checks if the username and password match
     * 
     * @param string $username
     * @param string $password
     * @return array|null Returns user data if credentials are valid, null otherwise
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);
        
        if ($user && isset($user['password'])) {
            // Check if password is hashed
            if (password_verify($password, $user['password']) || $user['password'] === $password) {
                unset($user['password']); // Remove password from returned data
                return $user;
            }
        }
        
        return null;
    }

    /**
     * Create a new user
     * 
     * @param array $userData
     * @return string|null Returns the ID of the created user, or null on failure
     */
    public function create(array $userData): ?string
    {
        try {
            // Hash the password if it's not already hashed
            if (isset($userData['password']) && strlen($userData['password']) < 60) {
                $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            }
            
            $userData['created_at'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->insertOne($userData);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update user information
     * 
     * @param string $id
     * @param array $userData
     * @return bool
     */
    public function update(string $id, array $userData): bool
    {
        try {
            // Hash password if being updated
            if (isset($userData['password']) && strlen($userData['password']) < 60) {
                $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            }
            
            $userData['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $userData]
            );
            
            // Return true if document was matched (even if not modified)
            return $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            error_log('User update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users
     * 
     * @return array
     */
    public function getAll(): array
    {
        $users = $this->collection->find([], ['projection' => ['password' => 0]])->toArray();
        return array_map(function($user) {
            return (array)$user;
        }, $users);
    }

    /**
     * Delete a user
     * 
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update user - alias for update() method
     * 
     * @param string $id
     * @param array $userData
     * @return bool
     */
    public function updateUser(string $id, array $userData): bool
    {
        return $this->update($id, $userData);
    }
}
