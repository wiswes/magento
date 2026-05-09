<?php
declare(strict_types=1);

namespace WisWes\MCP\Service;

use Magento\Framework\ObjectManagerInterface;
use PhpMcp\Server\Defaults\ContainerException;
use PhpMcp\Server\Defaults\NotFoundException;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 container that bridges php-mcp/server's handler resolution into
 * Magento's ObjectManager.
 *
 * php-mcp/server registers tool handlers as [class-string, method] pairs and
 * resolves the class via its container at call time. Its default
 * BasicContainer does pure reflection auto-wiring, which can't construct
 * Magento services (interfaces with preferences, virtual types, plugin
 * chains — knowledge that lives only in ObjectManager). Without this bridge
 * every tool call fails with "'Magento\Framework\ObjectManagerInterface' is
 * not instantiable" before our tool method even runs.
 *
 * Tool objects already wired by Magento DI (`customTools` in di.xml) are
 * pre-registered via {@see set()} so php-mcp gets the same instance back
 * without ObjectManager being asked to re-resolve them.
 */
class MagentoMcpContainer implements ContainerInterface
{
    /** @var array<string, object> Pre-registered instances keyed by FQCN */
    private array $instances = [];

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
    ) {
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!class_exists($id) && !interface_exists($id)) {
            throw new NotFoundException("Class, interface, or entry '{$id}' not found.");
        }
        try {
            return $this->objectManager->get($id);
        } catch (\Throwable $e) {
            throw new ContainerException(
                "Magento ObjectManager failed to resolve '{$id}': " . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || class_exists($id) || interface_exists($id);
    }
}
