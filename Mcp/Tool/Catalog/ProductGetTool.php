<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Catalog;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use PhpMcp\Server\Attributes\McpTool;

class ProductGetTool
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ConfigurableType $configurableType,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'product-info',
        description: <<<'DESC'
Retrieve a single product by SKU (or numeric ID). Returns the full product payload: id, sku, name, type, status, visibility, price, description, images, custom options, stock.

For configurable products, also returns:
  - configurable.attributes[]: {attribute_id, attribute_code, values: [{value_index, label}]}
  - configurable.variants[]: {sku, price, attributes: [value_index, value_index, ...]}

variants[].attributes is a positional array of value_index entries matching the order of configurable.attributes[]. Example: if attributes = [color, size], a variant with attributes [49, 167] means color=49 ("Red") + size=167 ("M"). Cross-reference value_index → label via attributes[].values to show human-readable choices.

Arguments: sku (string, required unless id is given); id (int, optional — used when SKU is unknown).
DESC
    )]
    public function get(string $sku = '', int $id = 0): array
    {
        $product = $id > 0
            ? $this->productRepository->getById($id)
            : $this->productRepository->get($sku);

        return [
            'id'          => (int) $product->getId(),
            'sku'         => (string) $product->getSku(),
            'name'        => (string) $product->getName(),
            'type_id'     => (string) $product->getTypeId(),
            'status'      => (int) $product->getStatus(),
            'visibility'  => (int) $product->getVisibility(),
            'price'       => (float) $product->getPrice(),
            'special_price'=> $product->getSpecialPrice() !== null ? (float) $product->getSpecialPrice() : null,
            'url_key'     => $product->getCustomAttribute('url_key')?->getValue(),
            'description' => $product->getCustomAttribute('description')?->getValue(),
            'short_description' => $product->getCustomAttribute('short_description')?->getValue(),
            'images'      => $this->images($product),
            'stock'       => $this->stock($product),
            'options'     => $this->options($product),
            'configurable'=> $this->configurable($product),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function images($product): array
    {
        $gallery = $product->getMediaGalleryEntries() ?? [];
        $items = [];
        foreach ($gallery as $entry) {
            $items[] = [
                'id'       => (int) $entry->getId(),
                'file'     => (string) $entry->getFile(),
                'label'    => (string) $entry->getLabel(),
                'position' => (int) $entry->getPosition(),
                'types'    => $entry->getTypes() ?? [],
            ];
        }
        return $items;
    }

    /** @return array<string, mixed>|null */
    private function stock($product): ?array
    {
        $ext = $product->getExtensionAttributes();
        $item = $ext?->getStockItem();
        if ($item === null) {
            return null;
        }
        return [
            'qty'          => (float) $item->getQty(),
            'is_in_stock'  => (bool) $item->getIsInStock(),
            'min_qty'      => (float) $item->getMinQty(),
            'max_sale_qty' => (float) $item->getMaxSaleQty(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function options($product): array
    {
        $opts = $product->getOptions() ?? [];
        $result = [];
        foreach ($opts as $opt) {
            $values = [];
            foreach ($opt->getValues() ?? [] as $v) {
                $values[] = [
                    'value_id' => (int) $v->getOptionTypeId(),
                    'title'    => (string) $v->getTitle(),
                    'price'    => (float) $v->getPrice(),
                    'price_type'=> (string) $v->getPriceType(),
                ];
            }
            $result[] = [
                'option_id' => (int) $opt->getOptionId(),
                'title'     => (string) $opt->getTitle(),
                'type'      => (string) $opt->getType(),
                'required'  => (bool) $opt->getIsRequire(),
                'values'    => $values,
            ];
        }
        return $result;
    }

    /** @return array<string, mixed>|null */
    private function configurable($product): ?array
    {
        if ($product->getTypeId() !== ConfigurableType::TYPE_CODE) {
            return null;
        }

        $children = $this->configurableType->getUsedProducts($product);

        $attributes = [];
        foreach ($this->configurableType->getConfigurableAttributes($product) as $attr) {
            $eav = $attr->getProductAttribute();
            $code = (string) $eav->getAttributeCode();

            $usedValues = [];
            foreach ($children as $child) {
                $val = $child->getData($code);
                if ($val !== null && $val !== '') {
                    $usedValues[(int) $val] = true;
                }
            }

            $values = [];
            foreach ($eav->getSource()->getAllOptions(false) as $option) {
                $idx = (int) $option['value'];
                if (!isset($usedValues[$idx])) {
                    continue;
                }
                $values[] = [
                    'value_index' => $idx,
                    'label'       => (string) $option['label'],
                ];
            }

            $attributes[] = [
                'attribute_id'   => (int) $eav->getId(),
                'attribute_code' => $code,
                'values'         => $values,
            ];
        }

        $variants = [];
        foreach ($children as $child) {
            // Positional value_index list, in the same order as the parent's
            // attributes[] above — saves tokens vs. a per-variant code=>value map.
            $combo = [];
            foreach ($attributes as $attr) {
                $val = $child->getData($attr['attribute_code']);
                $combo[] = $val !== null && $val !== '' ? (int) $val : null;
            }
            $variants[] = [
                'sku'        => (string) $child->getSku(),
                'price'      => (float) $child->getPrice(),
                'attributes' => $combo,
            ];
        }

        return [
            'attributes' => $attributes,
            'variants'   => $variants,
        ];
    }
}
