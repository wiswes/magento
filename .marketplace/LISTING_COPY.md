# Adobe Commerce Marketplace — Listing Copy

Paste-ready text for every field on the Adobe Commerce Marketplace
extension submission form. Each section is annotated with the form
field it maps to.

---

## Extension name

> **Field:** Extension Name (max 60 chars)

```
WisWes — AI Shopping Assistant (MCP)
```

(38 chars.)

---

## One-line summary

> **Field:** Tagline / Short Description (max 140 chars)

```
Ship an AI shopping assistant on your storefront in 10 minutes — chat that reads your live catalog, cart, and orders over MCP.
```

(135 chars.)

---

## Full description

> **Field:** Description (rich text). Follows Adobe's product
> description guide:
> https://developer.adobe.com/commerce/marketplace/guides/sellers/product-descriptions
>
> Conventions enforced below: bold-hyperlinked first mention of
> the integrated service (WisWes), no install instructions in body,
> every URL hyperlinked, capital "M" in Magento, Account & Pricing
> section as required for integration extensions.

**[WisWes](https://wiswes.com)** is an AI shopping-assistant platform: a chat widget that drops onto your storefront, talks to shoppers in natural language, and acts on their behalf — searching the catalog, adding to cart, looking up orders, and completing checkout.

The WisWes integration for Magento connects your store to the WisWes assistant over the [Model Context Protocol](https://modelcontextprotocol.io). Shoppers chat in their own words; the assistant calls real Magento APIs through a stateless `/mcp` endpoint served by your existing web server. Every action — cart updates, order placement, customer profile changes — routes through Magento's standard service contracts, so price rules, sales rules, stock reservations, and your other extensions all fire exactly as they would from the storefront UI.

The integration ships 22 typed tools the assistant can call across catalog, cart, checkout, customer, sales, and wishlist. A one-click admin handshake mints a shared secret and ships it to your WisWes workspace — merchants never see or copy a token. A nightly catalog push populates the WisWes vector index for semantic product search, with an on-demand button for bulk-update days.

Customer-scoped tools (cart, customer profile, orders) forward Magento's standard customer bearer token, so logged-in shoppers see only their own data and anonymous shoppers cannot read carts or orders. Tools throw `LocalizedException` with shopper-safe messages — internal stack traces never reach the AI. The module passes `phpcs --standard=Magento2` with zero errors.

### Features

- 22 typed AI tools spanning catalog search, category browse, cart add / update / remove, full checkout (address, shipping, payment, place order), customer profile and addresses, wishlist, and order status
- Stateless `/mcp` HTTP endpoint served by your existing Magento web server — no extra process or daemon to operate
- One-click admin handshake mints and ships the shared secret automatically; the merchant never handles the token
- Nightly catalog push to the WisWes vector index for semantic product search, with on-demand re-push from the admin or CLI
- Customer-aware: logged-in shoppers' carts, orders, and addresses are private to them
- Drop-in custom tools via the `#[McpTool]` PHP attribute — no fork required
- Bearer-auth on every request, `LocalizedException` boundary on every tool — no leaked stack traces
- Compatible with default themes and Hyvä-themed stores out of the box
- Open source under [GPL-3.0-or-later](https://github.com/wiswes/magento/blob/main/LICENSE) — full source on [GitHub](https://github.com/wiswes/magento)
- Free forever; no in-extension paywall

### Account and Pricing

The integration requires a [WisWes](https://wiswes.com) account, which you can create at [wiswes.com](https://wiswes.com). The account is **not** created automatically during install — sign up first, then run the one-click handshake from Magento admin to connect the two.

The Magento integration itself is **free and open source**. WisWes itself comes with a 14-day free trial — no credit card required. After the trial:

- **Basic** — $39.99/month — 1,000 conversations, plug-and-play widget
- **Pro** — $179.99/month — 10,000 conversations, custom MCP with cart, product, and customer flows
- **Enterprise** — from $500/month — unlimited conversations, A/B testing on prompts and flows, dedicated success manager, 99.9% uptime SLA

Model usage fees (Anthropic, OpenAI, etc.) are pass-through. Typical stores see $15–80/month on Basic, $60–250 on Pro, $200–800 on Enterprise. Bring your own API key to pay providers directly.

Full pricing detail at [wiswes.com/#pricing](https://wiswes.com/#pricing).

### Why Model Context Protocol

[Model Context Protocol](https://modelcontextprotocol.io) is the open standard for giving AI agents structured access to systems of record — originated by Anthropic, adopted by OpenAI, Google, and most major LLM vendors. WisWes connects to Magento *through* MCP, so the same integration also works with any future MCP-aware AI tool. Your investment is not locked to a single vendor.

### Compatibility

- Magento Open Source and Adobe Commerce 2.4.4, 2.4.5, 2.4.6, 2.4.7
- PHP 8.1, 8.2, 8.3, 8.4
- Default themes and [Hyvä](https://www.hyva.io)-themed stores
- Storefront widget is a single `<script>` tag inserted via Magento's native HTML Head config — no theme template edits

### Support

- [Install guide](https://wiswes.com/install/magento)
- [Documentation](https://wiswes.com/docs)
- [Issue tracker](https://github.com/wiswes/magento/issues)
- Email: [services@wiswes.com](mailto:services@wiswes.com)
- [Paid integration & on-store install](https://wiswes.com/services#magento-dev) — we can install, configure, and ship it for you

---

## Features (bulleted, for the "Key features" sidebar)

> **Field:** Features (Adobe usually allows ~10 short bullets)

- 22 typed AI tools across catalog, cart, checkout, customer, sales, wishlist
- Stateless `/mcp` endpoint over Magento's web server — no extra process
- One-click admin handshake mints + ships the shared secret automatically
- Nightly catalogue push to WisWes' vector index for semantic search
- Customer-aware: logged-in shoppers' carts and orders are private to them
- Drop-in custom tools via `#[McpTool]` PHP attribute
- Bearer-auth + Magento `LocalizedException` boundary — no leaked stack traces
- Magento 2.4.4–2.4.7 / PHP 8.1–8.4 / Hyvä compatible
- GPL-3.0 — full source on GitHub
- Free forever; no in-extension paywall

---

## Keywords / tags

> **Field:** Keywords (comma-separated, ~10 max)

```
ai, chatbot, ai-assistant, mcp, model-context-protocol, shopping-assistant, conversational-commerce, customer-support, product-search, chat-widget
```

---

## Categories

> **Field:** Adobe Commerce Marketplace category tree

Primary: **Customer Support** → **Live Chat & Helpdesk**
Secondary: **Marketing** → **Personalization & Experience**

(If "AI / ML" is offered as a category at submission time, prefer it
as primary and demote Customer Support to secondary.)

---

## Pricing model

> **Field:** Pricing tier

- **Type:** Free
- **License:** GPL-3.0-or-later (Adobe Marketplace's "Open Source"
  selection)

---

## Composer package

> **Field:** Composer Package Name

```
wiswes/magento-mcp
```

> **Field:** Composer Package Version

```
1.0.5
```

(v1.0.0 was tagged before the package rename to
`wiswes/magento-mcp` and is not available on Packagist under the
current name. v1.0.3 is the first stable with the
`version` field declared in composer.json — Adobe MEQP requires
the explicit version even though Packagist normally derives it
from the git tag.)

> **Field:** Source repository URL

```
https://github.com/wiswes/magento
```

---

## Support contacts

> **Field:** Support email / URL

- Support email: `services@wiswes.com`
- Support URL: `https://wiswes.com/install/magento`
- Issue tracker: `https://github.com/wiswes/magento/issues`

---

## User Guide

> **Field:** User Guide URL (Adobe usually requires this)

```
https://wiswes.com/install/magento
```

---

## Privacy policy

> **Field:** Privacy Policy URL (Adobe requires this on every listing)

```
https://wiswes.com/privacy
```

---

## Terms & conditions

> **Field:** Terms / EULA URL (Adobe shows this on the listing
> alongside the privacy link)

```
https://wiswes.com/terms
```

---

## Release notes for v1.0.5

> **Field:** Version release notes (max ~1500 chars)

```
Initial public release.

- 22 typed MCP tools across catalog, cart, checkout, customer, sales, wishlist.
- Stateless /mcp HTTP endpoint over Magento's web server.
- One-click admin handshake ships a shared secret to your WisWes workspace.
- Nightly catalogue push to the WisWes vector index for semantic product search.
- Customer-scoped tools forward Magento's standard customer bearer token.
- Custom tools via the #[McpTool] PHP attribute — drop a class into Mcp/Tool/ to expose new capabilities.
- Magento 2.4.4–2.4.7 compatible, PHP 8.1/8.2/8.3/8.4.
- Passes phpcs --standard=Magento2 with 0 errors.
```

---

## Developer / publisher info

> **Field:** Publisher / Developer name

```
WisWes
```

> **Field:** Publisher website

```
https://wiswes.com
```
