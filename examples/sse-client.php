<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */
require_once __DIR__ . '/../vendor/autoload.php';

use ModelContextProtocol\SDK\Client\Client;
use ModelContextProtocol\SDK\Client\Transport\SseClientTransport;

// Create an SSE transport
$transport = new SseClientTransport('http://localhost:8080/sse');

// Set up callbacks
$transport->setOnMessage(function (string $message) {
    echo "Received message: {$message}\n";
});

$transport->setOnError(function (Exception $error) {
    echo "Error: {$error->getMessage()}\n";
});

$transport->setOnClose(function () {
    echo "Connection closed\n";
});

// Create an MCP client
$client = new Client([
    'name' => 'PHP Example Client',
    'version' => '1.0.0',
]);

// Connect the client to the transport
$client->connect($transport);

// Example: Call a tool
$result = $client->callTool('add', ['a' => 5, 'b' => 3]);

// Keep the script running to receive SSE events
while (true) {
    // Process any available SSE events
    $transport->processAvailableData();

    // Sleep to avoid high CPU usage
    usleep(100000); // 100ms

    // You can add a condition to break the loop
    // For example, after a certain time or user input
    // Uncomment the following lines to exit after 10 seconds
    // static $startTime = null;
    // if ($startTime === null) $startTime = time();
    // if (time() - $startTime > 10) break;
}
