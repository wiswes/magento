<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use PhpMcp\Server\Attributes\McpTool;

class CustomerAddressUpdateTool
{
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionInterfaceFactory $regionFactory,
        private readonly CustomerScopeResolver $scopeResolver,
        private readonly AddressFormatter $addressFormatter,
    ) {
    }

    /**
     * @param string[]|null $street
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'customer-address-update',
        description: 'Creates or updates a customer address. Pass addressId to update; omit to create. Arguments: addressId (int, optional), customerId (int, optional — required when caller is admin/integration; ignored for customer tokens), firstname (string), lastname (string), street (array of strings), city (string), regionId (int, optional), region (string, optional — free-text region name), postcode (string), countryId (string, 2-letter ISO), telephone (string), defaultBilling (bool, optional), defaultShipping (bool, optional). Returns the saved address.'
    )]
    public function update(
        ?int $addressId = null,
        ?int $customerId = null,
        ?string $firstname = null,
        ?string $lastname = null,
        ?array $street = null,
        ?string $city = null,
        ?int $regionId = null,
        ?string $region = null,
        ?string $postcode = null,
        ?string $countryId = null,
        ?string $telephone = null,
        ?bool $defaultBilling = null,
        ?bool $defaultShipping = null,
    ): array {
        $resolvedCustomerId = $this->scopeResolver->resolve($customerId, 'customer-address-update');

        if ($addressId !== null) {
            $address = $this->addressRepository->getById($addressId);
            if ((int) $address->getCustomerId() !== $resolvedCustomerId) {
                throw new \RuntimeException('Address does not belong to the target customer.');
            }
        } else {
            $address = $this->addressFactory->create();
            $address->setCustomerId($resolvedCustomerId);
        }

        if ($firstname !== null) {
            $address->setFirstname($firstname);
        }
        if ($lastname !== null) {
            $address->setLastname($lastname);
        }
        if ($street !== null) {
            $address->setStreet($street);
        }
        if ($city !== null) {
            $address->setCity($city);
        }
        if ($postcode !== null) {
            $address->setPostcode($postcode);
        }
        if ($countryId !== null) {
            $address->setCountryId($countryId);
        }
        if ($telephone !== null) {
            $address->setTelephone($telephone);
        }
        if ($defaultBilling !== null) {
            $address->setIsDefaultBilling($defaultBilling);
        }
        if ($defaultShipping !== null) {
            $address->setIsDefaultShipping($defaultShipping);
        }

        if ($regionId !== null || $region !== null) {
            $regionObj = $this->regionFactory->create();
            if ($regionId !== null) {
                $regionObj->setRegionId($regionId);
            }
            if ($region !== null) {
                $regionObj->setRegion($region);
            }
            $address->setRegion($regionObj);
        }

        $saved = $this->addressRepository->save($address);
        return $this->addressFormatter->compact($saved);
    }
}
