<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Transport;

use WisWes\MCP\Model\Auth\RequestContext;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

/**
 * React HTTP middleware that copies the inbound `Authorization` header into
 * {@see RequestContext} for the duration of one MCP request.
 *
 * The bearer is the shared secret minted on Install (see
 * {@see \WisWes\MCP\Controller\Adminhtml\Install\Index}). It's consumed by
 * {@see \WisWes\MCP\Model\Auth\TokenUserContextResolver} to authenticate the
 * call and surface the admin user id to {@see \WisWes\MCP\Model\Auth\McpUserContext}.
 *
 * Tool dispatch happens synchronously inside the React HTTP request handler,
 * in the same call stack as this middleware — so the bearer set here is
 * visible to the {@see \WisWes\MCP\Model\Dispatch\ServiceContractDispatcher}
 * when it calls a Magento service contract. The context is cleared after the
 * response promise resolves so a token from one request never leaks into the
 * next one (important when MCP runs in stateless mode and one process serves
 * many clients).
 */
class BearerTokenMiddleware
{
    public function __construct(
        private readonly RequestContext $requestContext,
    ) {}

    public function __invoke(ServerRequestInterface $request, callable $next): mixed
    {
        $auth = $request->getHeaderLine('Authorization');
        $this->requestContext->setBearerToken($auth !== '' ? $auth : null);

        try {
            $result = $next($request);
        } catch (\Throwable $e) {
            $this->requestContext->clear();
            throw $e;
        }

        if ($result instanceof PromiseInterface) {
            return $result->then(
                function ($value) {
                    $this->requestContext->clear();
                    return $value;
                },
                function ($reason) {
                    $this->requestContext->clear();
                    throw $reason instanceof \Throwable
                        ? $reason
                        : new \RuntimeException((string) $reason);
                }
            );
        }

        $this->requestContext->clear();
        return $result;
    }
}
