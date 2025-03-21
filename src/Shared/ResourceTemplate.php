<?php

declare(strict_types=1);
/**
 * This file is part of huangdijia/mcp-php-sdk.
 *
 * @link     https://github.com/huangdijia/mcp-php-sdk
 * @document https://github.com/huangdijia/mcp-php-sdk/blob/main/README.md
 * @contact  Your name <your-mail@gmail.com>
 */

namespace ModelContextProtocol\SDK\Shared;

/**
 * A template for resource URIs.
 */
class ResourceTemplate
{
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
