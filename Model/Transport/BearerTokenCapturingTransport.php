<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Transport;

use WisWes\MCP\Model\Auth\RequestContext;
use PhpMcp\Server\Exception\TransportException;
use PhpMcp\Server\Transports\HttpServerTransport;
use React\Http\HttpServer;
use React\Socket\SocketServer;

/**
 * Subclass of {@see HttpServerTransport} that injects a
 * {@see BearerTokenMiddleware} so inbound Authorization headers
 * reach our {@see RequestContext}.
 *
 * Mirrors parent::listen() but inserts the middleware at HttpServer
 * construction time. This is the ONE place to refresh when bumping
 * php-mcp/server.
 */
class BearerTokenCapturingTransport extends HttpServerTransport
{
    public function __construct(
        private readonly RequestContext $requestContext,
        string $host = '127.0.0.1',
        int $port = 8080,
        string $mcpPath = '/mcp',
        ?array $sslContext = null,
    ) {
        parent::__construct(
            host: $host,
            port: $port,
            mcpPathPrefix: $mcpPath,
            sslContext: $sslContext,
        );
    }

    public function listen(): void
    {
        $parent = new \ReflectionClass(HttpServerTransport::class);

        $listening = $this->prop($parent, 'listening');
        $closing   = $this->prop($parent, 'closing');
        $socketP   = $this->prop($parent, 'socket');
        $httpP     = $this->prop($parent, 'http');
        $hostP     = $this->prop($parent, 'host');
        $portP     = $this->prop($parent, 'port');
        $sslP      = $this->prop($parent, 'sslContext');

        if ($listening->getValue($this)) {
            throw new TransportException('Http transport is already listening.');
        }
        if ($closing->getValue($this)) {
            throw new TransportException('Cannot listen, transport is closing/closed.');
        }

        $host = $hostP->getValue($this);
        $port = $portP->getValue($this);
        $ssl  = $sslP->getValue($this);
        $listenAddress = "{$host}:{$port}";
        $protocol = $ssl ? 'https' : 'http';

        $createRequestHandler = $parent->getMethod('createRequestHandler');
        $createRequestHandler->setAccessible(true);

        try {
            $socket = new SocketServer($listenAddress, $ssl ?? [], $this->loop);
            $socketP->setValue($this, $socket);

            $http = new HttpServer(
                $this->loop,
                new BearerTokenMiddleware($this->requestContext),
                $createRequestHandler->invoke($this),
            );
            $httpP->setValue($this, $http);
            $http->listen($socket);

            $socket->on('error', function (\Throwable $error) {
                $this->logger->error('Socket server error.', ['error' => $error->getMessage()]);
                $this->emit('error', [new TransportException("Socket server error: {$error->getMessage()}", 0, $error)]);
                $this->close();
            });

            $this->logger->info("Server is up and listening on {$protocol}://{$listenAddress} 🚀");

            $listening->setValue($this, true);
            $closing->setValue($this, false);
            $this->emit('ready');
        } catch (\Throwable $e) {
            $this->logger->error("Failed to start listener on {$listenAddress}", ['exception' => $e]);
            throw new TransportException(
                "Failed to start HTTP listener on {$listenAddress}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    private function prop(\ReflectionClass $class, string $name): \ReflectionProperty
    {
        $p = $class->getProperty($name);
        $p->setAccessible(true);
        return $p;
    }
}
