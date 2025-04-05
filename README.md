# MCP PHP SDK

## Table of Contents
- [Overview](#overview)
- [Installation](#installation)
- [Quickstart](#quickstart)
- [What is MCP?](#what-is-mcp)
- [Core Concepts](#core-concepts)
  - [Server](#server)
  - [Resources](#resources)
  - [Tools](#tools)
  - [Prompts](#prompts)
- [Running Your Server](#running-your-server)
  - [stdio](#stdio)
  - [HTTP with SSE](#http-with-sse)
  - [Testing and Debugging](#testing-and-debugging)
- [Examples](#examples)
  - [Echo Server](#echo-server)
  - [SQLite Explorer](#sqlite-explorer)
- [Advanced Usage](#advanced-usage)
  - [Low-Level Server](#low-level-server)
  - [Writing MCP Clients](#writing-mcp-clients)
  - [Server Capabilities](#server-capabilities)

## Overview

The Model Context Protocol allows applications to provide context for LLMs in a standardized way, separating the concerns of providing context from the actual LLM interaction. This PHP SDK implements the full MCP specification, making it easy to:

- Build MCP clients that can connect to any MCP server
- Create MCP servers that expose resources, prompts and tools
- Use standard transports like stdio and SSE
- Handle all MCP protocol messages and lifecycle events

## Installation

```bash
composer require huangdijia/mcp-sdk-php
```

## Quick Start

Let's create a simple MCP server that exposes a calculator tool and some data:

```php
<?php

use ModelContextProtocol\SDK\Server\McpServer;
use ModelContextProtocol\SDK\Server\Transport\StdioServerTransport;
use ModelContextProtocol\SDK\Shared\ResourceTemplate;

// Create an MCP server
$server = new McpServer([
    'name' => 'Demo',
    'version' => '1.0.0'
]);

// Add an addition tool
$server->tool('add', function (array $params) {
    $a = $params['a'] ?? 0;
    $b = $params['b'] ?? 0;
    
    return [
        'content' => [
            ['type' => 'text', 'text' => (string)($a + $b)]
        ]
    ];
});

// 示例：添加更多计算工具
$server->tool('multiply', function (array $params) {
    $a = $params['a'] ?? 0;
    $b = $params['b'] ?? 0;
    
    return [
        'content' => [
            ['type' => 'text', 'text' => (string)($a * $b)]
        ]
    ];
}, '将两个数字相乘', [
    'a' => ['type' => 'number', 'description' => '第一个数字'],
    'b' => ['type' => 'number', 'description' => '第二个数字'],
]);

// 示例：添加文本处理工具
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
}, '处理文本字符串', [
    'text' => ['type' => 'string', 'description' => '要处理的文本'],
    'operation' => [
        'type' => 'string',
        'description' => '要执行的操作',
        'enum' => ['uppercase', 'lowercase', 'capitalize', 'reverse', 'none'],
    ],
]);

// Add a dynamic greeting resource
$server->resource(
    'greeting',
    new ResourceTemplate('greeting://{name}', ['list' => null]),
    function (string $uri, array $params) {
        return [
            'contents' => [[
                'uri' => $uri,
                'text' => "Hello, {$params['name']}!"
            ]]
        ];
    }
);

// 示例：添加更多资源类型
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

// Start receiving messages on stdin and sending messages on stdout
$transport = new StdioServerTransport();
$server->connect($transport);
```

## What is MCP?

The [Model Context Protocol (MCP)](https://modelcontextprotocol.io) lets you build servers that expose data and functionality to LLM applications in a secure, standardized way. Think of it like a web API, but specifically designed for LLM interactions. MCP servers can:

- Expose data through **Resources** (think of these sort of like GET endpoints; they are used to load information into the LLM's context)
- Provide functionality through **Tools** (sort of like POST endpoints; they are used to execute code or otherwise produce a side effect)
- Define interaction patterns through **Prompts** (reusable templates for LLM interactions)
- And more!

### Available Tools

MCP PHP SDK提供了多种内置工具，可以在您的MCP服务器中使用：

1. **基础计算工具**
   - `add` - 将两个数字相加
   - `multiply` - 将两个数字相乘

2. **文本处理工具**
   - `textProcess` - 可执行各种文本操作，包括：
     - 转为大写 (uppercase)
     - 转为小写 (lowercase)
     - 首字母大写 (capitalize)
     - 文本反转 (reverse)

### Available Resources

SDK支持多种资源类型，可以通过URI方案访问：

1. **greeting** - 个性化问候
   - URI模板：`greeting://{name}`
   - 示例：`greeting://world` → "Hello, world!"

2. **time** - 时间信息
   - URI模式：`time://`
   - 提供当前时间信息，支持时区和格式自定义

3. **random** - 随机数生成
   - URI模式：`random://{min}/{max}`
   - 示例：`random://1/100` → 生成1到100之间的随机数
