<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\Data\AddressInterface;

class AddressFormatter
{
    /** @return array<string, mixed> */
    public function compact(AddressInterface $a): array
    {
        return [
            'id'               => (int) $a->getId(),
            'firstname'        => (string) $a->getFirstname(),
            'lastname'         => (string) $a->getLastname(),
            'street'           => $a->getStreet() ?? [],
            'city'             => (string) $a->getCity(),
            'region'           => $a->getRegion()?->getRegion(),
            'region_id'        => $a->getRegion()?->getRegionId(),
            'postcode'         => (string) $a->getPostcode(),
            'country_id'       => (string) $a->getCountryId(),
            'telephone'        => (string) $a->getTelephone(),
            'default_billing'  => (bool) $a->isDefaultBilling(),
            'default_shipping' => (bool) $a->isDefaultShipping(),
        ];
    }
}
