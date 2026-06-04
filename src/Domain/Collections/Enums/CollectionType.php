<?php

namespace Veloquent\Core\Domain\Collections\Enums;

use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;

enum CollectionType: string
{
    case Base = 'base';
    case Auth = 'auth';
    case Agents = 'agents';

    public function additionalRules(): array
    {
        return match ($this) {
            self::Auth => ['manage'],
            self::Agents => ['chat'],
            default => [],
        };
    }

    public static function parse(self|string|bool|null $collectionType): self
    {
        if ($collectionType instanceof self) {
            return $collectionType;
        }

        // Backward compability support
        if (is_bool($collectionType)) {
            return $collectionType ? self::Auth : self::Base;
        }

        if ($collectionType === null) {
            return self::Base;
        }

        return self::tryFrom((string) $collectionType) ?? self::Base;
    }

    public function reservedFields(): array
    {
        return array_column(
            $this->systemFields(), 
            'name'
        );
    }

    public function systemFields(): array
    {
        $base = [
            ['name' => 'id', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => true],
            ['name' => 'created_at', 'type' => CollectionFieldType::Datetime->value, 'nullable' => false, 'unique' => false],
            ['name' => 'updated_at', 'type' => CollectionFieldType::Datetime->value, 'nullable' => false, 'unique' => false],
        ];

        $extra = match ($this) {
            self::Auth => [
                ['name' => 'email', 'type' => CollectionFieldType::Email->value, 'nullable' => false, 'unique' => true],
                ['name' => 'password', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => false],
                ['name' => 'email_visibility', 'type' => CollectionFieldType::Boolean->value, 'nullable' => true, 'unique' => false, 'default' => true],
                ['name' => 'verified', 'type' => CollectionFieldType::Boolean->value, 'nullable' => true, 'unique' => false, 'default' => false],
            ],
            self::Agents => [
                ['name' => 'name', 'type' => CollectionFieldType::Text->value, 'nullable' => false, 'unique' => true],
                ['name' => 'type', 'type' => CollectionFieldType::Select->value, 'options' => ['regular', 'watcher'], 'nullable' => true, 'unique' => false, 'default' => 'regular'],
                ['name' => 'system_prompt', 'type' => CollectionFieldType::LongText->value, 'nullable' => true, 'unique' => false],
                ['name' => 'model', 'type' => CollectionFieldType::Text->value, 'nullable' => true, 'unique' => false],
                ['name' => 'temperature', 'type' => CollectionFieldType::Number->value, 'allow_decimals' => true, 'min' => 0, 'max' => 1, 'nullable' => true, 'unique' => false],
                ['name' => 'tone', 'type' => CollectionFieldType::Text->value, 'nullable' => true, 'unique' => false],
                ['name' => 'length', 'type' => CollectionFieldType::Text->value, 'nullable' => true, 'unique' => false],
                ['name' => 'output_type', 'type' => CollectionFieldType::Select->value, 'options' => ['text', 'json'], 'nullable' => true, 'unique' => false],
                ['name' => 'schema', 'type' => CollectionFieldType::Json->value, 'nullable' => true, 'unique' => false],
                ['name' => 'watcher_message', 'type' => CollectionFieldType::Text->value, 'nullable' => true, 'unique' => false],
                ['name' => 'watchers', 'type' => CollectionFieldType::RelationMany->value, 'target_collection_id' => '@self', 'nullable' => true, 'unique' => false],
            ],
            default => [],
        };

        return array_map([self::class, 'normalizeFieldDefinition'], array_merge($base, $extra));
    }

    private static function normalizeFieldDefinition(array $field): array
    {
        $type = CollectionFieldType::from($field['type']);
        return collect([...$type->defaultShape(), ...$field, 'type' => $type->value])
            ->only($type->allowedProperties())
            ->all();
    }
}
