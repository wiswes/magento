<?php
declare(strict_types=1);

namespace WisWes\MCP\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Install with WisWes" button on the WisWes Chat admin page.
 *
 * Clicking it does NOT just deep-link to wiswes.com. It posts to the admin
 * controller `wiswes_install/install/index`, which:
 *   1. Mints a Magento Integration access token scoped to the catalog/customer/cart
 *      resources WisWes needs.
 *   2. Server-side redirects to the WisWes onboarding URL with the token attached
 *      so chat_agent can call back into Magento's `/mcp` endpoint with proper auth.
 *   3. Returns the merchant here with a "snippet ready" flash message.
 *
 * Manual installs bypass this entirely — the merchant pastes the embed snippet
 * from the WisWes dashboard into Magento's native HTML Head config.
 */
class InstallButton extends Field
{
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $href = $this->getUrl('wiswes_install/install/index');

        return sprintf(
            '<a href="%s" class="action-default scalable" '
            . 'style="display:inline-flex;align-items:center;gap:8px;padding:8px 18px;'
            . 'background:#6951ff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">'
            . '<span>Install with WisWes</span>'
            . '<span aria-hidden="true">→</span>'
            . '</a>'
            . '<p style="margin:8px 0 0;font-size:12px;color:#6b7280;">'
            . 'Mints a Magento Integration access token scoped to catalog / customer / cart, hands it off to WisWes, '
            . 'and brings you back with the embed snippet. The token never appears on this page — Magento posts it '
            . 'directly to WisWes server-side. To install manually instead, copy the snippet from your WisWes '
            . 'dashboard and paste it into Stores → Configuration → Design → HTML Head.'
            . '</p>',
            htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }
}
