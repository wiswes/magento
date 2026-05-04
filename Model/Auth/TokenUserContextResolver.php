<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Auth;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Resolves the inbound MCP bearer to a Magento user context.
 *
 * Auth model is a shared secret minted by the {@see \WisWes\MCP\Controller\Adminhtml\Install\Index}
 * controller and passed to WisWes during install. Every MCP HTTP request carries
 * `Authorization: Bearer <secret>`; we compare against the value stored under
 * `wiswes_mcp/auth/shared_secret` (encrypted at rest) in constant time, then run
 * the request as the admin user whose id was saved alongside.
 *
 * No Magento Integration / OAuth1 token lookup is involved. That pre-existing
 * path required either:
 *   - the "Allow OAuth Access Tokens to be used as standalone Bearer tokens"
 *     admin setting (off by default), or
 *   - a TYPE_CONFIG Integration with resources declared in `etc/integration/
 *     api.xml` plus a `bin/magento setup:upgrade` after install,
 *
 * neither of which we control on the merchant's box. Pinning auth to a config
 * value the install flow itself writes removes both gotchas.
 */
class TokenUserContextResolver
{
    private const CONFIG_PATH_SECRET = 'wiswes_mcp/auth/shared_secret';
    private const CONFIG_PATH_ADMIN_ID = 'wiswes_mcp/auth/admin_id';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {}

    /**
     * @return array{0:int,1:int}|null [userType, userId] or null when the bearer is missing/wrong
     */
    public function resolve(string $bearerToken): ?array
    {
        $bearerToken = trim(preg_replace('/^Bearer\s+/i', '', $bearerToken) ?? '');
        if ($bearerToken === '') {
            return null;
        }

        $stored = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_SECRET);
        if ($stored === '') {
            return null;
        }
        $secret = $this->encryptor->decrypt($stored);
        if ($secret === '' || !hash_equals($secret, $bearerToken)) {
            return null;
        }

        $adminId = (int) $this->scopeConfig->getValue(self::CONFIG_PATH_ADMIN_ID);
        if ($adminId === 0) {
            return null;
        }

        return [UserContextInterface::USER_TYPE_ADMIN, $adminId];
    }
}
