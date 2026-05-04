<?php
declare(strict_types=1);

namespace WisWes\MCP\Mcp\Tool\Customer;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Authorization\Model\UserContextInterface;

/**
 * Resolves the customer id a tool should operate on.
 *
 * Two valid call paths:
 *   1. Customer-scoped bearer token — id comes from the token, $explicitId is
 *      ignored. The caller is the customer themselves.
 *   2. Admin or integration bearer token + explicit $explicitId — typical
 *      chat_agent path: the install-time integration token authenticates the
 *      MCP request, and chat_agent auto-threads the customer_id captured
 *      after customer_create / customer login back into customer-* calls.
 *
 * Anything else (no customer token AND no $explicitId, or an unknown user
 * type) throws — this is the tripwire that produced the
 * "customer-* requires a customer-scoped bearer token" failures in chat
 * widgets after a successful customer_create.
 */
class CustomerScopeResolver
{
    /**
     * Inject the concrete {@see McpUserContext} rather than the abstract
     * {@see UserContextInterface}: the MCP controller dispatches in the
     * frontend area, but the McpUserContext → UserContextInterface preference
     * is only declared for `webapi_rest/di.xml`. In frontend, an
     * UserContextInterface-typed dependency would resolve to Magento's
     * default (guest) implementation and these tools would never see the
     * authenticated MCP user. Injecting the concrete class sidesteps the
     * area lookup entirely.
     */
    public function __construct(
        private readonly McpUserContext $userContext,
    ) {}

    public function resolve(?int $explicitId, string $toolName): int
    {
        $type = $this->userContext->getUserType();

        if ($type === UserContextInterface::USER_TYPE_CUSTOMER) {
            return (int) $this->userContext->getUserId();
        }

        $isPrivileged = $type === UserContextInterface::USER_TYPE_ADMIN
            || $type === UserContextInterface::USER_TYPE_INTEGRATION;

        if ($isPrivileged && $explicitId !== null && $explicitId > 0) {
            return $explicitId;
        }

        throw new \RuntimeException(sprintf(
            '%s requires a customer-scoped bearer token, or an admin/integration token plus the customerId argument.',
            $toolName,
        ));
    }
}
