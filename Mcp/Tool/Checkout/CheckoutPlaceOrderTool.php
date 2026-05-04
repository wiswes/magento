<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Checkout;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\PaymentInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Sets the chosen payment method on the cart and places the order in one
 * call (Magento\Checkout\Api\(Guest)PaymentInformationManagementInterface
 * ::savePaymentInformationAndPlaceOrder). The shipping address+method must
 * have been saved earlier via checkout-set-address (type=shipping).
 */
class CheckoutPlaceOrderTool
{
    public function __construct(
        private readonly McpUserContext $userContext,
        private readonly CartManagementInterface $cartManagement,
        private readonly PaymentInformationManagementInterface $paymentInformation,
        private readonly GuestPaymentInformationManagementInterface $guestPaymentInformation,
        private readonly PaymentInterfaceFactory $paymentFactory,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'checkout-place-order',
        description: 'Place the order: saves the chosen payment method on the cart and finalizes purchase. Required: paymentMethodCode (from checkout-payment-methods, e.g. "checkmo", "cashondelivery"). Guests must also pass cartId and email. Prerequisite: shipping address+method already saved via checkout-set-address (type=shipping). Returns {order_id, increment_id, grand_total, currency}.'
    )]
    public function placeOrder(
        string $paymentMethodCode,
        ?string $cartId = null,
        ?string $email = null,
    ): array {
        $isCustomer = $this->userContext->isAuthenticated()
            && $this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER;

        $payment = $this->paymentFactory->create();
        $payment->setMethod($paymentMethodCode);

        // chat_agent's admin/integration token is non-customer; numeric
        // cartIds belong to the customer-scoped management API, not the
        // guest one (which only resolves the masked alphanumeric token).
        $intCartId = null;
        if ($isCustomer) {
            $intCartId = (int) $this->cartManagement->getCartForCustomer($this->userContext->getUserId())->getId();
        } elseif ($cartId !== null && ctype_digit($cartId)) {
            $intCartId = (int) $cartId;
        }

        if ($intCartId !== null) {
            $orderId = $this->paymentInformation->savePaymentInformationAndPlaceOrder(
                $intCartId,
                $payment,
            );
        } else {
            if ($cartId === null || $email === null) {
                throw new \RuntimeException('cartId and email are required for guest checkout.');
            }
            $orderId = $this->guestPaymentInformation->savePaymentInformationAndPlaceOrder(
                $cartId,
                $email,
                $payment,
            );
        }

        $order = $this->orderRepository->get((int) $orderId);

        return [
            'success'      => true,
            'order_id'     => (int) $order->getEntityId(),
            'increment_id' => (string) $order->getIncrementId(),
            'grand_total'  => (float) $order->getGrandTotal(),
            'currency'     => (string) $order->getOrderCurrencyCode(),
            'state'        => (string) $order->getState(),
            'status'       => (string) $order->getStatus(),
            // Surface as a UI-friendly action so the storefront can clear
            // its minicart and redirect to the success page.
            'action' => [
                'type'         => 'order_placed',
                'order_id'     => (int) $order->getEntityId(),
                'increment_id' => (string) $order->getIncrementId(),
            ],
        ];
    }
}
