<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Dispatch;

use WisWes\MCP\Model\Auth\McpUserContext;
use Magento\Framework\Api\SimpleDataObjectConverter;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Reflection\MethodsMap;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Invokes a Magento service-contract method the same way the REST controller
 * does — minus the HTTP layer.
 *
 * Steps:
 *   1. Reset the MCP user context so the per-call bearer token is re-applied.
 *   2. Resolve the service class via ObjectManager.
 *   3. Use {@see ServiceInputProcessor} (Magento's own request->args mapper)
 *      to convert the LLM-supplied associative array into typed DTOs that the
 *      service method expects. This is exactly what
 *      \Magento\Webapi\Controller\Rest\InputParamsResolver does internally.
 *   4. Invoke the method, return the (already typed) result. Conversion to
 *      JSON-serializable form is left to the caller / php-mcp serializer.
 *
 * Note: ACL is enforced by the service implementation itself when it consults
 * the user context. Methods marked `<resources ref="anonymous"/>` in webapi.xml
 * are callable without a bearer token; everything else requires one that maps
 * to a user with the right ACL resource.
 */
class ServiceContractDispatcher
{
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly ServiceInputProcessor $inputProcessor,
        private readonly MethodsMap $methodsMap,
        private readonly McpUserContext $userContext,
    ) {}

    /**
     * @param class-string         $serviceClass
     * @param string               $serviceMethod
     * @param array<string, mixed> $arguments  Associative array of method arg name => value
     * @return mixed
     * @throws WebapiException
     * @throws \Throwable
     */
    public function dispatch(string $serviceClass, string $serviceMethod, array $arguments): mixed
    {
        $this->userContext->reset();

        // Service contracts are typically defined as interfaces; resolve to the
        // concrete preference via ObjectManager.
        $service = $this->objectManager->get($serviceClass);

        // Magento's input processor expects camelCase keys; tolerate snake_case
        // by normalizing here, matching what the REST controller does.
        $normalized = [];
        foreach ($arguments as $key => $value) {
            $normalized[SimpleDataObjectConverter::snakeCaseToCamelCase($key)] = $value;
        }

        $typedArgs = $this->inputProcessor->process($serviceClass, $serviceMethod, $normalized);

        // Sanity check: ensure the method actually exists on the resolved class.
        $this->methodsMap->getMethodReturnType($serviceClass, $serviceMethod);

        return $service->{$serviceMethod}(...$typedArgs);
    }
}
