<?php
declare(strict_types=1);

namespace WisWes\MCP\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Push catalogue now" button on the WisWes Chat MCP admin page.
 * Hits the admin controller `wiswes_push/push/index` which runs the same
 * push code path the nightly cron uses.
 */
class PushButton extends Field
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $href = $this->getUrl('wiswes_push/push/index');

        return sprintf(
            '<a href="%s" class="action-default scalable" '
            . 'style="display:inline-flex;align-items:center;gap:8px;padding:8px 18px;'
            . 'background:#10b981;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">'
            . '<span>Push catalogue to WisWes</span>'
            . '<span aria-hidden="true">↑</span>'
            . '</a>'
            . '<p style="margin:8px 0 0;font-size:12px;color:#6b7280;">'
            . 'Pushes every enabled, catalog-visible product to WisWes for vector indexing. '
            . 'Runs automatically every night; this button is for after a bulk catalogue change '
            . 'when you don\'t want to wait. Rows that the WisWes dashboard operator has edited '
            . 'are skipped — those edits win until cleared.'
            . '</p>',
            htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }
}
