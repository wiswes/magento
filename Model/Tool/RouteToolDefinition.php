<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Tool;

/**
 * Immutable description of one MCP tool generated from a Magento REST route.
 *
 * Carries everything {@see \WisWes\MCP\Service\McpServerBuilder} needs to
 * register the tool with php-mcp/server: a name, a description, an input
 * schema, and a callable that the dispatcher will invoke when the tool fires.
 */
class RouteToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema  JSON Schema for the tool input
     * @param callable(array<string, mixed>): mixed $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly mixed $handler,
    ) {}
}
