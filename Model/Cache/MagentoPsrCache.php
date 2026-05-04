<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Cache;

use Magento\Framework\App\CacheInterface as MagentoCache;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 wrapper around Magento's cache frontend so php-mcp/server's
 * ClientStateManager can persist MCP session state across PHP-FPM requests.
 */
class MagentoPsrCache implements CacheInterface
{
    private const TAG = 'CHATAI_MCP';

    public function __construct(
        private readonly MagentoCache $cache,
        private readonly int $defaultTtl = 3600,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->cache->load($key);
        if ($raw === false || $raw === null || $raw === '') {
            return $default;
        }
        $value = @unserialize($raw, ['allowed_classes' => true]);
        return $value === false && $raw !== serialize(false) ? $default : $value;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return $this->cache->save(
            serialize($value),
            $key,
            [self::TAG],
            $this->resolveTtl($ttl),
        );
    }

    public function delete(string $key): bool
    {
        return $this->cache->remove($key);
    }

    public function clear(): bool
    {
        return $this->cache->clean([self::TAG]);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->get($k, $default);
        }
        return $out;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $k => $v) {
            $ok = $this->set((string) $k, $v, $ttl) && $ok;
        }
        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $k) {
            $ok = $this->delete($k) && $ok;
        }
        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->cache->load($key) !== false;
    }

    private function resolveTtl(\DateInterval|int|null $ttl): int
    {
        if ($ttl === null) {
            return $this->defaultTtl;
        }
        if ($ttl instanceof \DateInterval) {
            $ref = new \DateTimeImmutable();
            return $ref->add($ttl)->getTimestamp() - $ref->getTimestamp();
        }
        return (int) $ttl;
    }
}