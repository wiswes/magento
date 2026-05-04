<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Tool;

/**
 * Curated whitelist of Magento V1 REST endpoints exposed as MCP tools.
 *
 * Scope target: ~60% of "main" REST surface — catalog, sales, customer, quote,
 * cms, store. Each entry is the canonical (route, http_method) pair from a
 * Magento module's webapi.xml. The {@see RouteToolBuilder} resolves each one
 * back into its concrete service-contract method via Magento\Webapi\Model\Config
 * at server startup, so no service-class strings are duplicated here.
 *
 * Add or remove entries to retune coverage. The store config glob
 * `wiswes_mcp/tools/include` filters this list further at runtime.
 *
 * @phpstan-type Route array{route: string, method: string}
 */
class RouteCatalog
{
    /** @return list<Route> */
    public function all(): array
    {
        return array_merge(
            $this->catalog(),
            $this->inventory(),
            $this->customer(),
            $this->cart(),
            $this->checkout(),
            $this->sales(),
            $this->cms(),
            $this->store(),
            $this->tax(),
        );
    }

    /** @return list<Route> */
    private function catalog(): array
    {
        return [
            ['route' => '/V1/products',                            'method' => 'GET'],
            ['route' => '/V1/products',                            'method' => 'POST'],
            ['route' => '/V1/products/:sku',                       'method' => 'GET'],
            ['route' => '/V1/products/:sku',                       'method' => 'PUT'],
            ['route' => '/V1/products/:sku',                       'method' => 'DELETE'],
            ['route' => '/V1/products/:sku/media',                 'method' => 'GET'],
            ['route' => '/V1/products/:sku/media',                 'method' => 'POST'],
            ['route' => '/V1/products/attributes',                 'method' => 'GET'],
            ['route' => '/V1/products/attributes/:attributeCode',  'method' => 'GET'],
            ['route' => '/V1/products/attribute-sets/sets/list',   'method' => 'GET'],
            ['route' => '/V1/categories',                          'method' => 'GET'],
            ['route' => '/V1/categories',                          'method' => 'POST'],
            ['route' => '/V1/categories/:categoryId',              'method' => 'GET'],
            ['route' => '/V1/categories/:categoryId',              'method' => 'PUT'],
            ['route' => '/V1/categories/:categoryId',              'method' => 'DELETE'],
            ['route' => '/V1/categories/:categoryId/products',     'method' => 'GET'],
        ];
    }

    /** @return list<Route> */
    private function inventory(): array
    {
        return [
            ['route' => '/V1/stockItems/:productSku',          'method' => 'GET'],
            ['route' => '/V1/products/:productSku/stockItems/:itemId', 'method' => 'PUT'],
        ];
    }

    /** @return list<Route> */
    private function customer(): array
    {
        return [
            ['route' => '/V1/customers/search',     'method' => 'GET'],
            ['route' => '/V1/customers',            'method' => 'POST'],
            ['route' => '/V1/customers/:customerId','method' => 'GET'],
            ['route' => '/V1/customers/:customerId','method' => 'PUT'],
            ['route' => '/V1/customers/:customerId','method' => 'DELETE'],
            ['route' => '/V1/customers/me',         'method' => 'GET'],
            ['route' => '/V1/customers/me',         'method' => 'PUT'],
            ['route' => '/V1/customerGroups/:id',   'method' => 'GET'],
        ];
    }

    /** @return list<Route> */
    private function cart(): array
    {
        return [
            ['route' => '/V1/carts/mine',                        'method' => 'POST'],
            ['route' => '/V1/carts/mine',                        'method' => 'GET'],
            ['route' => '/V1/carts/mine/items',                  'method' => 'GET'],
            ['route' => '/V1/carts/mine/items',                  'method' => 'POST'],
            ['route' => '/V1/carts/mine/items/:itemId',          'method' => 'PUT'],
            ['route' => '/V1/carts/mine/items/:itemId',          'method' => 'DELETE'],
            ['route' => '/V1/carts/mine/totals',                 'method' => 'GET'],
            ['route' => '/V1/carts/mine/coupons/:couponCode',    'method' => 'PUT'],
            ['route' => '/V1/carts/mine/coupons',                'method' => 'DELETE'],
        ];
    }

    /** @return list<Route> */
    private function checkout(): array
    {
        return [
            ['route' => '/V1/carts/mine/billing-address',                'method' => 'POST'],
            ['route' => '/V1/carts/mine/billing-address',                'method' => 'GET'],
            ['route' => '/V1/carts/mine/shipping-information',           'method' => 'POST'],
            ['route' => '/V1/carts/mine/estimate-shipping-methods',      'method' => 'POST'],
            ['route' => '/V1/carts/mine/payment-information',            'method' => 'POST'],
            ['route' => '/V1/carts/mine/payment-methods',                'method' => 'GET'],
            ['route' => '/V1/carts/mine/order',                          'method' => 'PUT'],
        ];
    }

    /** @return list<Route> */
    private function sales(): array
    {
        return [
            ['route' => '/V1/orders',                'method' => 'GET'],
            ['route' => '/V1/orders/:id',            'method' => 'GET'],
            ['route' => '/V1/orders/:id/cancel',     'method' => 'POST'],
            ['route' => '/V1/orders/:id/hold',       'method' => 'POST'],
            ['route' => '/V1/orders/:id/unhold',     'method' => 'POST'],
            ['route' => '/V1/orders/:id/comments',   'method' => 'GET'],
            ['route' => '/V1/orders/:id/comments',   'method' => 'POST'],
            ['route' => '/V1/invoices',              'method' => 'GET'],
            ['route' => '/V1/invoices/:id',          'method' => 'GET'],
            ['route' => '/V1/shipments',             'method' => 'GET'],
            ['route' => '/V1/shipments/:id',         'method' => 'GET'],
            ['route' => '/V1/creditmemo',            'method' => 'GET'],
            ['route' => '/V1/creditmemo/:id',        'method' => 'GET'],
        ];
    }

    /** @return list<Route> */
    private function cms(): array
    {
        return [
            ['route' => '/V1/cmsPage/search',  'method' => 'GET'],
            ['route' => '/V1/cmsPage/:id',     'method' => 'GET'],
            ['route' => '/V1/cmsBlock/search', 'method' => 'GET'],
            ['route' => '/V1/cmsBlock/:id',    'method' => 'GET'],
        ];
    }

    /** @return list<Route> */
    private function store(): array
    {
        return [
            ['route' => '/V1/store/storeViews',   'method' => 'GET'],
            ['route' => '/V1/store/storeGroups',  'method' => 'GET'],
            ['route' => '/V1/store/websites',     'method' => 'GET'],
            ['route' => '/V1/store/storeConfigs', 'method' => 'GET'],
        ];
    }

    /** @return list<Route> */
    private function tax(): array
    {
        return [
            ['route' => '/V1/taxRates/search',  'method' => 'GET'],
            ['route' => '/V1/taxClasses/search','method' => 'GET'],
        ];
    }
}
