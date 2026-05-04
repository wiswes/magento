<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Cart;

use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class CartRemoveTool
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly CartInfoTool $cartInfoTool,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'cart-remove',
        description: 'Removes an item from a cart by item ID. Arguments: itemId (int, required); quoteId (int, required) — pass from session context (state.context.quote_id). Returns the updated cart snapshot. Works for both signed-in customers and guests.'
    )]
    public function remove(int $itemId, int $quoteId): array
    {
        if ($quoteId <= 0) {
            throw new \RuntimeException('cart-remove requires quoteId from session context.');
        }

        $this->cartItemRepository->deleteById($quoteId, $itemId);

        $cart = $this->cartRepository->get($quoteId);
        return $this->cartInfoTool->snapshot($cart, 'cart_removed');
    }
}
