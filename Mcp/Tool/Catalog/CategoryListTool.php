<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use PhpMcp\Server\Attributes\McpTool;

class CategoryListTool
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @return array{categories: list<array<string, mixed>>}
     */
    #[McpTool(
        name: 'category-list',
        description: 'Returns the category tree as a nested list of {name, url_key, children}. Arguments: rootCategoryName (string, optional — name of the category to use as root, e.g. "Men"; defaults to the store root); depth (int, default 2, max 3).'
    )]
    public function list(string $rootCategoryName = '', int $depth = 2): array
    {
        $depth = max(1, min($depth, 3));

        $storeRootId = (int) $this->storeManager->getStore()->getRootCategoryId();

        if ($rootCategoryName === '') {
            $root = $this->categoryRepository->get($storeRootId);
        } else {
            $root = $this->findCategoryByName($rootCategoryName, $storeRootId);
        }
        $rootLevel = (int) $root->getLevel();
        $maxLevel = $rootLevel + $depth;

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key'])
            ->addFieldToFilter('path', ['like' => $root->getPath() . '/%'])
            ->addFieldToFilter('level', ['lteq' => $maxLevel])
            ->setOrder('position', 'ASC');

        $flat = [];
        $parents = [];
        foreach ($collection as $cat) {
            $id = (int) $cat->getId();
            $flat[$id] = [
                'name'     => (string) $cat->getName(),
                'url_key'  => (string) $cat->getUrlKey(),
                'children' => [],
            ];
            $parents[$id] = (int) $cat->getParentId();
        }

        $tree = [];
        foreach ($flat as $id => &$node) {
            $parentId = $parents[$id];
            if (isset($flat[$parentId])) {
                $flat[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return ['categories' => $tree];
    }

    private function findCategoryByName(string $name, int $storeRootId): CategoryInterface
    {
        $storeRoot = $this->categoryRepository->get($storeRootId);

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name'])
            ->addFieldToFilter('path', ['like' => $storeRoot->getPath() . '/%'])
            ->addAttributeToFilter('name', $name)
            ->setOrder('level', 'ASC')
            ->setPageSize(1);

        $match = $collection->getFirstItem();
        if (!$match->getId()) {
            throw new \RuntimeException(sprintf('Category "%s" not found.', $name));
        }

        return $this->categoryRepository->get((int) $match->getId());
    }
}
