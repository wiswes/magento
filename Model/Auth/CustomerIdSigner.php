<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Auth;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * HMAC-signs the logged-in customer's id so chat_agent can verify the value
 * came from a real Magento session and was not fabricated by client-side JS.
 *
 * Threat model: window.CHAT_WIDGET_CONFIG.customerId is a plain JS variable
 * the page can rewrite. If chat_agent trusted the raw POST body, an attacker
 * could send another customer's id and the integration-token-scoped
 * customer-* MCP tools (customer-info, customer-address-update, ...) would
 * happily act on that customer's data. The signed token closes that window:
 * chat_agent recomputes the HMAC against its copy of the install secret and
 * only accepts an id whose signature matches.
 *
 * Token format: "<customer_id>.<unix_expiry>.<hex_hmac_sha256>".
 * Shared secret is the install bearer (stored encrypted under
 * wiswes_mcp/auth/shared_secret) — same secret chat_agent already holds in
 * commerce_config.commerce_token, so no new key distribution is needed.
 */
class CustomerIdSigner
{
    private const CONFIG_PATH_SECRET = 'wiswes_mcp/auth/shared_secret';
    private const TTL_SECONDS = 3600;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {}

    /**
     * Returns a signed token for the given customer id, or '' for guests
     * (or when the install secret is missing). chat_agent treats absence
     * as customer_id=0.
     */
    public function sign(int $customerId): string
    {
        if ($customerId <= 0) {
            return '';
        }
        $secret = $this->resolveSecret();
        if ($secret === '') {
            return '';
        }
        $expiry = time() + self::TTL_SECONDS;
        $payload = $customerId . '.' . $expiry;
        $signature = hash_hmac('sha256', $payload, $secret);
        return $payload . '.' . $signature;
    }

    private function resolveSecret(): string
    {
        $stored = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_SECRET);
        if ($stored === '') {
            return '';
        }
        try {
            $secret = $this->encryptor->decrypt($stored);
        } catch (\Throwable) {
            return '';
        }
        return is_string($secret) ? $secret : '';
    }
}
