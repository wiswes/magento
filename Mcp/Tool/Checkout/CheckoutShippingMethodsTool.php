<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Checkout;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\EstimateAddressInterfaceFactory;
use Magento\Quote\Api\GuestShippingMethodManagementInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Lists shipping methods available for the active cart given a destination
 * country/region/postcode. The LLM has no way to invent valid Magento carrier
 * and method codes (e.g. "flatrate" vs "USPS"), so it must call this before
 * checkout-set-address with type=shipping to discover them.
 */
class CheckoutShippingMethodsTool
{
    public function __construct(
        private readonly McpUserContext $userContext,
        private readonly CartManagementInterface $cartManagement,
        private readonly ShippingMethodManagementInterface $shippingMethodManagement,
        private readonly GuestShippingMethodManagementInterface $guestShippingMethodManagement,
        private readonly EstimateAddressInterfaceFactory $estimateAddressFactory,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'checkout-shipping-methods',
        description: 'List shipping methods available for the cart given a destination. Required: countryId (ISO-2, e.g. "US"). Optional: postcode, region, regionId, cartId (required for guests). Returns an array of {carrier_code, method_code, carrier_title, method_title, amount, available} — pass carrier_code + method_code into checkout-set-address. Call this BEFORE checkout-set-address so the codes are known.'
    )]
    public function getMethods(
        string $countryId,
        ?string $postcode = null,
        ?string $region = null,
        ?int $regionId = null,
        ?string $cartId = null,
    ): array {
        $address = $this->estimateAddressFactory->create();
        $address->setCountryId($countryId);
        if ($postcode !== null) {
            $address->setPostcode($postcode);
        }
        if ($region !== null) {
            $address->setRegion($region);
        }
        if ($regionId !== null) {
            $address->setRegionId($regionId);
        }

        $isCustomer = $this->userContext->isAuthenticated()
            && $this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER;

        // chat_agent calls in via the install-time admin/integration token,
        // so isCustomer is false even when there's a real cart. Detect that
        // case by treating a numeric cartId as the quote id and routing to
        // the customer-scoped management API (which accepts int cart ids
        // and works for guest quotes too). The guest API is reserved for
        // truly anonymous callers passing the masked alphanumeric token.
        $intCartId = null;
        if ($isCustomer) {
            $intCartId = (int) $this->cartManagement->getCartForCustomer($this->userContext->getUserId())->getId();
        } elseif ($cartId !== null && ctype_digit($cartId)) {
            $intCartId = (int) $cartId;
        }

        if ($intCartId !== null) {
            $methods = $this->shippingMethodManagement->estimateByAddress($intCartId, $address);
        } else {
            if ($cartId === null) {
                throw new \RuntimeException('cartId is required for guest checkout.');
            }
            $methods = $this->guestShippingMethodManagement->estimateByAddress($cartId, $address);
        }

        $rows = [];
        foreach ($methods as $m) {
            $rows[] = [
                'carrier_code'   => (string) $m->getCarrierCode(),
                'method_code'    => (string) $m->getMethodCode(),
                'carrier_title'  => (string) $m->getCarrierTitle(),
                'method_title'   => (string) $m->getMethodTitle(),
                'amount'         => (float) $m->getAmount(),
                'available'      => (bool) $m->getAvailable(),
                'error_message'  => (string) $m->getErrorMessage(),
            ];
        }

        if (empty($rows)) {
            return [
                'text'    => 'No shipping methods available for that destination. Ask the customer for a different address or country.',
                'methods' => [],
            ];
        }

        return ['methods' => $rows];
    }
}
