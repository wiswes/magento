<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Auth;

use Magento\Authorization\Model\UserContextInterface;

/**
 * `UserContextInterface` implementation that resolves the active user from
 * one of two sources, in order:
 *
 *   1. The MCP {@see RequestContext} bearer token (set by
 *      {@see \WisWes\MCP\Model\Transport\BearerTokenMiddleware} on each
 *      inbound MCP HTTP request). The bearer is the shared secret that the
 *      Install handshake stored under `wiswes_mcp/auth/shared_secret`;
 *      {@see TokenUserContextResolver} compares it constant-time and returns
 *      the admin user id captured at install time.
 *   2. A fallback {@see UserContextInterface} — typically the original
 *      `\Magento\Webapi\Model\Authorization\TokenUserContext` — for normal
 *      Magento REST traffic that hits the same `webapi_rest` area.
 *
 * Scoping the preference to `etc/webapi_rest/di.xml` keeps the blast radius
 * tight: admin/frontend areas continue to use Magento's stock contexts.
 *
 * The class is shared (singleton in DI) so re-resolution must be explicit.
 * The dispatcher calls {@see reset()} before each MCP tool call so a session
 * that switches between admin and customer tokens picks up the change.
 */
class McpUserContext implements UserContextInterface
{
    private bool $resolved = false;
    private ?int $userId = null;
    private ?int $userType = null;

    public function __construct(
        private readonly RequestContext $requestContext,
        private readonly TokenUserContextResolver $resolver,
        private readonly UserContextInterface $fallback,
    ) {
    }

    public function getUserId(): ?int
    {
        $this->ensureResolved();
        return $this->userId;
    }

    public function getUserType(): ?int
    {
        $this->ensureResolved();
        return $this->userType;
    }

    public function isAuthenticated(): bool
    {
        $this->ensureResolved();
        return $this->userId !== null;
    }

    public function reset(): void
    {
        $this->resolved = false;
        $this->userId = null;
        $this->userType = null;
    }

    private function ensureResolved(): void
    {
        if ($this->resolved) {
            return;
        }
        $this->resolved = true;

        $token = $this->requestContext->getBearerToken();
        if ($token === null) {
            // Not inside an MCP request — defer entirely to the original
            // webapi token context so normal REST behaves unchanged.
            $this->userType = $this->fallback->getUserType();
            $this->userId   = $this->fallback->getUserId();
            return;
        }

        $resolved = $this->resolver->resolve($token);
        if ($resolved === null) {
            return;
        }

        [$this->userType, $this->userId] = $resolved;
    }
}
