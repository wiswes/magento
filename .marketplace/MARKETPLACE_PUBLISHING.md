# Adobe Commerce Marketplace — Publishing Plan

End-to-end runbook for getting `wiswes/magento-mcp` listed on the
Adobe Commerce Marketplace as a free extension.

> **Status as of 2026-05-06**
>
> ✅ Phase 1 — Technical readiness (PHPCS clean, secure serializer, no XSS)
> ✅ Phase 1.5 — Admin UX (config XML already polished, no work required)
> ✅ Phase 2 — Listing copy + icon assets staged in `.marketplace/`
> ⏳ Phase 3 — Adobe seller account + screenshots + submission
> ⏳ Phase 4 — Tag `v1.0.0` on `wiswes/magento` repo

The remaining steps need a human in front of the Adobe portal and a
running Magento install for screenshots — **they cannot be automated.**

---

## Inventory of what's ready

| Asset | Location | Notes |
|---|---|---|
| Composer package | https://packagist.org/packages/wiswes/magento-mcp | Already published |
| Source repo | https://github.com/wiswes/magento | Public, GPL-3.0 |
| README + install guide | `README.md` (459 lines) | Comprehensive, no gaps |
| Listing copy (every form field) | `.marketplace/LISTING_COPY.md` | Paste-ready |
| Square icons (100/256/512/1024) | `.marketplace/icons/` | Auto-generated from wordmark; see caveat below |
| Screenshot brief (10 shots) | `.marketplace/SCREENSHOTS.md` | Specs + captions ready; capture is manual |
| PHPCS Magento2 standard | 0 errors / 376 warnings | Marketplace-acceptable |
| LICENSE | `LICENSE` (GPL-3.0) | Acceptable open-source license |

---

## ⚠️ Icon caveat

The icons in `.marketplace/icons/` are programmatic crops of the
existing **wordmark** logo — *"WisWes / chat your way to checkout"*
centered on white. They look fine at 512×512 and 1024×1024 but the
tagline becomes hard to read at 100×100 (the size Adobe uses on
search/browse cards).

**Recommendation before submission:** commission a designer pass for
a true icon-only mark — typically the "W" letterform, or a chat-
bubble + cart symbol, in WisWes brand violet `#6E3FF3`. Adobe's
listing pages will render the 100×100 most often, so this matters
more than the larger sizes.

If you ship with the wordmark icons as-is, the listing won't be
rejected; it just won't pop visually next to listings that have a
proper symbolic mark.

---

## Phase 3 — Step-by-step submission

### 3.1 Create the Adobe Marketplace seller account

> **You must do this part — Claude is not allowed to create accounts
> on your behalf.**

1. Open https://commercemarketplace.adobe.com/seller-portal in a
   browser signed into your `wes@wiswes.com` Adobe ID.
2. Click **Become a Developer** → fill the company profile:
   - **Legal name:** WisWes (or the entity you'll receive payouts
     under — though for a free extension this still has to be filled)
   - **Public seller name:** WisWes
   - **Country / address:** your business address
   - **Tax forms:** W-9 (US) or W-8BEN (non-US). Required even
     for free extensions.
3. Submit. Adobe usually approves within 1–3 business days.

### 3.2 Capture screenshots

Follow `.marketplace/SCREENSHOTS.md`. Minimum 5, target 10.

Save them to `.marketplace/screenshots/` using the file names in
that doc, then commit to the repo so the listing has a public URL
to point at if Adobe asks for source.

### 3.3 Tag v1.0.0 on the magento repo

Once screenshots are committed, cut the tag Adobe will resolve to:

```bash
cd /Users/serhiikovalenko/SITES/chat_ai/app/magento
git tag -a v1.0.0 -m "WisWes Magento MCP v1.0.0 — initial Marketplace release"
git push origin v1.0.0
```

Packagist auto-detects the tag and publishes
`wiswes/magento-mcp:1.0.0` within ~60 seconds.

### 3.4 Build the submission package

