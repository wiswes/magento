<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Tool;

/**
 * Maps a Magento V1 REST route + HTTP method to an MCP tool name.
 *
 * Convention (per the project brief):
 *   /V1/products            GET    -> product:list
 *   /V1/products/:sku       GET    -> product:get
 *   /V1/products            POST   -> product:create
 *   /V1/products/:sku       PUT    -> product:update
 *   /V1/products/:sku       DELETE -> product:delete
 *   /V1/products/:sku/media GET    -> product:media:list
 *   /V1/carts/mine/items    POST   -> cart:item:add
 *   /V1/orders/:id/cancel   POST   -> order:cancel
 *
 * Namespaces are singular (`product`, not `products`). Sub-resource segments
 * are joined with `:`. Verbs are inferred from HTTP method + whether the URL
 * ends with a path parameter or a literal action segment.
 */
class ToolNameMapper
{
    /** Irregular plurals not handled by the simple suffix rules. */
    private const IRREGULAR_PLURALS = [
        'taxes'         => 'tax',
        'categories'    => 'category',
        'addresses'     => 'address',
        'classes'       => 'class',
        'inventories'   => 'inventory',
        'cmsPage'       => 'cms-page',
        'cmsBlock'      => 'cms-block',
        'cmspage'       => 'cms-page',
        'cmsblock'      => 'cms-block',
        'creditmemo'    => 'credit-memo',
        'storeViews'    => 'store-view',
        'storeGroups'   => 'store-group',
        'websites'      => 'website',
        'storeConfigs'  => 'store-config',
        'customerGroups'=> 'customer-group',
    ];

    /** Action-segment overrides — when the trailing literal IS the verb. */
    private const ACTION_VERBS = [
        'cancel', 'hold', 'unhold', 'ship', 'invoice', 'refund', 'comments',
        'totals', 'estimate-shipping-methods', 'shipping-information',
        'payment-information', 'payment-methods', 'billing-address', 'order',
        'media', 'search',
    ];

    public function map(string $route, string $httpMethod): string
    {
        $segments = $this->segments($route);
        $method = strtoupper($httpMethod);

        if ($segments === []) {
            return 'magento:root';
        }

        $namespace = $this->normalize(array_shift($segments));

        // Walk remaining segments, separating literals from path params.
        $literals = [];
        $endsWithParam = false;
        foreach ($segments as $seg) {
            if (str_starts_with($seg, ':')) {
                $endsWithParam = true;
                continue;
            }
            $endsWithParam = false;
            $literals[] = $this->normalize($seg);
        }

        $verb = $this->verb($method, $literals, $endsWithParam);

        // If the trailing literal is itself an action verb (e.g. /orders/:id/cancel),
        // it already serves as the verb — don't double-up.
        if ($literals !== [] && in_array(end($literals), self::ACTION_VERBS, true)) {
            $verb = array_pop($literals);
            // Re-derive verb for collection-style action verbs.
            if ($verb === 'search') {
                $verb = 'list';
            }
        }

        $parts = array_filter([$namespace, ...$literals, $verb], static fn ($p) => $p !== '');
        return implode(':', $parts);
    }

    /** @return list<string> */
    private function segments(string $route): array
    {
        $route = trim($route, '/');
        if ($route === '') {
            return [];
        }
        $parts = explode('/', $route);
        // Drop the leading "V1" version segment.
        if (($parts[0] ?? null) === 'V1') {
            array_shift($parts);
        }
        return array_values($parts);
    }

    private function normalize(string $segment): string
    {
        if (isset(self::IRREGULAR_PLURALS[$segment])) {
            return self::IRREGULAR_PLURALS[$segment];
        }

        $singular = $this->singularize($segment);

        // camelCase / PascalCase -> kebab-case
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $singular) ?? $singular;
        return strtolower($kebab);
    }

    private function singularize(string $word): string
    {
        if (preg_match('/ies$/', $word)) {
            return preg_replace('/ies$/', 'y', $word) ?? $word;
        }
        if (preg_match('/sses$/', $word)) {
            return preg_replace('/es$/', '', $word) ?? $word;
        }
        if (preg_match('/[^s]s$/', $word)) {
            return substr($word, 0, -1);
        }
        return $word;
    }

    /** @param list<string> $literals */
    private function verb(string $method, array $literals, bool $endsWithParam): string
    {
        return match ($method) {
            'GET'    => $endsWithParam || $literals !== [] ? 'get' : 'list',
            'POST'   => $endsWithParam ? 'create' : ($literals !== [] ? 'add' : 'create'),
            'PUT'    => 'update',
            'DELETE' => 'delete',
            default  => strtolower($method),
        };
    }
}
