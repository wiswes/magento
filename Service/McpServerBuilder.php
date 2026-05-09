<?php
declare(strict_types=1);

namespace WisWes\MCP\Service;

use WisWes\MCP\Model\Auth\RequestContext;
use WisWes\MCP\Model\Tool\RouteToolBuilder;
use WisWes\MCP\Model\Transport\BearerTokenCapturingTransport;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PhpMcp\Server\Server;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Boots the php-mcp/server instance, registers every tool exposed by this
 * module, and constructs a transport. Two sources of tools are merged:
 *
 *   1. Auto-generated route tools from {@see RouteToolBuilder} (1:1 with the
 *      curated REST endpoints in {@see \WisWes\MCP\Model\Tool\RouteCatalog}).
 *   2. Hand-written tools registered via di.xml under `customTools` — for
 *      cases where the LLM ergonomics warrant a different shape than the raw
 *      REST endpoint (e.g. an MCP-friendly product search that hides
 *      Magento's verbose searchCriteria DSL).
 *
 * The store-config glob `wiswes_mcp/tools/include` filters the merged set so
 * an admin can scope what an LLM session sees without redeploying.
 */
class McpServerBuilder
{
    private const XML_PATH_INCLUDE_GLOB = 'wiswes_mcp/tools/include';

    /**
     * @param object[] $customTools  Hand-written tool objects with #[McpTool] methods
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RouteToolBuilder $routeToolBuilder,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestContext $requestContext,
        private readonly MagentoMcpContainer $container,
        private readonly ?CacheInterface $stateCache = null,
        private readonly array $customTools = [],
    ) {
    }

    public function buildTransport(string $host, int $port, string $prefix): BearerTokenCapturingTransport
    {
        return new BearerTokenCapturingTransport(
            requestContext: $this->requestContext,
            host: $host,
            port: $port,
            mcpPath: '/' . ltrim($prefix, '/'),
        );
    }

    public function buildServer(string $name, string $version): Server
    {
        $builder = Server::make()
            ->withServerInfo(name: $name, version: $version)
            ->withLogger($this->logger)
            ->withContainer($this->container);

        if ($this->stateCache !== null) {
            $builder->withCache($this->stateCache);
        }

        $glob = (string) ($this->scopeConfig->getValue(self::XML_PATH_INCLUDE_GLOB) ?: '*');

        // Auto-generated route tools are temporarily disabled. php-mcp/server
        // 2.0 dropped the Closure-handler overload of withTool() in favour of
        // [class, method] handlers whose JSON Schema is derived from method
        // reflection. RouteToolBuilder produces Closures with a separately
        // computed schema (SchemaBuilder), which the new API can no longer
        // ingest — passing them used to crash MCP initialise with
        // "Argument #1 ($handler) must be of type array|string, Closure
        // given". Until route tools are reshaped into per-route classes (or
        // a Closure-friendly registration path is added back to
        // php-mcp/server), we skip them and rely on the curated `customTools`
        // below — those are real classes with #[McpTool]-annotated methods
        // and work unchanged on 2.0.
        $registered = 0;

        foreach ($this->customTools as $instance) {
            // Hand php-mcp the already-wired instance (via the container)
            // so its [class, method] handler resolution returns this object
            // instead of asking BasicContainer to auto-wire Magento services.
            $this->container->set($instance::class, $instance);
            $registered += $this->registerCustomToolMethods($builder, $instance, $glob);
        }

        $this->logger->info(sprintf('[WisWes_MCP] Registered %d tools.', $registered));
        return $builder->build();
    }

    private function registerCustomToolMethods(\PhpMcp\Server\ServerBuilder $builder, object $instance, string $glob): int
    {
        $count = 0;
        $reflection = new \ReflectionClass($instance);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attrs = $method->getAttributes(\PhpMcp\Server\Attributes\McpTool::class);
            if ($attrs === []) {
                continue;
            }
            $mcpTool = $attrs[0]->newInstance();
            if (!$this->matches($mcpTool->name, $glob)) {
                continue;
            }
            $builder->withTool(
                [$instance::class, $method->getName()],
                name: $mcpTool->name,
                description: $mcpTool->description,
            );
            $count++;
        }
        return $count;
    }

    private function matches(string $toolName, string $globList): bool
    {
        $globs = array_filter(array_map('trim', explode(',', $globList)));
        if ($globs === [] || in_array('*', $globs, true)) {
            return true;
        }
        foreach ($globs as $glob) {
            $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($glob, '/')) . '$/';
            if (preg_match($regex, $toolName)) {
                return true;
            }
        }
        return false;
    }
}
