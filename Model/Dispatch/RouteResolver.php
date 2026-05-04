<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Dispatch;

use Magento\Webapi\Model\Config as WebapiConfig;
use Magento\Webapi\Model\Config\Converter;

/**
 * Looks up a Magento V1 REST route in the parsed webapi.xml config and returns
 * its concrete service-contract method ({class, method, parameters, acl}).
 *
 * Wraps Magento\Webapi\Model\Config — the same source of truth Magento itself
 * uses to dispatch /rest/V1 requests, so MCP tool dispatch stays in lockstep
 * with REST behavior across module versions.
 */
class RouteResolver
{
    /** @var array<string, array<string, array>>|null */
    private ?array $byRoute = null;

    public function __construct(
        private readonly WebapiConfig $webapiConfig,
    ) {}

    /**
     * @return array{
     *     class: class-string,
     *     method: string,
     *     resources: list<string>,
     *     parameters: array<string, array>
     * }|null
     */
    public function resolve(string $route, string $httpMethod): ?array
    {
        $this->index();
        $entry = $this->byRoute[$route][strtoupper($httpMethod)] ?? null;
        if ($entry === null) {
            return null;
        }

        /** @var array $serviceData */
        $serviceData = $entry[Converter::KEY_SERVICE] ?? [];
        $class  = $serviceData[Converter::KEY_SERVICE_CLASS] ?? null;
        $method = $serviceData[Converter::KEY_SERVICE_METHOD] ?? null;
        if (!$class || !$method) {
            return null;
        }

        return [
            'class'      => $class,
            'method'     => $method,
            'resources'  => $entry[Converter::KEY_ACL_RESOURCES] ?? [],
            'parameters' => $entry['parameters'] ?? [],
        ];
    }

    private function index(): void
    {
        if ($this->byRoute !== null) {
            return;
        }

        $services = $this->webapiConfig->getServices();
        $routes = $services[Converter::KEY_ROUTES] ?? [];

        $indexed = [];
        foreach ($routes as $url => $methods) {
            foreach ($methods as $method => $entry) {
                $indexed[$url][strtoupper($method)] = $entry;
            }
        }
        $this->byRoute = $indexed;
    }
}
