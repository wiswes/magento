<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Tool;

use WisWes\MCP\Model\Dispatch\RouteResolver;
use WisWes\MCP\Model\Dispatch\ServiceContractDispatcher;
use Psr\Log\LoggerInterface;

/**
 * Turns the curated {@see RouteCatalog} into a list of {@see RouteToolDefinition}s
 * by resolving each (route, method) pair against Magento's webapi.xml config and
 * reflecting the target service-contract method into a JSON Schema.
 *
 * Routes that the running Magento install does not actually expose (e.g. a
 * module is disabled) are skipped with a warning instead of crashing the
 * server boot.
 */
class RouteToolBuilder
{
    public function __construct(
        private readonly RouteCatalog $catalog,
        private readonly RouteResolver $resolver,
        private readonly ToolNameMapper $nameMapper,
        private readonly SchemaBuilder $schemaBuilder,
        private readonly ServiceContractDispatcher $dispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return list<RouteToolDefinition> */
    public function build(): array
    {
        $defs = [];
        foreach ($this->catalog->all() as $entry) {
            $route = $entry['route'];
            $method = $entry['method'];

            $resolved = $this->resolver->resolve($route, $method);
            if ($resolved === null) {
                $this->logger->warning(sprintf(
                    '[WisWes_MCP] Skipping %s %s — not found in webapi.xml. Module disabled?',
                    $method,
                    $route
                ));
                continue;
            }

            try {
                $schema = $this->schemaBuilder->build($resolved['class'], $resolved['method']);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[WisWes_MCP] Schema build failed for %s::%s (%s %s): %s',
                    $resolved['class'],
                    $resolved['method'],
                    $method,
                    $route,
                    $e->getMessage()
                ));
                continue;
            }

            $name = $this->nameMapper->map($route, $method);
            $description = $this->describe($route, $method, $resolved);

            $serviceClass  = $resolved['class'];
            $serviceMethod = $resolved['method'];
            $dispatcher = $this->dispatcher;

            $handler = function (array $arguments) use ($dispatcher, $serviceClass, $serviceMethod) {
                return $dispatcher->dispatch($serviceClass, $serviceMethod, $arguments);
            };

            $defs[] = new RouteToolDefinition($name, $description, $schema, $handler);
        }

        return $defs;
    }

    /** @param array{class:class-string,method:string,resources:list<string>} $resolved */
    private function describe(string $route, string $method, array $resolved): string
    {
        $acl = $resolved['resources'] !== []
            ? ' Requires ACL: ' . implode(', ', $resolved['resources']) . '.'
            : ' Anonymous access allowed.';

        return sprintf(
            'Magento REST %s %s — %s::%s.%s',
            $method,
            $route,
            $resolved['class'],
            $resolved['method'],
            $acl
        );
    }
}
