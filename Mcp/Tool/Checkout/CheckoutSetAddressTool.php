<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Checkout;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Checkout\Api\GuestShippingInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\GuestBillingAddressManagementInterface;
use PhpMcp\Server\Attributes\McpTool;

class CheckoutSetAddressTool
{
    public function __construct(
        private readonly McpUserContext $userContext,
        private readonly CartManagementInterface $cartManagement,
        private readonly BillingAddressManagementInterface $billingAddressManagement,
        private readonly GuestBillingAddressManagementInterface $guestBillingAddressManagement,
        private readonly ShippingInformationManagementInterface $shippingInfoManagement,
        private readonly GuestShippingInformationManagementInterface $guestShippingInfoManagement,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly ShippingInformationInterfaceFactory $shippingInfoFactory,
    ) {
    }

    /**
     * @param string[]|null $street
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'checkout-set-address',
        description: 'Set billing or shipping address on the cart. Arguments: type ("billing"|"shipping"); firstname, lastname, street, city, postcode, countryId, telephone; regionId or region (optional); cartId (required for guests); shippingCarrierCode + shippingMethodCode (required when type="shipping"); email (optional).'
    )]
    public function setAddress(
        string $type,
        string $firstname,
        string $lastname,
        array $street,
        string $city,
        string $postcode,
        string $countryId,
        string $telephone,
        ?int $regionId = null,
        ?string $region = null,
        ?string $cartId = null,
        ?string $shippingCarrierCode = null,
        ?string $shippingMethodCode = null,
        ?string $email = null,
    ): array {
        $isCustomer = $this->userContext->isAuthenticated()
            && $this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER;

        $fields = [
            'firstname'  => $firstname,
            'lastname'   => $lastname,
            'street'     => $street,
            'city'       => $city,
            'postcode'   => $postcode,
            'countryId'  => $countryId,
            'telephone'  => $telephone,
            'regionId'   => $regionId,
            'region'     => $region,
            'email'      => $email,
        ];

        if ($type === 'billing') {
            return $this->setBilling($this->buildAddress($fields), $isCustomer, $cartId);
        }

        // Mint a separate billing address from the same field set so the
        // single shipping call also seeds the billing form on /checkout —
        // ShippingInformationManagement::saveAddressInformation() persists
        // billing when it's present on the ShippingInformation payload.
        // A fresh factory instance is required because the same
        // AddressInterface can't simultaneously be both shipping and
        // billing (each maps to its own quote_address row).
        return $this->setShipping(
            $this->buildAddress($fields),
            $this->buildAddress($fields),
            $isCustomer,
            $cartId,
            $shippingCarrierCode,
            $shippingMethodCode,
        );
    }

    /**
     * @param array{firstname:string,lastname:string,street:string[],city:string,postcode:string,countryId:string,telephone:string,regionId:?int,region:?string,email:?string} $fields
     */
    private function buildAddress(array $fields)
    {
        $address = $this->addressFactory->create();
        $address->setFirstname($fields['firstname']);
        $address->setLastname($fields['lastname']);
        $address->setStreet($fields['street']);
        $address->setCity($fields['city']);
        $address->setPostcode($fields['postcode']);
        $address->setCountryId($fields['countryId']);
        $address->setTelephone($fields['telephone']);
        if ($fields['email'] !== null) {
            $address->setEmail($fields['email']);
        }
        if ($fields['regionId'] !== null) {
            $address->setRegionId($fields['regionId']);
        }
        if ($fields['region'] !== null) {
            $address->setRegion($fields['region']);
        }
        return $address;
    }

    /**
     * chat_agent's admin/integration token isn't a customer, so the bare
     * customer/guest fork would dump every call into the guest API — which
     * resolves cartIds through the masked-token table and explodes on the
     * numeric quote_id chat_agent threads in. Resolve to int cartId first
     * and only use the guest API for true masked tokens.
     */
    private function resolveIntCartId(bool $isCustomer, ?string $cartId): ?int
    {
        if ($isCustomer) {
            return (int) $this->cartManagement->getCartForCustomer($this->userContext->getUserId())->getId();
        }
        if ($cartId !== null && ctype_digit($cartId)) {
            return (int) $cartId;
        }
        return null;
    }

    private function setBilling($address, bool $isCustomer, ?string $cartId): array
    {
        $intCartId = $this->resolveIntCartId($isCustomer, $cartId);
        if ($intCartId !== null) {
            $assignedId = $this->billingAddressManagement->assign($intCartId, $address);
        } else {
            if ($cartId === null) {
                throw new \RuntimeException('cartId is required for guest checkout.');
            }
            $assignedId = $this->guestBillingAddressManagement->assign($cartId, $address);
        }

        return [
            'success'    => true,
            'address_id' => $assignedId,
        ];
    }

    private function setShipping($shippingAddress, $billingAddress, bool $isCustomer, ?string $cartId, ?string $carrierCode, ?string $methodCode): array
    {
        if ($carrierCode === null || $methodCode === null) {
            throw new \RuntimeException('shippingCarrierCode and shippingMethodCode are required for type=shipping.');
        }

        $shippingInfo = $this->shippingInfoFactory->create();
        $shippingInfo->setShippingAddress($shippingAddress);
        $shippingInfo->setBillingAddress($billingAddress);
        $shippingInfo->setShippingCarrierCode($carrierCode);
        $shippingInfo->setShippingMethodCode($methodCode);

        $intCartId = $this->resolveIntCartId($isCustomer, $cartId);
        if ($intCartId !== null) {
            $result = $this->shippingInfoManagement->saveAddressInformation($intCartId, $shippingInfo);
        } else {
            if ($cartId === null) {
                throw new \RuntimeException('cartId is required for guest checkout.');
            }
            $result = $this->guestShippingInfoManagement->saveAddressInformation($cartId, $shippingInfo);
        }

        $totals = $result->getTotals();
        return [
            'success'       => true,
            'payment_methods' => array_map(fn($m) => ['code' => $m->getCode(), 'title' => $m->getTitle()], $result->getPaymentMethods()),
            'totals'        => [
                'grand_total' => (float) $totals->getGrandTotal(),
                'subtotal'    => (float) $totals->getSubtotal(),
                'shipping'    => (float) $totals->getShippingAmount(),
                'tax'         => (float) $totals->getTaxAmount(),
                'currency'    => (string) $totals->getQuoteCurrencyCode(),
            ],
        ];
    }
}
