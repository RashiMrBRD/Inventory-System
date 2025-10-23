<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Database;
use Exception;

/**
 * Database Service
 * This class handles all MongoDB database connections and operations
 */
class DatabaseService
{
    private static ?DatabaseService $instance = null;
    private Client $client;
    private Database $database;
    private array $config;

    /**
     * Private constructor to prevent direct instantiation
     * This ensures that we use the singleton pattern
     */
    private function __construct()
    {
        $this->config = require __DIR__ . '/../../config/database.php';
        $this->connect();
    }

    /**
     * Get the singleton instance of DatabaseService
     * 
     * @return DatabaseService
     */
    public static function getInstance(): DatabaseService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to MongoDB database
     * This method creates a connection using the configuration settings
     * 
     * @throws Exception if connection fails
     */
    private function connect(): void
    {
        try {
            $mongoConfig = $this->config['mongodb'];
            $connectionString = $this->buildConnectionString();
            
            $this->client = new Client($connectionString, [], [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array'
                ]
            ]);
            
            $this->database = $this->client->selectDatabase($mongoConfig['database']);
            
            // Test the connection
            $this->database->command(['ping' => 1]);
            
        } catch (Exception $e) {
            throw new Exception("MongoDB connection failed: " . $e->getMessage());
        }
    }

    /**
     * Build MongoDB connection string
     * This method constructs the proper connection string based on configuration
     * 
     * @return string
     */
    private function buildConnectionString(): string
    {
        $mongoConfig = $this->config['mongodb'];
        $host = $mongoConfig['host'];
        $port = $mongoConfig['port'];
        $db   = $mongoConfig['database'];
        
        if (!empty($mongoConfig['username']) && !empty($mongoConfig['password'])) {
            $username = urlencode($mongoConfig['username']);
            $password = urlencode($mongoConfig['password']);
            return "mongodb://{$username}:{$password}@{$host}:{$port}/{$db}?authSource={$db}";
        }
        
        return "mongodb://{$host}:{$port}";
    }

    /**
     * Get the MongoDB database instance
     * 
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Get a specific collection from the database
     * 
     * @param string $collectionName
     * @return \MongoDB\Collection
     */
    public function getCollection(string $collectionName)
    {
        return $this->database->selectCollection($collectionName);
    }

    /**
     * Get collection name from config
     * 
     * @param string $key
     * @return string
     */
    public function getCollectionName(string $key): string
    {
        return $this->config['collections'][$key] ?? $key;
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
