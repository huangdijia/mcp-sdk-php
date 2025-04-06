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
     * @var bool whether this resource supports subscription
     */
    public bool $subscribable = false;

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
        $this->subscribable = $options['subscribable'] ?? false;
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
     * Check if a URI matches this template's format.
     *
     * @param string $uri the URI to check
     * @return bool whether the URI matches the template format
     */
    public function matchesUri(string $uri): bool
    {
        $pattern = '/\{([^}]+)\}/'; // Match {param} placeholders

        // Create a regex pattern from the template
        $regexPattern = preg_quote($this->template, '/');
        $regexPattern = preg_replace($pattern, '([^/]+)', $regexPattern);
        $regexPattern = '/^' . $regexPattern . '$/'; // Anchor to start and end

        return (bool) preg_match($regexPattern, $uri);
    }

    /**
     * Extract parameters from a URI based on this template.
     *
     * @param string $uri the URI to extract parameters from
     * @return array the extracted parameters
     */
    public function extractParams(string $uri): array
    {
        $params = [];
        $pattern = '/\{([^}]+)\}/'; // Match {param} placeholders

        // Create a regex pattern from the template
        $regexPattern = preg_quote($this->template, '/');
        $regexPattern = preg_replace($pattern, '([^/]+)', $regexPattern);
        $regexPattern = '/^' . $regexPattern . '$/'; // Anchor to start and end

        // Extract parameter names from the template
        preg_match_all($pattern, $this->template, $matches);
        $paramNames = $matches[1];

        // Extract parameter values from the URI
        if (preg_match($regexPattern, $uri, $matches)) {
            array_shift($matches); // Remove the full match
            $params = array_combine($paramNames, $matches);
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
