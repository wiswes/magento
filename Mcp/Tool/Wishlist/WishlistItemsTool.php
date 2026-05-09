<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Wishlist;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Wishlist\Model\WishlistFactory;
use PhpMcp\Server\Attributes\McpTool;

class WishlistItemsTool
{
    public function __construct(
        private readonly WishlistFactory $wishlistFactory,
        private readonly UserContextInterface $userContext,
    ) {
    }

    /** @return array{customer_id: int, items: list<array<string, mixed>>} */
    #[McpTool(
        name: 'wishlist-items',
        description: 'Returns all items in the authenticated customer\'s wishlist. Each item includes item_id, product_id, sku, name, qty, added_at, and store_id. No arguments. Customer bearer token required.'
    )]
    public function items(): array
    {
        if ($this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER) {
            throw new \RuntimeException('wishlist-items requires a customer-scoped bearer token.');
        }
        $customerId = (int) $this->userContext->getUserId();

        $wishlist = $this->wishlistFactory->create()->loadByCustomerId($customerId, true);

        $items = [];
        foreach ($wishlist->getItemCollection() as $item) {
            $product = $item->getProduct();
            $items[] = [
                'item_id'    => (int) $item->getId(),
                'product_id' => (int) $item->getProductId(),
                'sku'        => (string) $product->getSku(),
                'name'       => (string) $product->getName(),
                'qty'        => (float) $item->getQty(),
                'added_at'   => (string) $item->getAddedAt(),
                'store_id'   => (int) $item->getStoreId(),
            ];
        }

        return [
            'customer_id' => $customerId,
            'items'       => $items,
        ];
    }
}
