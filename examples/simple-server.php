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

/**
 * MCP Server 示例.
 *
 * 这个例子展示了如何创建一个简单的 MCP 服务器，实现:
 * - 多种工具函数
 * - 不同类型的资源
 * - 事件处理
 */

// 创建 MCP 服务器配置
$serverConfig = [
    'name' => 'PHP Example Server',
    'version' => '1.0.0',
    'description' => '这是一个示例 MCP 服务器，提供计算工具和资源服务',
];

// 创建 MCP 服务器实例
$server = new McpServer($serverConfig);

/*
 * 工具示例部分
 */

// 添加基础计算工具
$server->tool('add', function (array $params) {
    try {
        $a = is_numeric($params['a'] ?? null) ? (float) $params['a'] : 0;
        $b = is_numeric($params['b'] ?? null) ? (float) $params['b'] : 0;
        $result = $a + $b;

        return [
            'content' => [
                ['type' => 'text', 'text' => (string) $result],
            ],
        ];
    } catch (Throwable $e) {
        return [
            'content' => [
                ['type' => 'text', 'text' => '计算错误: ' . $e->getMessage()],
            ],
        ];
    }
}, [
    'description' => '将两个数字相加',
    'parameters' => [
        'a' => ['type' => 'number', 'description' => '第一个数字'],
        'b' => ['type' => 'number', 'description' => '第二个数字'],
    ],
]);

// 添加乘法工具
$server->tool('multiply', function (array $params) {
    try {
        $a = is_numeric($params['a'] ?? null) ? (float) $params['a'] : 0;
        $b = is_numeric($params['b'] ?? null) ? (float) $params['b'] : 0;
        $result = $a * $b;

        return [
            'content' => [
                ['type' => 'text', 'text' => (string) $result],
            ],
        ];
    } catch (Throwable $e) {
        return [
            'content' => [
                ['type' => 'text', 'text' => '计算错误: ' . $e->getMessage()],
            ],
        ];
    }
}, [
    'description' => '将两个数字相乘',
    'parameters' => [
        'a' => ['type' => 'number', 'description' => '第一个数字'],
        'b' => ['type' => 'number', 'description' => '第二个数字'],
    ],
]);

// 添加一个更复杂的字符串处理工具
$server->tool('textProcess', function (array $params) {
    $text = $params['text'] ?? '';
    $operation = $params['operation'] ?? 'none';

    switch ($operation) {
        case 'uppercase':
            $result = strtoupper($text);
            break;
        case 'lowercase':
            $result = strtolower($text);
            break;
        case 'capitalize':
            $result = ucwords($text);
            break;
        case 'reverse':
            $result = strrev($text);
            break;
        default:
            $result = $text;
    }

    return [
        'content' => [
            ['type' => 'text', 'text' => $result],
        ],
    ];
}, [
    'description' => '处理文本字符串',
    'parameters' => [
        'text' => ['type' => 'string', 'description' => '要处理的文本'],
        'operation' => [
            'type' => 'string',
            'description' => '要执行的操作',
            'enum' => ['uppercase', 'lowercase', 'capitalize', 'reverse', 'none'],
        ],
    ],
]);

/*
 * 资源示例部分
 */

// 添加问候资源
$server->resource(
    'greeting',
    new ResourceTemplate('greeting://{name}', ['list' => null]),
    function (string $uri, array $params) {
        $name = $params['name'] ?? '访客';

        return [
            'contents' => [[
                'uri' => $uri,
                'text' => "你好, {$name}! 欢迎使用 MCP 服务示例。",
            ]],
        ];
    }
);

// 添加时间资源
$server->resource(
    'time',
    new ResourceTemplate('time://', ['list' => []]),
    function (string $uri, array $params) {
        $timezone = $params['timezone'] ?? 'Asia/Shanghai';
        $format = $params['format'] ?? 'Y-m-d H:i:s';

        try {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));

            return [
                'contents' => [[
                    'uri' => $uri,
                    'text' => '当前时间: ' . $dateTime->format($format),
                ]],
            ];
        } catch (Exception $e) {
            return [
                'contents' => [[
                    'uri' => $uri,
                    'text' => '时间获取失败: ' . $e->getMessage(),
                ]],
            ];
        }
    }
);

// 添加一个随机数生成资源
$server->resource(
    'random',
    new ResourceTemplate('random://{min}/{max}', ['list' => null]),
    function (string $uri, array $params) {
        $min = isset($params['min']) && is_numeric($params['min']) ? (int) $params['min'] : 1;
        $max = isset($params['max']) && is_numeric($params['max']) ? (int) $params['max'] : 100;

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        $random = rand($min, $max);

        return [
            'contents' => [[
                'uri' => $uri,
                'text' => "生成的随机数: {$random}",
            ]],
        ];
    }
);

/*
 * 事件处理部分
 */

// 设置服务器初始化完成的回调
$server->onInitialized = function () use ($serverConfig) {
    echo "服务器 \"{$serverConfig['name']}\" v{$serverConfig['version']} 已初始化并准备接收请求\n";
    echo '启动时间: ' . date('Y-m-d H:i:s') . "\n";
    echo "------------------------------------------------------\n";
};

// 设置请求处理回调
$server->onRequest = function (string $requestId, string $method) {
    echo "收到请求: ID={$requestId}, 方法={$method}\n";
};

// 设置请求完成回调
$server->onResponse = function (string $requestId, string $method) {
    echo "请求已处理: ID={$requestId}, 方法={$method}\n";
    echo "------------------------------------------------------\n";
};

/*
 * 启动服务器
 */
echo "正在启动 MCP 服务器...\n";

// 创建标准输入/输出传输层
$transport = new StdioServerTransport();

// 连接服务器与传输层
$server->connect($transport);

// 保持运行直到传输层关闭
while (true) {
    // 休眠以避免高 CPU 使用率
    usleep(100000); // 100ms
}
