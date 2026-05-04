<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Sales;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class OrderUpdateTool
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderManagementInterface $orderManagement,
        private readonly OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly OrderStatusHistoryInterfaceFactory $historyFactory,
        private readonly McpUserContext $userContext,
        private readonly OrderInfoTool $orderInfoTool,
    ) {}

    /**
     * @param array<string, mixed>|null $address
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'order-update',
        description: 'Performs an action on an order. Arguments: orderId (int, required); action (string, required — one of "comment", "hold", "unhold", "cancel", "shipping-address"); comment (string, for action=comment); status (string, optional new status for action=comment); notify (bool, default false — email the customer for action=comment); address (object with firstname, lastname, street, city, region, postcode, countryId, telephone — for action=shipping-address). Customer tokens may only act on their own orders; admin tokens may act on any. Returns the updated order info.'
    )]
    public function update(
        int $orderId,
        string $action,
        ?string $comment = null,
        ?string $status = null,
        bool $notify = false,
        ?array $address = null,
    ): array {
        $order = $this->orderRepository->get($orderId);
        $this->orderInfoTool->assertCanRead($order);

        match ($action) {
            'comment' => $this->addComment($orderId, $comment ?? '', $status, $notify),
            'hold' => $this->orderManagement->hold($orderId),
            'unhold' => $this->orderManagement->unHold($orderId),
            'cancel' => $this->orderManagement->cancel($orderId),
            'shipping-address' => $this->updateShippingAddress($order, $address ?? []),
            default => throw new \RuntimeException(sprintf(
                'Unknown action "%s". Valid: comment, hold, unhold, cancel, shipping-address.',
                $action
            )),
        };

        return $this->orderInfoTool->info(orderId: $orderId);
    }

    private function addComment(int $orderId, string $comment, ?string $status, bool $notify): void
    {
        $history = $this->historyFactory->create();
        $history->setComment($comment);
        $history->setIsVisibleOnFront(true);
        $history->setIsCustomerNotified($notify);
        if ($status !== null) {
            $history->setStatus($status);
        }
        $this->orderManagement->addComment($orderId, $history);
    }

    /** @param array<string, mixed> $addressData */
    private function updateShippingAddress($order, array $addressData): void
    {
        if ($this->userContext->getUserType() !== UserContextInterface::USER_TYPE_ADMIN
            && $this->userContext->getUserType() !== UserContextInterface::USER_TYPE_INTEGRATION) {
            throw new \RuntimeException('Shipping address update requires admin privileges.');
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress === null) {
            throw new \RuntimeException('Order has no shipping address (virtual order).');
        }

        if (isset($addressData['firstname'])) { $shippingAddress->setFirstname($addressData['firstname']); }
        if (isset($addressData['lastname'])) { $shippingAddress->setLastname($addressData['lastname']); }
        if (isset($addressData['street'])) { $shippingAddress->setStreet($addressData['street']); }
        if (isset($addressData['city'])) { $shippingAddress->setCity($addressData['city']); }
        if (isset($addressData['region'])) { $shippingAddress->setRegion($addressData['region']); }
        if (isset($addressData['postcode'])) { $shippingAddress->setPostcode($addressData['postcode']); }
        if (isset($addressData['countryId'])) { $shippingAddress->setCountryId($addressData['countryId']); }
        if (isset($addressData['telephone'])) { $shippingAddress->setTelephone($addressData['telephone']); }

        $this->orderAddressRepository->save($shippingAddress);
    }
}
