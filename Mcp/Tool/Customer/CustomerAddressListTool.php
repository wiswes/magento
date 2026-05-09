<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class CustomerAddressListTool
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerScopeResolver $scopeResolver,
        private readonly AddressFormatter $addressFormatter,
    ) {
    }

    /** @return array{customer_id: int, addresses: list<array<string, mixed>>} */
    #[McpTool(
        name: 'customer-address-list',
        description: 'Returns all saved addresses for a customer. Each address includes id, name, street, city, region, postcode, country_id, telephone, and default_billing/default_shipping flags. Argument: customerId (int, optional — required when caller is admin/integration; ignored for customer tokens).'
    )]
    public function list(?int $customerId = null): array
    {
        $resolvedCustomerId = $this->scopeResolver->resolve($customerId, 'customer-address-list');
        $customer = $this->customerRepository->getById($resolvedCustomerId);

        $addresses = [];
        foreach ($customer->getAddresses() ?? [] as $addr) {
            $addresses[] = $this->addressFormatter->compact($addr);
        }

        return [
            'customer_id' => $resolvedCustomerId,
            'addresses'   => $addresses,
        ];
    }
}
