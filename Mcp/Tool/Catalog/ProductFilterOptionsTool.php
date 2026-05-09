<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Catalog;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use PhpMcp\Server\Attributes\McpTool;

class ProductFilterOptionsTool
{
    public function __construct(
        private readonly ProductAttributeRepositoryInterface $attributeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    private const MAX_OPTIONS_PER_ATTRIBUTE = 10;

    /**
     * @return array{filters: list<array<string, mixed>>}
     */
    #[McpTool(
        name: 'product-filter-options',
        description: 'Lists dropdown/select product attributes flagged as filterable in the Magento admin, with their valid option values. Always call this BEFORE product-filter to discover acceptable field names and values. Returns {filters: [{code, label, type, options: [{value, label}]}]}. options[] is capped at 10 entries per attribute.'
    )]
    public function options(): array
    {
        // Surface every attribute flagged "Filterable in layered nav".
        // RequestGeneratorPlugin promotes all such attributes into
        // quick_search_container as well, so the same filters work whether
        // the caller passed a phrase or not.
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('is_filterable', [1, 2], 'in')
            ->addFilter('frontend_input', ['select', 'multiselect'], 'in')
            ->create();

        $items = [];
        foreach ($this->attributeRepository->getList($criteria)->getItems() as $attribute) {
            $opts = [];
            foreach ($attribute->getOptions() ?? [] as $opt) {
                $value = (string) $opt->getValue();
                if ($value === '') {
                    continue;
                }
                $opts[] = [
                    'value' => $value,
                    'label' => (string) $opt->getLabel(),
                ];
                if (count($opts) >= self::MAX_OPTIONS_PER_ATTRIBUTE) {
                    break;
                }
            }
            $items[] = [
                'code'    => (string) $attribute->getAttributeCode(),
                'label'   => (string) $attribute->getDefaultFrontendLabel(),
                'type'    => (string) $attribute->getFrontendInput(),
                'options' => $opts,
            ];
        }

        return ['filters' => $items];
    }
}
