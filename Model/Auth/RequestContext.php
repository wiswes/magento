<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Auth;

/**
 * Per-request authentication state for the running MCP server.
 *
 * The MCP HTTP transport does not flow request headers into tool callables,
 * so we keep the bearer token in a process-local holder that is set by the
 * transport-level middleware (see {@see \WisWes\MCP\Service\McpServerBuilder})
 * and read by the dispatcher just before invoking a service contract.
 *
 * Singleton-scoped via di.xml — never `new` it.
 */
class RequestContext
{
    private ?string $bearerToken = null;

    public function setBearerToken(?string $token): void
    {
        $this->bearerToken = $token !== null && $token !== '' ? $token : null;
    }

    public function getBearerToken(): ?string
    {
        return $this->bearerToken;
    }

    public function hasToken(): bool
    {
        return $this->bearerToken !== null;
    }

    public function clear(): void
    {
        $this->bearerToken = null;
    }
}
