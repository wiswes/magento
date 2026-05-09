<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\AddressRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class CustomerAddressRemoveTool
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly CustomerScopeResolver $scopeResolver,
    ) {
    }

    /** @return array{success: bool, addressId: int} */
    #[McpTool(
        name: 'customer-address-remove',
        description: 'Deletes a customer address by ID. The address must belong to the target customer. Arguments: addressId (int, required), customerId (int, optional — required when caller is admin/integration; ignored for customer tokens). Returns {success: true, addressId}.'
    )]
    public function remove(int $addressId, ?int $customerId = null): array
    {
        $resolvedCustomerId = $this->scopeResolver->resolve($customerId, 'customer-address-remove');

        $address = $this->addressRepository->getById($addressId);
        if ((int) $address->getCustomerId() !== $resolvedCustomerId) {
            throw new \RuntimeException('Address does not belong to the target customer.');
        }

        $this->addressRepository->deleteById($addressId);

        return [
            'success'   => true,
            'addressId' => $addressId,
        ];
    }
}
