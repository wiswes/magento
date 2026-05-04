<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Sales\Api\OrderRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class CustomerInfoTool
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CustomerScopeResolver $scopeResolver,
        private readonly AddressFormatter $addressFormatter,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'customer-info',
        description: 'Returns a snapshot of a customer: profile (id, name, email, group), all addresses, and most recent orders (id, increment, status, total, placed_at). Arguments: recentOrders (int, default 5, max 20), customerId (int, optional — required when caller is admin/integration; ignored for customer tokens).'
    )]
    public function info(int $recentOrders = 5, ?int $customerId = null): array
    {
        $resolvedCustomerId = $this->scopeResolver->resolve($customerId, 'customer-info');
        $recentOrders = max(1, min($recentOrders, 20));

        $customer = $this->customerRepository->getById($resolvedCustomerId);

        $addresses = [];
        foreach ($customer->getAddresses() ?? [] as $addr) {
            $addresses[] = $this->addressFormatter->compact($addr);
        }

        return [
            'profile'   => [
                'id'        => (int) $customer->getId(),
                'email'     => (string) $customer->getEmail(),
                'firstname' => (string) $customer->getFirstname(),
                'lastname'  => (string) $customer->getLastname(),
                'group_id'  => (int) $customer->getGroupId(),
                'dob'       => $customer->getDob(),
                'gender'    => $customer->getGender(),
            ],
            'addresses'     => $addresses,
            'recent_orders' => $this->recentOrders($resolvedCustomerId, $recentOrders),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recentOrders(int $customerId, int $limit): array
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId, 'eq')
            ->setPageSize($limit)
            ->setCurrentPage(1)
            ->create();

        $sortOrder = (new SortOrder())
            ->setField('created_at')
            ->setDirection(SortOrder::SORT_DESC);
        $criteria->setSortOrders([$sortOrder]);

        $result = $this->orderRepository->getList($criteria);
        $orders = [];
        foreach ($result->getItems() as $order) {
            $orders[] = [
                'order_id'  => (int) $order->getEntityId(),
                'increment' => (string) $order->getIncrementId(),
                'status'    => (string) $order->getStatus(),
                'total'     => (float) $order->getGrandTotal(),
                'currency'  => (string) $order->getOrderCurrencyCode(),
                'placed_at' => (string) $order->getCreatedAt(),
            ];
        }
        return $orders;
    }
}
