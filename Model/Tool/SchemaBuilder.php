<?php
declare(strict_types=1);

namespace WisWes\MCP\Model\Tool;

use Magento\Framework\Reflection\MethodsMap;
use Magento\Framework\Reflection\TypeProcessor;

/**
 * Builds a JSON Schema describing the input arguments of a Magento service
 * contract method, using the same reflection machinery Magento uses for SOAP
 * WSDL generation. The result feeds straight into the MCP tool definition so
 * the LLM sees real Magento types instead of generic blobs.
 *
 * Falls back to a permissive `object` schema for parameters whose type cannot
 * be reflected (e.g. mixed, callable). DTO walking is shallow — one level
 * deep — to keep the resulting schemas readable. Deep DTOs are exposed as
 * `type: object` with a description pointing at the PHP class so the LLM can
 * infer structure from the type name.
 */
class SchemaBuilder
{
    public function __construct(
        private readonly MethodsMap $methodsMap,
        private readonly TypeProcessor $typeProcessor,
    ) {}

    /**
     * @return array{type: 'object', properties: array<string, array>, required: list<string>}
     */
    public function build(string $serviceClass, string $serviceMethod): array
    {
        $params = $this->methodsMap->getMethodParams($serviceClass, $serviceMethod);

        $properties = [];
        $required = [];
        foreach ($params as $param) {
            $name = $param[MethodsMap::METHOD_META_NAME] ?? null;
            if ($name === null) {
                continue;
            }

            $type = $param[MethodsMap::METHOD_META_TYPE] ?? 'string';
            $isDefault = $param[MethodsMap::METHOD_META_HAS_DEFAULT_VALUE] ?? false;

            $properties[$name] = $this->jsonSchemaForType((string) $type);

            if (!$isDefault) {
                $required[] = $name;
            }
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
            'required'   => $required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSchemaForType(string $phpType): array
    {
        $isArray = str_ends_with($phpType, '[]');
        $base = $isArray ? substr($phpType, 0, -2) : $phpType;

        $primitive = $this->mapPrimitive($base);
        if ($primitive !== null) {
            return $isArray
                ? ['type' => 'array', 'items' => ['type' => $primitive]]
                : ['type' => $primitive];
        }

        // Complex type → expose as object and hint at the PHP class.
        $description = sprintf('Magento DTO: %s', $base);
        return $isArray
            ? ['type' => 'array', 'items' => ['type' => 'object', 'description' => $description]]
            : ['type' => 'object', 'description' => $description];
    }

    private function mapPrimitive(string $type): ?string
    {
        return match (strtolower($type)) {
            'string', 'str'             => 'string',
            'int', 'integer'            => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean'           => 'boolean',
            'array'                     => 'array',
            default                     => null,
        };
    }
}
