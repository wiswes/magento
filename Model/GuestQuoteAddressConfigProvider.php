<?php
declare(strict_types=1);

namespace WisWes\MCP\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Surfaces the guest quote's saved shipping/billing addresses into
 * window.checkoutConfig so the storefront's KO form pre-fills.
 *
 * Why this exists: Magento's stock DefaultConfigProvider only emits
 * `shippingAddressFromData` for logged-in customers (sourced from their
 * customer-address book). For guests, the value is undefined regardless
 * of what's in `quote_address`. When the chat agent saves a shipping
 * address via `ShippingInformationManagement::saveAddressInformation`,
 * the row exists in DB but the /checkout form binds to an empty KO
 * model and shows blank fields.
 */
class GuestQuoteAddressConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
    ) {}

    public function getConfig(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (\Exception) {
            return [];
        }

        // Logged-in customers already get shippingAddressFromData populated
        // from their address book by Magento's DefaultConfigProvider. Don't
        // override that path — only fill the guest gap.
        if (!$quote->getCustomerIsGuest()) {
            return [];
        }

        $config = [];

        $shipping = $quote->getShippingAddress();
        if ($shipping && $shipping->getCity()) {
            $config['shippingAddressFromData'] = $this->toCheckoutFormat($shipping);
        }

        $billing = $quote->getBillingAddress();
        if ($billing && $billing->getCity()) {
            $config['billingAddressFromData'] = $this->toCheckoutFormat($billing);
        }

        return $config;
    }

    /**
     * Match the shape `Magento\Checkout\Model\DefaultConfigProvider::getAddressFromData`
     * uses: snake_case attribute codes (`country_id`, `region_id`), `street`
     * as a non-empty array, no empty entries. The checkout form's KO bindings
     * read these specific keys; camelCase variants don't bind for `street` /
     * `region_id` even though `firstname` / `city` happen to match either way.
     *
     * @return array<string, mixed>
     */
    private function toCheckoutFormat(AddressInterface $address): array
    {
        $street = $address->getStreet();
        if (!is_array($street)) {
            $street = $street === null ? [] : explode("\n", (string) $street);
        }
        $street = array_values(array_filter(array_map('strval', $street), 'strlen'));

        $data = [
            'firstname'  => (string) $address->getFirstname(),
            'lastname'   => (string) $address->getLastname(),
            'street'     => $street,
            'city'       => (string) $address->getCity(),
            'postcode'   => (string) $address->getPostcode(),
            'country_id' => (string) $address->getCountryId(),
            'telephone'  => (string) $address->getTelephone(),
        ];
        if ($address->getRegionId()) {
            $data['region_id'] = (int) $address->getRegionId();
        }
        if ($address->getRegion()) {
            $data['region'] = (string) $address->getRegion();
        }
        if ($address->getCompany()) {
            $data['company'] = (string) $address->getCompany();
        }
        return $data;
    }
}
