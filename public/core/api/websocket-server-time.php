<?php
/**
 * WebSocket Server for Live Server Time
 * 
 * This script provides a WebSocket endpoint for streaming live server time updates.
 * It uses Ratchet for WebSocket functionality.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ServerTime implements MessageComponentInterface {
    protected $clients;
    private $loop;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "[WebSocket] New connection! ({$conn->resourceId})\n";
        
        // Send current time immediately
        $this->sendTimeToClient($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // No messages expected from client
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "[WebSocket] Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
        echo "[WebSocket] An error has occurred: {$e->getMessage()}\n";
    }

    private function sendTimeToClient($conn) {
        $time = date('H:i:s');
        $message = json_encode([
            'type' => 'time',
            'time' => $time
        ]);
        $conn->send($message);
    }

    public function broadcastTime() {
        $time = date('H:i:s');
        $message = json_encode([
            'type' => 'time',
            'time' => $time
        ]);

        foreach ($this->clients as $client) {
            $client->send($message);
        }
    }
}

// Run the WebSocket server
$server = new ServerTime();
$loop = \React\EventLoop\Factory::create();

// Broadcast time every second
$loop->addPeriodicTimer(1, function() use ($server) {
    $server->broadcastTime();
});

$app = new Ratchet\App('localhost', 8080);
$app->route('/ws/server-time', $server, ['*']);

echo "[WebSocket] Server started on ws://localhost:8080/ws/server-time\n";
$app->run($loop);
