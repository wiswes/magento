<?php
declare(strict_types=1);

namespace WisWes\MCP\Controller\Adminhtml\Install;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Store\Model\StoreManagerInterface;

/**
 * One-click WisWes install handshake.
 *
 * 1. Generates a long random shared secret unique to this Magento install.
 * 2. Persists the secret (encrypted) and the current admin's user id under
 *    `wiswes_mcp/auth/*`. The MCP middleware reads both at runtime: the secret
 *    to authenticate inbound bearers, the admin id to drive ACL.
 * 3. Redirects the merchant to the WisWes dashboard with the secret embedded
 *    as `install_token` in the query string. The dashboard stores it on the
 *    tenant's CommerceConfig so future chats can hit `/mcp` with the right
 *    `Authorization: Bearer <secret>` header.
 *
 * No Magento OAuth / Integration is involved — the MCP route has its own
 * auth path ({@see \WisWes\MCP\Model\Auth\TokenUserContextResolver}) that
 * compares against the stored secret and skips the oauth_token table
 * entirely. This avoids the "Allow OAuth Access Tokens as Bearer" admin
 * setting, the TYPE_CONFIG resource-grant XML dance, and the rest of the
 * Integration auth surface.
 *
 * The merchant never sees the secret in the browser; it travels server →
 * server via a redirect over HTTPS.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'WisWes_MCP::config';

    private const CONFIG_PATH_SECRET = 'wiswes_mcp/auth/shared_secret';
    private const CONFIG_PATH_ADMIN_ID = 'wiswes_mcp/auth/admin_id';
    private const SECRET_LENGTH = 64;
    private const INTEGRATION_NAME = 'WisWes Chat MCP';

    public function __construct(
        Context $context,
        private readonly WriterInterface $configWriter,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly Random $random,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $adminUser = $this->_auth->getUser();
            if ($adminUser === null || (int) $adminUser->getId() === 0) {
                throw new LocalizedException(__('No active admin session — log in and click Install again.'));
            }

            // Reuse the existing secret if one's already there so re-running
            // Install from a different admin doesn't invalidate live widgets.
            $existing = (string) $this->scopeConfig->getValue(self::CONFIG_PATH_SECRET);
            $secret = $existing !== '' ? $this->encryptor->decrypt($existing) : '';
            if ($secret === '') {
                $secret = $this->random->getRandomString(self::SECRET_LENGTH);
                $this->configWriter->save(
                    self::CONFIG_PATH_SECRET,
                    $this->encryptor->encrypt($secret),
                );
            }
            $this->configWriter->save(self::CONFIG_PATH_ADMIN_ID, (string) $adminUser->getId());

            $storeUrl = (string) $this->storeManager->getStore()->getBaseUrl();
            $returnUrl = $this->getUrl('adminhtml/system_config/edit', ['section' => 'wiswes_widget']);
            $params = http_build_query([
                'install_token' => $secret,
                'store_url' => $storeUrl,
                'integration_name' => self::INTEGRATION_NAME,
                'return_url' => $returnUrl,
            ]);

            return $resultRedirect->setUrl($this->_resolveWiswesUrl() . '?' . $params);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                'WisWes install failed: ' . $e->getMessage()
                . ' — you can still install manually by pasting the embed snippet from your WisWes dashboard '
                . 'into Stores → Configuration → Design → HTML Head.'
            );
            return $resultRedirect->setPath('adminhtml/system_config/edit', ['section' => 'wiswes_widget']);
        }
    }

    private function _resolveWiswesUrl(): string
    {
        // Admins configure the dashboard base URL under
        // Stores → Configuration → WisWes Chat → MCP Connection. We always append
        // the install handshake path so the field stays a clean host setting.
        $base = (string) $this->scopeConfig->getValue('wiswes_mcp/install/wiswes_url');
        if ($base === '') {
            $base = 'https://api.wiswes.com/';
        }
        return rtrim($base, '/') . '/connect/magento';
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
