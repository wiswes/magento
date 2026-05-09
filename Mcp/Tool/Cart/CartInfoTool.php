<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Cart;

use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use PhpMcp\Server\Attributes\McpTool;

class CartInfoTool
{
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartTotalRepositoryInterface $totalsRepository,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'cart-info',
        description: 'Returns the active cart in one shot — header, line items with options, and totals (subtotal, discount, shipping, tax, grand_total, currency). Argument: quoteId (int, optional) — pass the cart_id from session context if you have one. If omitted, returns an empty cart snapshot. Works for both signed-in customers and guests.'
    )]
    public function info(int $quoteId = 0): array
    {
        if ($quoteId <= 0) {
            // No cart yet. Return a `text` field so chat_agent's
            // extract_llm_content surfaces a clear human-readable string to
            // the LLM ("the cart is empty, tell the user") instead of a
            // bag of zeroes the model loops over trying to interpret.
            return [
                'text'        => 'The cart is empty — there are no items yet. '
                                . 'Tell the customer plainly and offer to help them add a product.',
                'cart_id'     => 0,
                'items_count' => 0,
                'items'       => [],
                'totals'      => null,
                'context'     => ['quote_id' => 0],
            ];
        }
        $cart = $this->cartRepository->get($quoteId);
        return $this->snapshot($cart);
    }

    /**
     * Resolve the cart for a tool call: load by id when one is supplied,
     * otherwise create a new (guest) cart so the call has somewhere to write.
     * Caller should propagate the returned cart id back into session context
     * (snapshot() includes it under `context.quote_id`).
     */
    public function resolveCart(int $quoteId): CartInterface
    {
        if ($quoteId > 0) {
            return $this->cartRepository->get($quoteId);
        }
        $newId = $this->cartManagement->createEmptyCart();
        return $this->cartRepository->get($newId);
    }

    /**
     * @param string|null $actionType  Set on write operations (cart_added /
     * cart_updated / cart_removed) so chat_agent emits a `chat:action` SSE
     * event the storefront can listen for to refresh the minicart. Read-only
     * `cart-info` calls leave it null — no UI side effect.
     * @return array<string, mixed>
     */
    public function snapshot(CartInterface $cart, ?string $actionType = null): array
    {
        $cartId = (int) $cart->getId();
        $totals = $this->totalsRepository->get($cartId);
        $itemsCount = (int) $cart->getItemsCount();

        $payload = [
            'cart_id'     => $cartId,
            'items_count' => $itemsCount,
            'items'       => $this->lineItems($cart),
            'totals'      => $this->totals($totals),
            // Threaded back into chat_agent's state.context.quote_id by
            // ResponseParser so subsequent cart-* calls reuse the same cart.
            'context'     => ['quote_id' => $cartId],
        ];
        // Same rationale as the empty-cart branch in info(): give the LLM
        // a clear text signal for read-only snapshots so an empty cart
        // doesn't drive it into a re-call loop. Skipped on write ops
        // (cart_added / cart_updated / cart_removed) — those carry their
        // own action and already include line items.
        if ($actionType === null && $itemsCount === 0) {
            $payload['text'] = 'The cart is empty — there are no items yet. '
                . 'Tell the customer plainly and offer to help them add a product.';
        }
        if ($actionType !== null) {
            $payload['action'] = [
                'type'        => $actionType,
                'cart_id'     => $cartId,
                'items_count' => $itemsCount,
            ];
        }
        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function lineItems(CartInterface $cart): array
    {
        $items = [];
        foreach ($cart->getItems() ?? [] as $item) {
            $items[] = [
                'id'        => (int) $item->getItemId(),
                'sku'       => (string) $item->getSku(),
                'name'      => (string) $item->getName(),
                'qty'       => (float) $item->getQty(),
                'price'     => (float) $item->getPrice(),
                'row_total' => (float) $item->getRowTotal(),
            ];
        }
        return $items;
    }

    /** @return array<string, mixed> */
    private function totals(TotalsInterface $totals): array
    {
        return [
            'subtotal'    => (float) $totals->getSubtotal(),
            'discount'    => (float) $totals->getDiscountAmount(),
            'shipping'    => (float) $totals->getShippingAmount(),
            'tax'         => (float) $totals->getTaxAmount(),
            'grand_total' => (float) $totals->getGrandTotal(),
            'currency'    => (string) $totals->getQuoteCurrencyCode(),
        ];
    }
}
