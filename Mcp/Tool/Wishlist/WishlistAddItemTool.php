<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Wishlist;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Wishlist\Model\WishlistFactory;
use PhpMcp\Server\Attributes\McpTool;

class WishlistAddItemTool
{
    public function __construct(
        private readonly WishlistFactory $wishlistFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserContextInterface $userContext,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'wishlist-add-item',
        description: 'Adds a product to the authenticated customer\'s wishlist. Arguments: sku (string, required); qty (float, default 1). Returns {success, item_id, sku, wishlist_count}. Customer bearer token required.'
    )]
    public function add(string $sku, float $qty = 1): array
    {
        if ($this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER) {
            throw new \RuntimeException('wishlist-add-item requires a customer-scoped bearer token.');
        }
        $customerId = (int) $this->userContext->getUserId();

        $product = $this->productRepository->get($sku);
        $wishlist = $this->wishlistFactory->create()->loadByCustomerId($customerId, true);

        $buyRequest = new DataObject(['qty' => $qty]);
        $item = $wishlist->addNewItem($product, $buyRequest);

        if (is_string($item)) {
            throw new \RuntimeException('Failed to add to wishlist: ' . $item);
        }

        $wishlist->save();

        return [
            'success'        => true,
            'item_id'        => (int) $item->getId(),
            'sku'            => $sku,
            'wishlist_count' => (int) $wishlist->getItemsCount(),
        ];
    }
}
