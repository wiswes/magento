<?php
declare(strict_types=1);

namespace WisWes\MCP\Block;

use WisWes\MCP\Model\Auth\CustomerIdSigner;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;

/**
 * Renders the `window.CHAT_WIDGET_CONFIG` initialiser script that the chat
 * widget reads on boot. Two modes:
 *
 *  - Non-cacheable pages (cart, checkout, customer account, ...): inline
 *    the active quote_id / customer_id / customer_name directly into the
 *    HTML so the widget picks them up immediately on first send, no
 *    extra round-trip needed.
 *  - Cacheable pages (catalog, CMS, ...): leave the values empty in the
 *    cached HTML and let the JS snippet fetch them from the
 *    `chat-widget-context` customer-data section once the page becomes
 *    interactive (Magento hole-punches that section out of the cache).
 *
 * The actual widget script (<script src="…/embed.js">) stays operator-
 * pasted via Stores → Configuration → HTML Head — this block only sets
 * up the config that the embed reads.
 */
class WidgetConfig extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly CustomerIdSigner $customerIdSigner,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * True when the current page response will not be Full-Page-Cached.
     * Determines whether we can safely render dynamic session data inline
     * instead of deferring to the customer-data fetch.
     */
    public function isPageCacheable(): bool
    {
        $layout = $this->getLayout();
        if (method_exists($layout, 'isCacheable')) {
            return (bool) $layout->isCacheable();
        }
        return true;
    }

    /** Active quote (cart) id; 0 when no cart yet. */
    public function getQuoteId(): int
    {
        try {
            return (int) $this->checkoutSession->getQuote()->getId();
        } catch (\Exception) {
            return 0;
        }
    }

    /** Logged-in customer id; 0 for guests. */
    public function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    /**
     * HMAC-signed customer id. Empty for guests or when the install secret
     * is not yet configured. chat_agent treats this as the authority for
     * customer scope and ignores the raw customerId.
     */
    public function getCustomerSignature(): string
    {
        return $this->customerIdSigner->sign($this->getCustomerId());
    }

    /** Logged-in customer's display name; empty for guests. */
    public function getCustomerName(): string
    {
        if ($this->getCustomerId() <= 0) {
            return '';
        }
        try {
            $customer = $this->customerSession->getCustomerData();
            if ($customer) {
                return trim(
                    ((string) $customer->getFirstname()) . ' ' . ((string) $customer->getLastname())
                );
            }
        } catch (\Exception) {
            // fall through
        }
        return '';
    }
}
