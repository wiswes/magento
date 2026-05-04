<?php
declare(strict_types=1);

namespace WisWes\MCP\CustomerData;

use WisWes\MCP\Model\Auth\CustomerIdSigner;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Customer-data section that exposes the active cart id and signed-in
 * customer info to the chat widget. Magento hole-punches this section
 * out of the full-page cache so cached pages can still pick up live
 * session state via JS once the page is interactive.
 *
 * If the visitor has no quote yet, we eagerly create+persist a guest cart
 * here and stash it on the checkout session. That way the chat agent and
 * the storefront converge on the SAME cart from the customer's first
 * meaningful interaction, instead of the agent creating a side cart the
 * minicart never sees.
 *
 * Surfaced under the section name `chat-widget-context` (see etc/frontend/di.xml).
 */
class ChatWidgetContext implements SectionSourceInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CustomerIdSigner $customerIdSigner,
    ) {}

    /** @return array<string, mixed> */
    public function getSectionData(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $quoteId = $this->resolveOrCreateQuoteId($customerId);

        $customerName = '';
        if ($customerId > 0) {
            try {
                $customer = $this->customerSession->getCustomerData();
                if ($customer) {
                    $customerName = trim(
                        ((string) $customer->getFirstname()) . ' ' . ((string) $customer->getLastname())
                    );
                }
            } catch (\Exception) {
                // Best-effort — name is non-essential.
            }
        }

        return [
            'quote_id'           => $quoteId,
            'customer_id'        => $customerId,
            'customer_name'      => $customerName,
            // HMAC-signed customer id. chat_agent verifies the signature
            // against the tenant's install secret and ignores the raw
            // customer_id field above — that one is JS-mutable and cannot
            // be trusted as the authority. Empty string for guests.
            'customer_signature' => $this->customerIdSigner->sign($customerId),
        ];
    }

    /**
     * Return the active quote id, creating + persisting a new (customer or
     * guest) quote on the spot if none exists yet. The new quote id is
     * pinned onto the checkout session so subsequent storefront reads
     * (and the chat agent's cart-* MCP calls, once threaded through) see
     * the same cart instead of each call minting a fresh one.
     */
    private function resolveOrCreateQuoteId(int $customerId): int
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $quoteId = (int) $quote->getId();
            if ($quoteId > 0) {
                return $quoteId;
            }
        } catch (\Exception) {
            // Fall through and create one.
        }

        try {
            $newId = $customerId > 0
                ? (int) $this->cartManagement->createEmptyCartForCustomer($customerId)
                : (int) $this->cartManagement->createEmptyCart();

            if ($newId <= 0) {
                return 0;
            }

            // Pin the freshly-minted cart onto the checkout session so this
            // browser session reuses it on every subsequent request.
            $cart = $this->cartRepository->get($newId);
            $this->checkoutSession->setQuoteId($newId);
            $this->checkoutSession->replaceQuote($cart);

            return $newId;
        } catch (\Exception) {
            // Cart creation can fail under unusual conditions (e.g. disabled
            // store, missing currency). Don't break the page — return 0 and
            // let the chat agent create one later via its own cart-add path.
            return 0;
        }
    }
}
