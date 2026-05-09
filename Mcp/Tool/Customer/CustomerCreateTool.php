<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\Store\Model\StoreManagerInterface;
use PhpMcp\Server\Attributes\McpTool;

class CustomerCreateTool
{
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerTokenServiceInterface $customerTokenService,
    ) {
    }

    /** @return array<string, mixed> */
    #[McpTool(
        name: 'customer_create',
        description: 'Register a new customer account and sign them in. Arguments: email (required), password (required), firstname (required), lastname (required), dob (Y-m-d, optional), gender (1=Male, 2=Female, 3=Not specified, optional), taxvat (optional). Returns a friendly welcome message, the customer profile (email, name), and an access_token that authenticates them on subsequent customer-scoped tool calls. No auth required.'
    )]
    public function create(
        string $email,
        string $password,
        string $firstname,
        string $lastname,
        ?string $dob = null,
        ?int $gender = null,
        ?string $taxvat = null,
    ): array {
        $store = $this->storeManager->getStore();

        $customer = $this->customerFactory->create();
        $customer->setEmail($email);
        $customer->setFirstname($firstname);
        $customer->setLastname($lastname);
        $customer->setStoreId((int) $store->getId());
        $customer->setWebsiteId((int) $store->getWebsiteId());
        if ($dob !== null) {
            $customer->setDob($dob);
        }
        if ($gender !== null) {
            $customer->setGender($gender);
        }
        if ($taxvat !== null) {
            $customer->setTaxvat($taxvat);
        }

        $saved = $this->accountManagement->createAccount($customer, $password);

        $accessToken = $this->customerTokenService->createCustomerAccessToken($email, $password);

        return [
            'message' => sprintf(
                'Welcome, %s! Your account has been created and you are now signed in.',
                (string) $saved->getFirstname()
            ),
            'logged_in'    => true,
            'access_token' => $accessToken,
            'customer'     => [
                'id'        => (int) $saved->getId(),
                'email'     => (string) $saved->getEmail(),
                'firstname' => (string) $saved->getFirstname(),
                'lastname'  => (string) $saved->getLastname(),
                'dob'       => $saved->getDob(),
                'gender'    => $saved->getGender(),
            ],
            // Surface the new customer's id/name into chat_agent's session
            // context. ResponseParser merges typed fields (customer_id,
            // customer_name) onto state.context, and tool_executor then
            // auto-threads customerId into subsequent customer-* MCP calls
            // so the integration token can act on behalf of this customer.
            'context' => [
                'customer_id'   => (int) $saved->getId(),
                'customer_name' => trim((string) $saved->getFirstname() . ' ' . (string) $saved->getLastname()),
            ],
        ];
    }
}
