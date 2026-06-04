<?php

namespace Veloquent\Core\Domain\Ai\Agents;

use Laravel\Ai\Contracts\HasStructuredOutput;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class StructuredVeloquentAgent extends VeloquentAgent implements HasStructuredOutput
{
    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        if (empty($this->schema)) {
            return [];
        }

        if (array_is_list($this->schema)) {
            $mapped = $this->mapSchemaType($this->schema, $schema);
            return [
                'items' => $mapped->required(),
            ];
        }

        $properties = [];
        foreach ($this->schema as $key => $definition) {
            /** @var JsonSchema $schema */
            $mapped = $this->mapSchemaType($definition, $schema);
            $properties[$key] = $mapped->required();
        }

        return $properties;
    }

    /**
     * Recursively resolve a schema definition into standard JsonSchema types.
     */
    protected function mapSchemaType(mixed $definition, JsonSchema $schema): mixed
    {
        if (is_string($definition)) {
            return match (strtolower($definition)) {
                'integer', 'int' => $schema->integer(),
                'number', 'float' => $schema->number(),
                'boolean', 'bool' => $schema->boolean(),
                'array' => $schema->array()->items($schema->string()),
                default => $schema->string(),
            };
        }

        if (is_array($definition)) {
            // Check if it's a standard JSON Schema structured format
            if (isset($definition['type'])) {
                $type = strtolower((string) $definition['type']);
                if ($type === 'array') {
                    $itemsDef = $definition['items'] ?? 'string';
                    return $schema->array()->items($this->mapSchemaType($itemsDef, $schema));
                }
                if ($type === 'object') {
                    $propertiesDef = $definition['properties'] ?? [];
                    $mapped = [];
                    foreach ($propertiesDef as $k => $v) {
                        $mapped[$k] = $this->mapSchemaType($v, $schema);
                    }
                    return $schema->object($mapped);
                }

                return match ($type) {
                    'integer', 'int' => $schema->integer(),
                    'number', 'float' => $schema->number(),
                    'boolean', 'bool' => $schema->boolean(),
                    default => $schema->string(),
                };
            }

            // Check if it is a list representation (e.g. ['string'] or [ {...} ])
            if (array_is_list($definition)) {
                $itemsDef = !empty($definition) ? $definition[0] : 'string';
                return $schema->array()->items($this->mapSchemaType($itemsDef, $schema));
            }

            // Otherwise, treat as an associative array representing a simplified nested object
            $mapped = [];
            foreach ($definition as $k => $v) {
                $mapped[$k] = $this->mapSchemaType($v, $schema);
            }
            return $schema->object($mapped);
        }

        return $schema->string();
    }
}
