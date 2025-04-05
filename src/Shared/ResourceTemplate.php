<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Deeka Wong <huangdijia@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

/**
 * A template for resource URIs.
 */
class ResourceTemplate
{
    /**
     * @var bool whether this resource is listable
     */
    public bool $listable = false;

    /**
     * @var string the resource name
     */
    public string $name = '';

    /**
     * @var string the resource description
     */
    public string $description = '';

    /**
     * @var string the resource MIME type
     */
    public string $mimeType = '';

    /**
     * @var string the URI template
     */
    private string $template;

    /**
     * @var array the template options
     */
    private array $options;

    /**
     * Constructor.
     *
     * @param string $template the URI template
     * @param array $options the template options
     */
    public function __construct(string $template, array $options = [])
    {
        $this->template = $template;
        $this->options = $options;

        // Initialize properties from options
        $this->listable = $options['listable'] ?? false;
        $this->name = $options['name'] ?? '';
        $this->description = $options['description'] ?? '';
        $this->mimeType = $options['mimeType'] ?? '';
    }

    /**
     * Get the URI template.
     *
     * @return string the URI template
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Get the URI template for API clients.
     *
     * @return string the URI template
     */
    public function getUriTemplate(): string
    {
        return $this->template;
    }

    /**
     * Get an example URI based on this template.
     *
     * @return string example URI
     */
    public function getExampleUri(): string
    {
        $uri = $this->template;

        // Replace all placeholder parameters with example values
        $pattern = '/\{([^}]+)\}/';
        if (preg_match_all($pattern, $uri, $matches)) {
            foreach ($matches[1] as $param) {
                $exampleValue = $this->options['examples'][$param] ?? 'example';
                $uri = str_replace('{' . $param . '}', $exampleValue, $uri);
            }
        }

        return $uri;
    }

    /**
     * Check if this template can be listed.
     *
     * @return bool whether this template can be listed
     */
    public function canList(): bool
    {
        return isset($this->options['list']);
    }

    /**
     * Extract parameters from a URI using this template.
     *
     * @param string $uri the URI to extract parameters from
     * @return array the extracted parameters
     */
    public function extractParams(string $uri): array
    {
        $params = [];

        // Convert template to regex pattern
        $pattern = preg_quote($this->template, '/');
        $pattern = str_replace('\{', '(?<', $pattern);
        $pattern = str_replace('\}', '>[^/]+)', $pattern);
        $pattern = '/^' . $pattern . '$/';

        // Extract parameters
        if (preg_match($pattern, $uri, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
        }

        return $params;
    }

    /**
     * Generate a URI from this template with the given parameters.
     *
     * @param array $params the parameters to use
     * @return string the generated URI
     */
    public function generateUri(array $params): string
    {
        $uri = $this->template;

        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        return $uri;
    }
}
