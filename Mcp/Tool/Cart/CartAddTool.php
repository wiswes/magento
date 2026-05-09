<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionInterfaceFactory;
use Magento\Quote\Api\Data\ProductOptionExtensionFactory;
use Magento\ConfigurableProduct\Api\Data\ConfigurableItemOptionValueInterfaceFactory;
use Magento\Bundle\Api\Data\BundleOptionInterfaceFactory;
use Magento\Catalog\Api\Data\CustomOptionInterfaceFactory;
use Magento\Downloadable\Api\Data\DownloadableOptionInterfaceFactory;
use PhpMcp\Server\Attributes\McpTool;

class CartAddTool
{
    public function __construct(
        private readonly CartItemRepositoryInterface $cartItemRepository,
        private readonly CartItemInterfaceFactory $cartItemFactory,
        private readonly ProductOptionInterfaceFactory $productOptionFactory,
        private readonly ProductOptionExtensionFactory $optionExtensionFactory,
        private readonly ConfigurableItemOptionValueInterfaceFactory $configurableOptionFactory,
        private readonly BundleOptionInterfaceFactory $bundleOptionFactory,
        private readonly CustomOptionInterfaceFactory $customOptionFactory,
        private readonly DownloadableOptionInterfaceFactory $downloadableOptionFactory,
        private readonly CartInfoTool $cartInfoTool,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ConfigurableType $configurableType,
    ) {
    }

    /**
     * Note the docblock types `object|null` for $options instead of
     * `array<string, mixed>|null`: php-mcp's SchemaGenerator maps any PHP
     * `array` (including associative ones) to JSON Schema `array`, but the
     * LLM passes `options` as a JSON object — the schema validator then
     * rejects the call. Declaring it as `object|null` here yields a JSON
     * schema of `["object", "null"]`, which matches the actual payload
     * shape. The PHP signature stays `?array` because PHP decodes JSON
     * objects into associative arrays.
     *
     * @param object|null $options Product-type-specific options object.
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'cart-add',
        description: <<<'DESC'
Adds a product to the cart (works for guests and signed-in customers).

Arguments: sku (string, required — the parent SKU for configurable products); quoteId (int, REQUIRED — pass the quote_id from your session context. Returned in every cart-* response under context.quote_id and threaded into your state automatically); qty (float, default 1); options (object, optional — product-type specific).

For configurable products: pass options.configurable as an array of {attribute_id, value_index} where attribute_id is the configurable attribute's id (e.g. 144 for size, 93 for color) and value_index is the chosen option's value_index (e.g. 167 for "S", 49 for "Black"). Both are integers — DO NOT pass labels like "S" or "Black", and DO NOT confuse value_index with attribute_id. If you call cart-add for a configurable parent without all required attributes, the response is {needs_options: true, attributes: [...]} listing exactly which attribute_id/value_index pairs to ask the customer about — confirm with them, then re-call with the chosen pairs.

For other product types the failing response/error will tell you the right shape (e.g. options.bundle = [{option_id, option_qty, option_selections: [int]}], options.custom = [{option_id, option_value}], options.downloadable = [{link_id}]).

Returns the updated cart snapshot.
DESC
    )]
    public function add(string $sku, float $qty = 1, ?array $options = null, int $quoteId = 0): array
    {
        if ($quoteId <= 0) {
            throw new \RuntimeException(
                'cart-add requires quoteId. Pass the quote_id from your session context '
                . '(returned by cart-info / cart-add / any cart-* response under context.quote_id). '
                . 'Do NOT call cart-add without it — that would silently start a new cart and '
                . 'lose any items the customer already had.'
            );
        }

        $missingOptions = $this->missingConfigurableOptions($sku, $options['configurable'] ?? []);
        if ($missingOptions !== null) {
            return [
                'needs_options' => true,
                'sku'           => $sku,
                'message'       => 'Configurable product — confirm each attribute value with the customer, then re-call cart-add with options.configurable = [{attribute_id: <int>, value_index: <int>}, ...]. Use the attribute_id and value_index integers from the attributes[] list below; do NOT pass labels.',
                'attributes'    => $missingOptions,
            ];
        }

        $cart = $this->cartInfoTool->resolveCart($quoteId);
        $cartId = (int) $cart->getId();

        /** @var CartItemInterface $item */
        $item = $this->cartItemFactory->create();
        $item->setSku($sku);
        $item->setQty($qty);
        $item->setQuoteId($cartId);

        if ($options !== null) {
            $item->setProductOption($this->buildProductOption($options));
        }

        try {
            $this->cartItemRepository->save($item);
        } catch (\Exception $e) {
            // Translate Magento's option-validation failures into shape hints
            // the LLM can act on without us bloating the tool description.
            throw new \RuntimeException($this->describeAddFailure($sku, $options, $e), 0, $e);
        }

