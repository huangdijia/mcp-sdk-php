<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */
require_once __DIR__ . '/../vendor/autoload.php';

use ModelContextProtocol\SDK\Server\McpServer;
use ModelContextProtocol\SDK\Server\Transport\StdioServerTransport;
use ModelContextProtocol\SDK\Shared\ResourceTemplate;

// Create an MCP server
$server = new McpServer([
    'name' => 'PHP Example Server',
    'version' => '1.0.0',
]);

// Add a simple calculator tool
$server->tool('add', function (array $params) {
    $a = $params['a'] ?? 0;
    $b = $params['b'] ?? 0;

    return [
        'content' => [
            ['type' => 'text', 'text' => (string) ($a + $b)],
        ],
    ];
});

// Add a multiply tool
$server->tool('multiply', function (array $params) {
    $a = $params['a'] ?? 0;
    $b = $params['b'] ?? 0;

    return [
        'content' => [
            ['type' => 'text', 'text' => (string) ($a * $b)],
        ],
    ];
});

// Add a dynamic greeting resource
$server->resource(
    'greeting',
    new ResourceTemplate('greeting://{name}', ['list' => null]),
    function (string $uri, array $params) {
        return [
            'contents' => [[
                'uri' => $uri,
                'text' => "Hello, {$params['name']}!",
            ]],
        ];
    }
);

// Add a time resource
$server->resource(
    'time',
    new ResourceTemplate('time://', ['list' => []]),
    function (string $uri, array $params) {
        return [
            'contents' => [[
                'uri' => $uri,
                'text' => 'Current time: ' . date('Y-m-d H:i:s'),
            ]],
        ];
    }
);

// Set up a callback for when initialization is complete
$server->onInitialized = function () {
    echo "Server initialized and ready to receive requests.\n";
};

// Start receiving messages on stdin and sending messages on stdout
$transport = new StdioServerTransport();
$server->connect($transport);

// This will run until the transport is closed
while (true) {
    // Sleep to avoid high CPU usage
    usleep(100000); // 100ms
}
