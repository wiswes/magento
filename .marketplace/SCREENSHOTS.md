# Adobe Commerce Marketplace — Screenshots Brief

Adobe accepts up to **10 screenshots** per listing. Recommended
minimum size **1280×800**. PNG or JPG. Each one needs a one-line
caption.

The screenshots below are the ones to capture against a real Magento
2.4.7 install with `WisWes_MCP` enabled. Names are the file names to
save them under in `.marketplace/screenshots/`.

---

## Required (the listing reads weak without these)

### 1. `01-storefront-chat-open.png`
**Caption:** *Wes answering a product question on the storefront with
a real product card.*

Capture the Magento storefront with the WisWes chat bubble open,
mid-conversation. The shopper has asked "recommend a wireless
charger for an iPhone" and Wes has replied with a product card
pulled from the live catalog. Make sure the storefront URL bar
shows a real Magento domain, not localhost.

### 2. `02-storefront-cart-action.png`
**Caption:** *"Add the second one to my bag" — Wes calls cart:add and
confirms the new line item.*

Same conversation a few turns later: shopper says "add the second
one", Wes confirms. Show the floating cart count in the header
incrementing in the same screenshot if possible.

### 3. `03-admin-install-button.png`
**Caption:** *One-click admin handshake mints the shared secret and
hands it to your WisWes workspace.*

Magento admin → Stores → Configuration → WisWes Chat → WisWes Chat
Widget. Show the **Install** button section with its inline help
text visible.

### 4. `04-admin-mcp-config.png`
**Caption:** *The MCP connection page — set the dashboard URL,
trigger an immediate catalog push.*

Magento admin → Stores → Configuration → WisWes Chat → WisWes Chat
MCP. Show both the Connection group (with `WisWes Dashboard URL`
field) and the Catalogue Sync group (with `Push catalogue now`
button) in one screenshot.

### 5. `05-tools-list-in-wiswes.png`
**Caption:** *All 22 Magento tools auto-discovered in the WisWes
workspace within seconds of install.*

WisWes dashboard → Behavior → Tools, filtered to the Magento
namespace. Show the full list of 22 tools (catalog, cart,
checkout, customer, sales, wishlist).

---

## Strongly recommended (raises listing quality)

### 6. `06-conversation-log.png`
**Caption:** *Every chat is logged with the tool calls inline — debug
prompts, audit conversations, refine descriptions.*

WisWes dashboard → Conversations → opened conversation. Show the
chat transcript on the left and the tool-call detail panel on the
right.

### 7. `07-custom-tool-class.png`
**Caption:** *Add custom tools by dropping a PHP class with
`#[McpTool]` into `Mcp/Tool/` — no fork needed.*

A code editor (VS Code or similar) showing
`Mcp/Tool/Loyalty/LoyaltyPointsTool.php` with the `#[McpTool]`
attribute highlighted. The example from `README.md` lines 240–260
is a good source.

### 8. `08-curl-mcp-endpoint.png`
**Caption:** *The `/mcp` endpoint is just a bearer-authenticated
HTTPS POST — easy to test, easy to integrate.*

A terminal window showing the `curl -X POST https://.../mcp` from
the README's "Verify it works" section, with the JSON-RPC
`tools/list` response visible.

---

## Optional (room permitting)

### 9. `09-magento-cart-after-chat.png`
**Caption:** *Carts created via chat are real Magento carts — every
extension hook fires, every sales rule applies.*

Open the cart page on the storefront. Show the line items the
shopper added through chat, with the same totals/shipping/taxes
that would appear from a normal storefront-driven flow.

### 10. `10-tool-discovery-toggle.png`
**Caption:** *Per-workspace tool gating from the WisWes admin —
toggle individual tools, override descriptions, pin defaults.*

WisWes dashboard → Behavior → Tools → expanded tool detail panel
showing the description override field and the toggle switch.

---

## Capture tips

- Use a real Magento 2.4.7 store with at least 50 products and
  realistic categories. Sample data is fine if it's been touched
  up so SKUs/names look like a real catalog.
- Browser frame: hide bookmark bars; don't show extension icons.
- Don't include test customer email addresses, real customer
  PII, or active shared secrets in any screenshot. Blur them
  before saving.
- Run at 1440×900 or 1920×1200 native, then export at 1280×800
  for a tight crop.
- For admin screenshots, expand the relevant accordion / section
  before screenshotting so the field is visible without
  scrolling.