        // Re-load to include the freshly added item in the snapshot.
        $cart = $this->cartInfoTool->resolveCart($cartId);
        return $this->cartInfoTool->snapshot($cart, 'cart_added');
    }

    /**
     * Build an LLM-actionable error message based on the product type — so the
     * LLM learns the right `options` shape from the failure instead of from a
     * pre-bloated tool description.
     */
    private function describeAddFailure(string $sku, ?array $options, \Exception $e): string
    {
        try {
            $product = $this->productRepository->get($sku);
            $type = (string) $product->getTypeId();
        } catch (\Exception) {
            $type = 'unknown';
        }

        $shape = match ($type) {
            'bundle'       => 'Pass options.bundle = [{option_id, option_qty, option_selections: [int]}, ...].',
            'downloadable' => 'Pass options.downloadable = [{link_id}, ...].',
            'simple', 'virtual' => 'If this product has custom options pass options.custom = [{option_id, option_value}, ...]; otherwise no options are needed.',
            default        => 'Check the product type and pass the appropriate options object.',
        };

        return sprintf(
            "cart-add failed for SKU '%s' (type: %s): %s. %s",
            $sku,
            $type,
            $e->getMessage(),
            $shape
        );
    }

    /**
     * Returns the list of configurable attributes the LLM still needs to
     * confirm with the customer, or null when nothing is missing (either the
     * SKU is not a configurable product, or every required attribute is
     * already covered by $supplied).
     *
     * @param array<int, array{attribute_id?: int|string, value_index?: int|string}> $supplied
     * @return list<array<string, mixed>>|null
     */
    private function missingConfigurableOptions(string $sku, array $supplied): ?array
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (\Exception $e) {
            return null;
        }

        if ($product->getTypeId() !== ConfigurableType::TYPE_CODE) {
            return null;
        }

        $providedAttributeIds = [];
        foreach ($supplied as $opt) {
            if (isset($opt['attribute_id'])) {
                $providedAttributeIds[(int) $opt['attribute_id']] = true;
            }
        }

        $children = $this->configurableType->getUsedProducts($product);

        $missing = [];
        foreach ($this->configurableType->getConfigurableAttributes($product) as $attr) {
            $eav = $attr->getProductAttribute();
            $attributeId = (int) $eav->getId();
            if (isset($providedAttributeIds[$attributeId])) {
                continue;
            }

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

            $missing[] = [
                'attribute_id'   => $attributeId,
                'attribute_code' => $code,
                'label'          => (string) $attr->getLabel(),
                'values'         => $values,
            ];
        }

        return $missing === [] ? null : $missing;
    }

    /** @param array<string, mixed> $options */
    private function buildProductOption(array $options): \Magento\Quote\Api\Data\ProductOptionInterface
    {
        $productOption = $this->productOptionFactory->create();
        $ext = $this->optionExtensionFactory->create();

        if (isset($options['configurable'])) {
            $configurableOptions = [];
            foreach ($options['configurable'] as $opt) {
                // LLM-facing keys are attribute_id/value_index (matching the
                // shape we hand back in needs_options). Magento's
                // ConfigurableItemOptionValue API expects option_id/option_value
                // — same concepts, different names — so translate here.
                $attributeId = (int) ($opt['attribute_id'] ?? $opt['option_id'] ?? 0);
                $valueIndex  = (int) ($opt['value_index']  ?? $opt['option_value'] ?? 0);
                $co = $this->configurableOptionFactory->create();
                $co->setOptionId($attributeId);
                $co->setOptionValue($valueIndex);
                $configurableOptions[] = $co;
            }
            $ext->setConfigurableItemOptions($configurableOptions);
        }

        if (isset($options['bundle'])) {
            $bundleOptions = [];
            foreach ($options['bundle'] as $opt) {
                $bo = $this->bundleOptionFactory->create();
                $bo->setOptionId($opt['option_id']);
                $bo->setOptionQty($opt['option_qty'] ?? 1);
                $bo->setOptionSelections($opt['option_selections'] ?? []);
                $bundleOptions[] = $bo;
            }
            $ext->setBundleOptions($bundleOptions);
        }

        if (isset($options['custom'])) {
            $customOptions = [];
            foreach ($options['custom'] as $opt) {
                $co = $this->customOptionFactory->create();
                $co->setOptionId($opt['option_id']);
                $co->setOptionValue($opt['option_value']);
                $customOptions[] = $co;
            }
            $ext->setCustomOptions($customOptions);
        }

        if (isset($options['downloadable'])) {
            $linkIds = array_map(fn($link) => (int) $link['link_id'], $options['downloadable']);
            $dlOption = $this->downloadableOptionFactory->create();
            $dlOption->setDownloadableLinks($linkIds);
            $ext->setDownloadableOption($dlOption);
        }

        $productOption->setExtensionAttributes($ext);
        return $productOption;
    }
}
