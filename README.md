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
composer require modelcontextprotocol/sdk
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