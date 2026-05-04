<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use PhpMcp\Server\Attributes\McpTool;

class CustomerUpdateTool
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerScopeResolver $scopeResolver,
    ) {}

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'customer-update',
        description: 'Updates profile fields for a customer. Only provided fields are changed; omitted fields remain untouched. Arguments: customerId (int, optional — required when caller is admin/integration; ignored for customer tokens), firstname (string, optional), lastname (string, optional), email (string, optional), dob (string Y-m-d, optional), gender (int 1=Male 2=Female 3=Not specified, optional). Returns the updated profile.'
    )]
    public function update(
        ?int $customerId = null,
        ?string $firstname = null,
        ?string $lastname = null,
        ?string $email = null,
        ?string $dob = null,
        ?int $gender = null,
    ): array {
        $resolvedCustomerId = $this->scopeResolver->resolve($customerId, 'customer-update');
        $customer = $this->customerRepository->getById($resolvedCustomerId);

        if ($firstname !== null) {
            $customer->setFirstname($firstname);
        }
        if ($lastname !== null) {
            $customer->setLastname($lastname);
        }
        if ($email !== null) {
            $customer->setEmail($email);
        }
        if ($dob !== null) {
            $customer->setDob($dob);
        }
        if ($gender !== null) {
            $customer->setGender($gender);
        }

        $saved = $this->customerRepository->save($customer);

        return [
            'id'        => (int) $saved->getId(),
            'email'     => (string) $saved->getEmail(),
            'firstname' => (string) $saved->getFirstname(),
            'lastname'  => (string) $saved->getLastname(),
            'dob'       => $saved->getDob(),
            'gender'    => $saved->getGender(),
            'group_id'  => (int) $saved->getGroupId(),
        ];
    }
}
