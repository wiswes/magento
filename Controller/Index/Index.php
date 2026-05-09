<?php
declare(strict_types=1);

namespace WisWes\MCP\Controller\Index;

use WisWes\MCP\Model\Auth\RequestContext;
use WisWes\MCP\Service\McpServerBuilder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Math\Random;
use PhpMcp\Server\Exception\McpServerException;
use PhpMcp\Server\JsonRpc\Notification;
use PhpMcp\Server\JsonRpc\Request as McpRequest;
use PhpMcp\Server\JsonRpc\Response as McpResponse;
use Psr\Log\LoggerInterface;

/**
 * Apache-served MCP endpoint at `/mcp`. One HTTP POST per JSON-RPC frame,
 * stateless aside from session state persisted via ClientStateManager's
 * PSR-16 cache (MagentoPsrCache). No SSE, no server-initiated notifications.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const SESSION_HEADER = 'Mcp-Session-Id';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly McpServerBuilder $serverBuilder,
        private readonly RequestContext $requestContext,
        private readonly State $appState,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Random $random,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): ResultInterface
    {
        try {
            $this->appState->setAreaCode('webapi_rest');
        } catch (\Throwable) {

        }

        $auth = (string) ($this->request->getHeader('Authorization') ?: '');
        if (stripos($auth, 'Bearer ') === 0) {
            $this->requestContext->setBearerToken(trim(substr($auth, 7)));
        } else {
            $this->requestContext->clear();
        }

        $sessionId = (string) ($this->request->getHeader(self::SESSION_HEADER) ?: '');
        if ($sessionId === '') {
            $sessionId = bin2hex($this->random->getRandomBytes(16));
        }

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/json', true);
        $result->setHeader(self::SESSION_HEADER, $sessionId, true);

        $name    = (string) ($this->scopeConfig->getValue('wiswes_mcp/server/name') ?: 'WisWes Magento MCP');
        $version = '1.0.0';
        $payload = null;

        try {
            $raw = (string) $this->request->getContent();
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload) || !isset($payload['method'])) {
                throw McpServerException::invalidRequest("Missing 'method'");
            }
            $message = (isset($payload['id']) && $payload['id'] !== null)
                ? McpRequest::fromArray($payload)
                : Notification::fromArray($payload);

            $server = $this->serverBuilder->buildServer($name, $version);
            $response = $server->getProcessor()->process($message, $sessionId);

            if ($message instanceof Notification || $response === null) {
                $result->setHttpResponseCode(204);
                return $result->setContents('');
            }

            return $result->setContents($this->encode($response));

        } catch (\JsonException $e) {
            $err = McpServerException::parseError($e->getMessage());
            return $result->setContents($this->encode(McpResponse::error($err->toJsonRpcError(), null)));
        } catch (McpServerException $e) {
            return $result->setContents($this->encode(McpResponse::error($e->toJsonRpcError(), (is_array($payload) ? ($payload['id'] ?? null) : null))));
        } catch (\Throwable $e) {
            $this->logger->error('[WisWes_MCP] ' . $e->getMessage(), ['exception' => $e]);
            return $result->setContents($this->encode(McpResponse::error(McpServerException::internalError()->toJsonRpcError(), (is_array($payload) ? ($payload['id'] ?? null) : null))));
        } finally {
            $this->requestContext->clear();
        }
    }

    private function encode(McpResponse $response): string
    {
        return json_encode($response->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
