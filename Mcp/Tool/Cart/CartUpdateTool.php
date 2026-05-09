<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Cart;

use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CouponManagementInterface;
use PhpMcp\Server\Attributes\McpTool;

class CartUpdateTool
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly CouponManagementInterface $couponManagement,
        private readonly CartInfoTool $cartInfoTool,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'cart-update',
        description: 'Updates a cart. Two modes: (1) update item quantity — pass itemId (int) + qty (float); (2) manage coupon — pass couponCode (string) to apply or removeCoupon (true) to remove. Pass quoteId (int, required) from session context (state.context.quote_id). Returns the updated cart snapshot. Works for both signed-in customers and guests.'
    )]
    public function update(
        int $quoteId,
        ?int $itemId = null,
        ?float $qty = null,
        ?string $couponCode = null,
        bool $removeCoupon = false,
    ): array {
        if ($quoteId <= 0) {
            throw new \RuntimeException('cart-update requires quoteId from session context.');
        }

        $cart = $this->cartRepository->get($quoteId);

        if ($itemId !== null && $qty !== null) {
            $item = null;
            foreach ($cart->getItems() ?? [] as $cartItem) {
                if ((int) $cartItem->getItemId() === $itemId) {
                    $item = $cartItem;
                    break;
                }
            }
            if ($item === null) {
                throw new \RuntimeException(sprintf('Item %d not found in cart.', $itemId));
            }
            $item->setQty($qty);
            $this->cartItemRepository->save($item);
        } elseif ($removeCoupon) {
            $this->couponManagement->remove($quoteId);
        } elseif ($couponCode !== null) {
            $this->couponManagement->set($quoteId, $couponCode);
        } else {
            throw new \RuntimeException('Provide either (itemId + qty) or couponCode or removeCoupon.');
        }

        $cart = $this->cartRepository->get($quoteId);
        return $this->cartInfoTool->snapshot($cart, 'cart_updated');
    }
}
