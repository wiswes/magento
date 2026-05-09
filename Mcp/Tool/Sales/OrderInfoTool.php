<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Sales;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class OrderInfoTool
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly McpUserContext $userContext,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'order-info',
        description: 'Returns a compact status payload for one order — header (state, status, total, placed_at), latest history comments, line items, and per-shipment tracking numbers. Two modes: (1) Customer token — pass orderId (int); (2) Guest — pass incrementId (string) + email (string) + postcode (string), no token needed. Customer tokens may only read their own orders; admin tokens may read any.'
    )]
    public function info(
        ?int $orderId = null,
        ?string $incrementId = null,
        ?string $email = null,
        ?string $postcode = null,
    ): array {
        if ($orderId !== null) {
            $order = $this->orderRepository->get($orderId);
            $this->assertCanRead($order);
        } elseif ($incrementId !== null && $email !== null && $postcode !== null) {
            $order = $this->guestLookup($incrementId, $email, $postcode);
        } else {
            throw new \RuntimeException('Provide orderId (with token) or incrementId+email+postcode (guest).');
        }

        return $this->compact($order);
    }

    /** @return array<string, mixed> */
    private function compact(OrderInterface $order): array
    {
        return [
            'order_id'   => (int) $order->getEntityId(),
            'increment'  => (string) $order->getIncrementId(),
            'state'      => (string) $order->getState(),
            'status'     => (string) $order->getStatus(),
            'total'      => (float) $order->getGrandTotal(),
            'currency'   => (string) $order->getOrderCurrencyCode(),
            'placed_at'  => (string) $order->getCreatedAt(),
            'updated_at' => (string) $order->getUpdatedAt(),
            'items'      => $this->items($order),
            'comments'   => $this->latestComments($order, 5),
            'shipments'  => $this->shipments((int) $order->getEntityId()),
        ];
    }

    private function guestLookup(string $incrementId, string $email, string $postcode): OrderInterface
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->setPageSize(1)
            ->create();

        $result = $this->orderRepository->getList($criteria);
        $items = $result->getItems();

        if (empty($items)) {
            throw new \RuntimeException('Order not found.');
        }

        $order = reset($items);

        if (strtolower((string) $order->getCustomerEmail()) !== strtolower($email)) {
            throw new \RuntimeException('Order not found.');
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null || strtolower((string) $billingAddress->getPostcode()) !== strtolower($postcode)) {
            throw new \RuntimeException('Order not found.');
        }

        return $order;
    }

    public function assertCanRead(OrderInterface $order): void
    {
        $type = $this->userContext->getUserType();
        $uid  = $this->userContext->getUserId();

        if ($type === UserContextInterface::USER_TYPE_ADMIN || $type === UserContextInterface::USER_TYPE_INTEGRATION) {
            return;
        }
        if ($type === UserContextInterface::USER_TYPE_CUSTOMER && (int) $order->getCustomerId() === (int) $uid) {
            return;
        }
        throw new \RuntimeException('order-info: not authorized to read this order.');
    }

    /** @return list<array<string, mixed>> */
    private function items(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getItems() ?? [] as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $items[] = [
                'item_id' => (int) $item->getItemId(),
                'sku'     => (string) $item->getSku(),
                'name'    => (string) $item->getName(),
                'qty'     => (float) $item->getQtyOrdered(),
                'price'   => (float) $item->getPrice(),
                'row_total'=> (float) $item->getRowTotal(),
                'status'  => (string) $item->getStatus(),
            ];
        }
        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function latestComments(OrderInterface $order, int $limit): array
    {
        $comments = [];
        $history = $order->getStatusHistories() ?? [];
        foreach (array_slice($history, 0, $limit) as $entry) {
            $comments[] = [
                'created_at' => (string) $entry->getCreatedAt(),
                'status'     => (string) $entry->getStatus(),
                'comment'    => (string) $entry->getComment(),
                'visible'    => (bool) $entry->getIsVisibleOnFront(),
            ];
        }
        return $comments;
    }

    /** @return list<array<string, mixed>> */
    private function shipments(int $orderId): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('order_id', $orderId, 'eq')
            ->create();
        $result = $this->shipmentRepository->getList($criteria);

        $shipments = [];
        foreach ($result->getItems() as $shipment) {
            $tracks = [];
            foreach ($shipment->getTracks() ?? [] as $track) {
                $tracks[] = [
                    'carrier' => (string) $track->getCarrierCode(),
                    'title'   => (string) $track->getTitle(),
                    'number'  => (string) $track->getTrackNumber(),
                ];
            }
            $shipments[] = [
                'shipment_id' => (int) $shipment->getEntityId(),
                'created_at'  => (string) $shipment->getCreatedAt(),
                'tracks'      => $tracks,
            ];
        }
        return $shipments;
    }
}
