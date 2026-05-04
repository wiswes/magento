<?php
declare(strict_types=1);

namespace WisWes\MCP\Plugin\Search;

use Magento\CatalogSearch\Model\Search\RequestGenerator;

/**
 * Magento's RequestGenerator emits dynamic filter declarations for product
 * attributes based on TWO different flags depending on the request:
 *
 *   - catalog_view_container  ← attributes with is_filterable IN (1, 2)
 *   - quick_search_container  ← attributes with is_filterable_in_search = 1
 *
 * That asymmetry means an attribute flagged "Filterable in layered nav"
 * but NOT "Filterable in search results layered nav" works as a filter
 * when no phrase is passed (catalog_view_container) and is silently
 * dropped from the request schema when a phrase IS passed
 * (quick_search_container) — the search engine ignores the unknown filter
 * and returns the unfiltered phrase results.
 *
 * For our MCP product-filter tool we want consistent behaviour: any
 * filterable attribute should work as a filter regardless of whether the
 * caller also passed a free-text phrase. We achieve this by copying every
 * filter / query / aggregation declaration the generator emitted for
 * catalog_view_container into quick_search_container, and wiring the
 * promoted queries into the main quick_search_container bool query so the
 * search engine actually evaluates them.
 *
 * The cached search request config must be flushed after this plugin is
 * registered (`bin/magento cache:flush`).
 */
class RequestGeneratorPlugin
{
    private const CATALOG = 'catalog_view_container';
    private const QUICK = 'quick_search_container';

    /**
     * @param array<string, array<string, mixed>> $result
     * @return array<string, array<string, mixed>>
     */
    public function afterGenerate(RequestGenerator $subject, array $result): array
    {
        if (!isset($result[self::CATALOG], $result[self::QUICK])) {
            return $result;
        }

        $catalog = $result[self::CATALOG];
        $quick = $result[self::QUICK];

        foreach (['queries', 'filters', 'aggregations'] as $section) {
            if (!isset($catalog[$section])) {
                continue;
            }
            $quick[$section] = $quick[$section] ?? [];
            foreach ($catalog[$section] as $key => $entry) {
                // Don't overwrite the container's own root query (each
                // container has a top-level entry named after itself).
                if ($key === self::CATALOG) {
                    continue;
                }
                if (!isset($quick[$section][$key])) {
                    $quick[$section][$key] = $entry;
                }
            }
        }

        // Promote each catalog_view_container query reference into the
        // quick_search_container bool query so Opensearch actually applies
        // the new filters. Without this step the filter declarations exist
        // but no clause references them.
        $catalogRefs = $catalog['queries'][self::CATALOG]['queryReference'] ?? [];
        if ($catalogRefs !== []) {
            $quick['queries'][self::QUICK]['queryReference'] = $quick['queries'][self::QUICK]['queryReference'] ?? [];
            $existing = [];
            foreach ($quick['queries'][self::QUICK]['queryReference'] as $ref) {
                if (isset($ref['ref'])) {
                    $existing[$ref['ref']] = true;
                }
            }
            foreach ($catalogRefs as $ref) {
                if (!isset($ref['ref']) || isset($existing[$ref['ref']])) {
                    continue;
                }
                $quick['queries'][self::QUICK]['queryReference'][] = $ref;
                $existing[$ref['ref']] = true;
            }
        }

        $result[self::QUICK] = $quick;
        return $result;
    }
}
