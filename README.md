# WisWes MCP for Magento 2

[![Version](https://img.shields.io/badge/version-1.0.2-blue.svg)](https://github.com/wiswes/magento/releases)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Magento](https://img.shields.io/badge/Magento-2.4.4%2B-orange.svg)](https://magento.com)
[![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-777BB4.svg)](https://php.net)

The official WisWes module for Magento 2. Plugs your store into the **WisWes** AI shopping assistant ([wiswes.com](https://wiswes.com)) over the **Model Context Protocol (MCP)**.

The module ships:

- A stateless MCP HTTP endpoint at `/mcp`, served through your existing Magento web server — no separate process to manage.
- 22 typed tools across catalog, cart, checkout, customer, sales, and wishlist.
- A one-click admin handshake that hands a shared secret to your WisWes workspace.
- Nightly catalogue push to the WisWes vector index for semantic product search.

> **What this gives you:** the `Wes` chat persona on your storefront can read your live Magento data and act on the cart with no glue code. Shoppers ask questions in natural language, Wes calls the right tool, you ship more orders.

- **Module name:** `WisWes_MCP`
- **Composer package:** `wiswes/magento-mcp`
- **Tested Magento versions:** 2.4.4, 2.4.5, 2.4.6, 2.4.7
- **PHP:** 8.1 / 8.2 / 8.3

---

## Table of contents

- [Install](#install)
  - [Option A — Composer (recommended)](#option-a--composer-recommended)
  - [Option B — Git clone](#option-b--git-clone)
  - [Option C — ZIP archive](#option-c--zip-archive)
- [Connect to WisWes](#connect-to-wiswes)
- [Push the catalogue](#push-the-catalogue)
- [Install the storefront widget](#install-the-storefront-widget)
- [Verify it works](#verify-it-works)
- [Tools shipped out of the box](#tools-shipped-out-of-the-box)
- [Use](#use)
- [Extend — write your own tool](#extend--write-your-own-tool)
- [Configuration reference](#configuration-reference)
- [Upgrade](#upgrade)
- [Uninstall](#uninstall)
- [Troubleshoot](#troubleshoot)
- [Support](#support)
- [License](#license)

---

## Install

Pick one of the three install paths. Composer is recommended for production.

### Option A — Composer (recommended)

```bash
composer require wiswes/magento-mcp
bin/magento module:enable WisWes_MCP
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The package is published on [Packagist](https://packagist.org/packages/wiswes/magento-mcp). Pin a major line with `composer require wiswes/magento-mcp:^1.0`.

### Option B — Git clone

```bash
mkdir -p app/code/WisWes
git clone https://github.com/wiswes/magento.git app/code/WisWes/MCP
cd app/code/WisWes/MCP && git checkout v1.0.2 && cd -

bin/magento module:enable WisWes_MCP
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Option C — ZIP archive

Download the archive from the [Releases page](https://github.com/wiswes/magento/releases/latest) and unzip into your Magento root:

```bash
unzip ~/Downloads/wiswes-magento-1.0.2.zip -d .
mkdir -p app/code/WisWes && mv wiswes-magento-1.0.2 app/code/WisWes/MCP

bin/magento module:enable WisWes_MCP
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Runtime dependency

The module depends on the [`php-mcp/server`](https://github.com/php-mcp/server) library at runtime. It is intentionally not declared as a hard Composer dependency yet — install it once at the project root so you can pin the version your stack tolerates:

```bash
composer require php-mcp/server
```

---

## Connect to WisWes

The MCP endpoint is served by your existing web server at `https://<your-magento>/mcp`. There is **no separate process to start** — once `setup:upgrade` runs, the route is live.

Connecting to your WisWes workspace is a one-click handshake from the Magento admin:

1. Open **Stores → Configuration → WisWes Chat → WisWes Chat MCP → Connection**.
2. (Optional) Set the **WisWes Dashboard URL** if you're connecting to a staging or self-hosted dashboard. Default: `https://api.wiswes.com/`.
3. Save the config.
4. Open **Stores → Configuration → WisWes Chat → WisWes Chat Widget → Install** and click the **Install** button.
5. Magento generates a long random shared secret unique to this install, persists it (encrypted) under `wiswes_mcp/auth/shared_secret`, and redirects you to the WisWes dashboard with the secret embedded as `install_token` in the URL.
6. The dashboard saves the token on your tenant's CommerceConfig. From now on every WisWes chat reaches your store as `POST https://<your-magento>/mcp` with `Authorization: Bearer <secret>`.

The merchant never sees the secret in the browser — it travels server-to-server via a redirect over HTTPS.

### Auth model

Tools fall into three categories based on the Magento context they need:

| Tool group              | Token type                                              |
|-------------------------|---------------------------------------------------------|
| Catalog (search/filter/get/category) | shared install secret only — public storefront context |
| Cart, customer, wishlist             | shared secret + customer bearer (forwarded by WisWes when chat is identified) |
| Order updates                        | shared secret + customer (own orders) or admin (any order) |

WisWes forwards the shopper's customer token automatically when the chat is logged in. Anonymous shoppers can still use catalog tools but can't read the cart until they sign in.

---

## Push the catalogue

WisWes serves product search out of its own vector index, populated from your Magento store. The push runs nightly at 03:00 by default; trigger it manually after a bulk catalogue change:

```bash
# from Magento root
bin/magento wiswes:products:push
# Pushed 4271 products in 43 batches (upserted=4271, skipped_operator=0, skipped_cap=0)
```

Or click **Push catalogue now** under **Stores → Configuration → WisWes Chat → WisWes Chat MCP → Catalogue Sync**.

The push ships a compact retrieval payload (`sku`, `name`, `url`, `price`) plus a metadata blob built from name + short description + searchable attributes. The blob is what WisWes embeds; the retrieval payload is what the LLM sees verbatim when a result matches.

The push is incremental — only enabled, visible products are sent, batched 100 at a time. Auth uses the same shared secret minted by the Install handshake.

---

## Install the storefront widget

The WisWes chat bubble is a single `<script>` tag. The module does **not** auto-inject it — paste it into Magento's native script areas instead, so your install path is identical to Shopify or any custom storefront:

- **Stores → Configuration → Design → HTML Head → Scripts and Style Sheets**, or
- `default_head_blocks.xml` in your theme

Copy the snippet from your WisWes workspace under **Configuration → Commerce → Embed snippet**. It looks like:

```html
<script src="https://app.wiswes.com/api/widget/embed.js?user_token=YOUR_TENANT_TOKEN" defer></script>
```

The token in the snippet is your tenant ID — every chat that loads via this snippet is scoped to your workspace.

---

## Verify it works

1. **MCP endpoint reachable.** From any host that WisWes can reach:
   ```bash
   curl -X POST https://<your-magento>/mcp \
     -H 'Authorization: Bearer <your-shared-secret>' \
     -H 'Content-Type: application/json' \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
   ```
   You should see a JSON-RPC response listing the 22 built-in tools.
2. **Tools discovered in WisWes.** Open WisWes → **Behavior → Tools**. The 22 tools appear within seconds.
3. **Catalog wired.** Ask Wes: *"recommend a wireless charger for an iPhone."* Wes should call `product:filter` and return real catalog rows.
4. **Cart wired.** While logged in on the storefront, ask: *"add the second one to my bag."* Wes should call `cart:add` and confirm the new line item.
5. **Conversation logged.** Refresh **Conversations** in WisWes — your test chat appears with the tool calls in the right pane.

Full tool reference + arguments lives in the [WisWes docs](https://wiswes.com/docs#magento-tools).

---

## Tools shipped out of the box

22 tools across six groups. Names listed are the MCP tool ids the agent sees.

| Group     | Tool                              | Summary                                                       |
|-----------|-----------------------------------|---------------------------------------------------------------|
| Catalog   | `category:list`                   | Category tree as a nested list                                |
| Catalog   | `product:filter`                  | Structured filter queries (EAV field, operator, value)        |
| Catalog   | `product:filter:options`          | Discover the filterable attributes + their option values      |
| Catalog   | `product:get`                     | Full product payload by SKU or ID                             |
| Cart      | `cart:info`                       | Snapshot of the active cart                                   |
| Cart      | `cart:add`                        | Add a product (configurable / bundle / custom options)        |
| Cart      | `cart:update`                     | Quantity, coupon apply / remove                               |
| Cart      | `cart:remove`                     | Remove a line item by id                                      |
| Checkout  | `checkout:set:address`            | Billing or shipping address (guest or logged-in)              |
| Checkout  | `checkout:shipping_methods`       | Available shipping methods for the active cart                |
| Checkout  | `checkout:payment_methods`        | Available payment methods for the active cart                 |
| Checkout  | `checkout:place_order`            | Place the order from the active cart                          |
| Customer  | `customer:create`                 | Register a new customer                                       |
| Customer  | `customer:info`                   | Profile, addresses, recent orders                             |
| Customer  | `customer:update`                 | Patch profile fields                                          |
| Customer  | `customer:address:list`           | All saved addresses                                           |
| Customer  | `customer:address:update`         | Create or update an address                                   |
| Customer  | `customer:address:remove`         | Delete an address                                             |
| Sales     | `order:info`                      | Compact order status + history + tracking                     |
| Sales     | `order:update`                    | Comment / hold / cancel / change shipping address             |
| Wishlist  | `wishlist:items`                  | All items in the customer wishlist                            |
| Wishlist  | `wishlist:add:item`               | Add a product to the wishlist                                 |

Every tool returns a typed array or throws a Magento `LocalizedException` with a shopper-safe message — no raw stack traces leak to the agent.

---

## Use

Once installed and connected, the day-to-day workflow is:

1. **Customers chat** with Wes via the WisWes widget on your storefront.
2. **Wes selects tools** to answer questions or take actions — search the catalog, look up an order, add to cart.
3. **Tool calls hit `/mcp` on your store**, which reads/writes through Magento's standard service contracts so all your existing extension hooks fire (price rules, stock reservations, sales rules, etc.).
4. **Results return to Wes**, who replies in natural language with product cards, status updates, or confirmations.

You can scope which tools are available per workspace under **Behavior → Tools** in WisWes — toggle individual tools on/off, override their description (the prompt text the model reads), or pin argument defaults. None of this requires touching PHP.

---

## Extend — write your own tool

Every Magento tool is a plain PHP class with a `#[McpTool]` attribute. Drop a class into `Mcp/Tool/...`, rebuild the DI cache, clear the OPcache, and the tool appears in your WisWes workspace within seconds.

### Minimal example

```php
<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Loyalty;

use PhpMcp\Server\Attributes\McpTool;

class LoyaltyPointsTool
{
    public function __construct(
        private readonly \Acme\Loyalty\Api\PointsClient $client,
    ) {}

    #[McpTool(
        name: 'loyalty:points',
        description: 'Returns the authenticated customer\'s loyalty tier and current points balance. Arguments: none. Customer bearer token required.'
    )]
    public function points(): array
    {
        $snapshot = $this->client->snapshotForCurrentUser();

        return [
            'tier'          => $snapshot->getTier(),
            'points'        => $snapshot->getBalance(),
            'tier_progress' => $snapshot->getProgressToNext(),
        ];
    }
}
```

After:

```bash
bin/magento setup:di:compile
bin/magento cache:flush
```

The tool appears under **Behavior → Tools** in WisWes labelled `loyalty:points`.

### Conventions that pay off

- **One tool, one job.** The model picks better between ten narrow tools than between three broad ones.
- **Be specific in `description`.** It's the prompt the model reads when deciding whether to call your tool. Include each argument, the return shape, and when *not* to use the tool.
- **Validate at the boundary.** Throw `Magento\Framework\Exception\LocalizedException` with a shopper-safe message — the agent will surface it as-is.
- **Return arrays, not DTOs.** Strict typed arrays serialize cleanly to MCP. Hide internals (`row_id`, `parent_id`, internal flags) from the response unless the model needs them.
- **Stay stateless.** Tools should work the same on first call and 1,000th call. Cart / order state belongs in Magento, not in the tool class.

### Tool with arguments

```php
#[McpTool(
    name: 'sales:track_order',
    description: 'Returns carrier and tracking URL for an order. Arguments: order_id (string, required).'
)]
public function track(string $orderId): array
{
    $shipment = $this->client->getLatestShipment($orderId);
    return [
        'order_id'     => $orderId,
        'carrier'      => $shipment->getCarrier(),
        'status'       => $shipment->getStatus(),
        'tracking_url' => $shipment->getTrackingUrl(),
    ];
}
```

### Suppressing built-in tools

To run a curated subset, set the `wiswes_mcp/tools/include` config value to a comma-separated glob list:

```bash
bin/magento config:set wiswes_mcp/tools/include 'product:*,cart:*,order:info'
bin/magento cache:flush
```

`*` (the default) means all built-in + custom tools are exposed.

---

## Configuration reference

All settings live under **Stores → Configuration → WisWes Chat** (or via `bin/magento config:set`).

### Connection (`wiswes_mcp/install/*`)

| Path                              | Purpose                                                              | Default                       |
|-----------------------------------|----------------------------------------------------------------------|-------------------------------|
| `wiswes_mcp/install/wiswes_url`   | Base URL of the WisWes dashboard the Install button redirects to     | `https://api.wiswes.com/`     |

### Auth — set automatically by the Install handshake (`wiswes_mcp/auth/*`)

| Path                                | Purpose                                                              |
|-------------------------------------|----------------------------------------------------------------------|
| `wiswes_mcp/auth/shared_secret`     | Encrypted shared secret used to authenticate `/mcp` bearer tokens    |
| `wiswes_mcp/auth/admin_id`          | Admin user id captured at install time, drives ACL                    |

### Tools (`wiswes_mcp/tools/*`)

| Path                              | Purpose                                                              | Default                       |
|-----------------------------------|----------------------------------------------------------------------|-------------------------------|
| `wiswes_mcp/tools/include`        | Glob list of tool names to expose                                    | `*`                           |

### Server identity (`wiswes_mcp/server/*`)

| Path                              | Purpose                                                              | Default                       |
|-----------------------------------|----------------------------------------------------------------------|-------------------------------|
| `wiswes_mcp/server/name`          | Advertised server name in the MCP `initialize` handshake             | `WisWes Magento MCP`          |

---

## Upgrade

```bash
# Composer
composer update wiswes/magento-mcp
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

# Git
cd app/code/WisWes/MCP && git fetch && git checkout v<new-tag> && cd -
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

# ZIP — download the new archive and unzip over the existing folder, then run the same setup commands.
```

---

## Uninstall

```bash
bin/magento module:disable WisWes_MCP
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

# Composer
composer remove wiswes/magento-mcp

# Git / ZIP
rm -rf app/code/WisWes/MCP

# Optional — wipe the install secret
bin/magento config:set wiswes_mcp/auth/shared_secret ''
bin/magento config:set wiswes_mcp/auth/admin_id ''
```

In WisWes, clear the connection under **Configuration → Commerce**.

---

## Troubleshoot

### Module installs but no tools show up in WisWes

- Run `bin/magento setup:di:compile` — required after any new tool class is added.
- Clear OPcache (`bin/magento cache:flush` or restart php-fpm) — tool discovery happens at first request after a deploy.
- Check the MCP URL is reachable from WisWes (firewall, NAT). From a WisWes-side host:
  ```bash
  curl -i -X POST https://<your-magento>/mcp \
    -H 'Authorization: Bearer <your-secret>' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
  ```

### Tool calls return 401 Unauthorized

- The shared secret rotated or wasn't installed. Re-run the Install handshake from **Stores → Configuration → WisWes Chat → Install**.
- For customer-scoped tools (cart, customer, order), the storefront chat must be identified — anonymous chats can't read the cart.

### `Class PhpMcp\Server\Server not found`

The runtime depends on `php-mcp/server`. Install it at your project root:

```bash
composer require php-mcp/server
```

### Composer can't find a matching version of `wiswes/magento-mcp`

Run `composer clear-cache`, then re-try `composer require wiswes/magento-mcp:^1.0`. Packagist refreshes its index within seconds of every tagged push, so this is almost always a stale local cache.

To install from a feature branch that hasn't been tagged yet, add the GitHub source as a VCS repository in your `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/wiswes/magento" }
  ]
}
```

### Catalogue push reports `skipped_cap` or `skipped_operator`

- `skipped_cap` — your WisWes plan's product index limit was hit. Upgrade the plan or trim the catalog filter.
- `skipped_operator` — a row was rejected by the WisWes side as malformed (missing SKU, empty name). Inspect via:
  ```bash
  bin/magento wiswes:products:push -vvv
  ```

### `/mcp` returns 404

- Confirm the module is enabled: `bin/magento module:status WisWes_MCP`.
- Confirm the `mcp` route is registered: `bin/magento info:routes:url:list 2>/dev/null | grep mcp` (or check `etc/frontend/routes.xml`).
- Re-run `bin/magento setup:upgrade`.

---

## Support

- **Docs:** https://wiswes.com/docs
- **Install guide:** https://wiswes.com/install/magento
- **Issues:** https://github.com/wiswes/magento/issues
- **Email:** services@wiswes.com
- **Paid integration:** https://wiswes.com/services#magento-dev — we install, configure, and ship the widget on your store for you.

---

## License

Released under the [GNU General Public License v3.0](LICENSE) — see the LICENSE file for full terms.