Adobe's MEQP review wants either:
- A **Composer package reference** (preferred for free extensions), or
- A **ZIP archive** of the source.

Composer reference is simpler — once the Packagist tag is live,
you just enter `wiswes/magento-mcp` and version `1.0.0` in the
form.

### 3.5 Fill the submission form

In the Adobe Marketplace seller portal:

1. **My Products** → **Submit New Product**
2. Choose **Adobe Commerce / Magento Open Source extension**
3. Paste from `.marketplace/LISTING_COPY.md`:
   - Extension name
   - Tagline / short description
   - Full description
   - Features bullets
   - Keywords / tags
   - Categories
   - Pricing (Free) + License (GPL-3.0)
   - Support email + URL + issue tracker
   - User Guide URL
   - Release notes
   - Publisher info
4. Upload icons from `.marketplace/icons/`:
   - 100×100 — listing card thumbnail
   - 512×512 — listing detail page
5. Upload screenshots from `.marketplace/screenshots/` with the
   captions from `SCREENSHOTS.md`.
6. Enter the Composer package: `wiswes/magento-mcp` version
   `1.0.0`.
7. Submit for review.

### 3.6 What to expect during review

Adobe runs three sequential checks:

1. **Marketing review** (~3–5 business days). Listing copy,
   screenshots, category fit, support contacts. If they push
   back, edit the listing draft and resubmit — this is an
   in-portal change, no new Packagist tag needed.

2. **Technical review (MEQP)** (~5–10 business days). They run
   `phpcs --standard=Magento2` (we've already cleaned it),
   `phpcs --standard=MEQP2` (same standard, a bit stricter),
   plus their own automated installer test on a clean Magento.
   Common kickbacks at this stage:
   - Missing `app/code/.../etc/module.xml` `<sequence>` for any
     framework module the extension depends on (we depend on
     `Magento_Catalog`, `Magento_Customer`, `Magento_Sales`,
     `Magento_Quote`, `Magento_Checkout` — already declared).
   - `composer.json` missing the `type: magento2-module` field
     (already set).
   - `LICENSE` file mismatch with `composer.json` license field
     (both say GPL-3.0-or-later).
   - Hard-coded URLs that should be configurable (the dashboard
     URL is already configurable via
     `wiswes_mcp/install/wiswes_url`).

3. **Functional review** (~3–5 business days). They install the
   module on their staging Magento, click through the admin
   config, and verify the storefront widget loads. They will not
   actually exercise the WisWes side — you don't need to give
   them a WisWes account. The Install button just needs to
   redirect successfully.

Total wall time: **2–4 weeks** end to end for a first submission.

### 3.7 If they reject

Each rejection comes with a written list of issues in the seller
portal. Fix them in the repo, push a new patch tag (`v1.0.1`),
update the Composer version in the listing draft, and resubmit.

The most common first-submission rejection reason is the icon —
they often ask for a more recognizable mark. If that happens, the
designer pass mentioned above becomes mandatory.

---

## Phase 4 — Post-launch

Once approved:

- Listing goes live within 24h at
  `https://commercemarketplace.adobe.com/wiswes-magento-mcp.html`
  (URL slug derived from extension name).
- Update `app/sell` install page to add an Adobe Marketplace
  badge alongside the Packagist + GitHub buttons.
- Update the `README.md` install instructions to mention the
  Marketplace as a fourth install option.
- Submit a release announcement on the Magento subreddit
  (`r/Magento2`) and the Magento Community Slack
  (`#extensions-announcements`).

---

## Open decisions for you

1. **Designer pass for the icon — yes / no?** Affects whether to
   submit now or wait. If "no", we ship with the wordmark icons
   and accept a higher rejection probability on first round.
2. **Tax form** (W-9 or W-8BEN) — choose based on your business
   entity location.
3. **Do you want to register the listing under "WisWes" the
   company or under "Wes" personally?** This drives the Adobe
   seller profile fields and the payout structure (irrelevant
   for free extensions, but the seller name appears on the
   listing card).
