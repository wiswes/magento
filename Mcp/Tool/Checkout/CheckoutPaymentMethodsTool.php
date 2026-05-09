<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Checkout;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestPaymentMethodManagementInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Lists payment methods available for the active cart. Magento only exposes
 * payment methods after the shipping address+method are saved on the quote,
 * so call this AFTER checkout-set-address (type=shipping) succeeds.
 */
class CheckoutPaymentMethodsTool
{
    public function __construct(
        private readonly McpUserContext $userContext,
        private readonly CartManagementInterface $cartManagement,
        private readonly PaymentMethodManagementInterface $paymentMethodManagement,
        private readonly GuestPaymentMethodManagementInterface $guestPaymentMethodManagement,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'checkout-payment-methods',
        description: 'List payment methods available for the cart. Argument: cartId (required for guests). Returns array of {code, title} — pass code into checkout-place-order as paymentMethodCode. Magento exposes payment methods only after the shipping address and method are saved (checkout-set-address with type=shipping), so call this immediately after that succeeds.'
    )]
    public function getMethods(?string $cartId = null): array
    {
        $isCustomer = $this->userContext->isAuthenticated()
            && $this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER;

        // chat_agent's admin/integration token is non-customer; route numeric
        // cartIds through the customer-scoped management API rather than the
        // guest one (which only resolves the masked alphanumeric token).
        $intCartId = null;
        if ($isCustomer) {
            $intCartId = (int) $this->cartManagement->getCartForCustomer($this->userContext->getUserId())->getId();
        } elseif ($cartId !== null && ctype_digit($cartId)) {
            $intCartId = (int) $cartId;
        }

        if ($intCartId !== null) {
            $methods = $this->paymentMethodManagement->getList($intCartId);
        } else {
            if ($cartId === null) {
                throw new \RuntimeException('cartId is required for guest checkout.');
            }
            $methods = $this->guestPaymentMethodManagement->getList($cartId);
        }

        $rows = [];
        foreach ($methods as $m) {
            $rows[] = [
                'code'  => (string) $m->getCode(),
                'title' => (string) $m->getTitle(),
            ];
        }

        if (empty($rows)) {
            return [
                'text'    => 'No payment methods available. Make sure the shipping address+method are saved first via checkout-set-address (type=shipping).',
                'methods' => [],
            ];
        }

        return ['methods' => $rows];
    }
}
