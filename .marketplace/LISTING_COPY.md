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

> **Field:** Description (rich text, ~500–2000 words). Adobe lets you
> include H2/H3 headings, bullet lists, and bold text.

### What WisWes is

WisWes plugs your Magento 2 store into the **WisWes AI Shopping
Assistant** ([wiswes.com](https://wiswes.com)) over the **Model
Context Protocol (MCP)**. Shoppers chat in natural language; the
assistant calls real Magento APIs to search products, add to cart,
look up orders, and complete checkout — entirely inside your
storefront.

This extension is the official, free, open-source bridge between
Magento and WisWes. There is no paid tier inside the extension
itself — you pay WisWes for the chat assistant subscription, the
extension is free forever.

### What you get out of the box

- **22 typed Magento tools** the AI can call: catalog search,
  category browse, cart add/update/remove, checkout (address,
  shipping, payment, place order), customer profile + addresses +
  wishlist, and order status.
- **Stateless `/mcp` HTTP endpoint** served by your existing
  Magento web server — no extra process to run, no separate Node
  service, no daemon to monitor.
- **One-click admin handshake** — generates and exchanges a shared
  secret with your WisWes workspace from the Magento admin. No
  copy-pasting tokens.
- **Nightly catalogue push** to the WisWes vector index for
  semantic search. Trigger on demand from the admin or via CLI.
- **Customer-aware tools** — when a logged-in shopper chats, the
  assistant sees *their* cart, *their* orders, *their* addresses
  via Magento's standard customer token forwarding.
- **Extensible** — add a custom tool by dropping a class with a
  `#[McpTool]` attribute into `Mcp/Tool/`. No fork required.

### Why MCP

Model Context Protocol is the open standard (originated by
Anthropic, adopted by OpenAI, Google, and most major LLM
vendors) for giving AI agents structured access to systems of
record. WisWes connects to Magento *through* MCP, so the same
extension also works with any future MCP-aware AI tool — your
investment isn't locked to one vendor.

### Built on Magento's standard plumbing

Every tool routes through Magento's service contracts. Your
existing extension hooks fire — price rules, sales rules, stock
reservations, plugin observers. Nothing is bypassed. If a
shopper places an order via chat, your fulfilment pipeline sees
exactly the same order it would have seen from the storefront UI.

### Security

- The `/mcp` endpoint is bearer-token authenticated. The shared
  secret is minted by Magento at install time, persisted
  encrypted under `wiswes_mcp/auth/shared_secret`, and never
  shown in the browser — the install handshake hands it
  server-to-server via redirect over HTTPS.
- Customer-scoped tools require a **second** token (the customer
  bearer that Magento already issues to logged-in shoppers).
  Anonymous chats can browse the catalogue but cannot read carts
  or orders.
- Tools throw `LocalizedException` with shopper-safe messages.
  Internal stack traces never reach the AI.
- The module passes `phpcs --standard=Magento2` with **0 errors**.

### Compatibility

- **Magento Open Source / Adobe Commerce**: 2.4.4, 2.4.5, 2.4.6,
  2.4.7
- **PHP**: 8.1, 8.2, 8.3
- Works on default themes and on Hyvä-themed stores. The
  storefront widget is a single `<script>` tag inserted via
  Magento's native HTML Head config — no theme template edits.

### Pricing

Extension is **free**. WisWes itself has a free tier (5,000
chats/month) and paid plans starting at $49/mo at
[wiswes.com/#pricing](https://wiswes.com/#pricing).

### Support

- Install guide: https://wiswes.com/install/magento
- Docs: https://wiswes.com/docs
- Issues: https://github.com/wiswes/magento/issues
- Email: services@wiswes.com
- Paid integration / on-store install: https://wiswes.com/services#magento-dev

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
- Magento 2.4.4–2.4.7 / PHP 8.1–8.3 / Hyvä compatible
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
1.0.2
```

(v1.0.0 was tagged before the package rename to
`wiswes/magento-mcp` and is not available on Packagist under the
current name. v1.0.2 is the first stable that ships with the
`.marketplace/` folder excluded from the dist archive.)

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

## Release notes for v1.0.2

> **Field:** Version release notes (max ~1500 chars)

```
Initial public release.

- 22 typed MCP tools across catalog, cart, checkout, customer, sales, wishlist.
- Stateless /mcp HTTP endpoint over Magento's web server.
- One-click admin handshake ships a shared secret to your WisWes workspace.
- Nightly catalogue push to the WisWes vector index for semantic product search.
- Customer-scoped tools forward Magento's standard customer bearer token.
- Custom tools via the #[McpTool] PHP attribute — drop a class into Mcp/Tool/ to expose new capabilities.
- Magento 2.4.4–2.4.7 compatible, PHP 8.1/8.2/8.3.
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
