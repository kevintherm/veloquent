<?php

namespace Veloquent\Core\Domain\Collections\Enums;

enum CollectionFieldType: string
{
    case Text = 'text';
    case LongText = 'longtext';
    case RichText = 'richtext';
    case Number = 'number';
    case Boolean = 'boolean';
    case Datetime = 'datetime';
    case Date = 'date';
    case Email = 'email';
    case Url = 'url';
    case Json = 'json';
    case File = 'file';
    case Relation = 'relation';
    case RelationMany = 'relation_many';
    case Select = 'select';

    public function typeProperties(): array
    {
        return match ($this) {
            self::Text => ['min' => null, 'max' => null],
            self::Email => ['min' => null, 'max' => null],
            self::Url => ['min' => null, 'max' => null],
            self::File => ['multiple' => false, 'min' => null, 'max' => null, 'max_size_kb' => null, 'allowed_mime_types' => [], 'protected' => false],
            self::LongText => [],
            self::RichText => [],
            self::Relation => ['target_collection_id' => null, 'cascade_on_delete' => false],
            self::RelationMany => ['target_collection_id' => null, 'pivot_fields' => []],
            self::Number => ['min' => null, 'max' => null, 'allow_decimals' => false],
            self::Select => ['options' => []],
            self::Boolean, self::Datetime, self::Date, self::Json => [],
        };
    }

    public function allowedProperties(): array
    {
        $properties = [...self::commonPropertyNames(), ...array_keys($this->typeProperties())];

        if ($this === self::File) {
            return array_values(array_filter($properties, static fn (string $property): bool => $property !== 'default'));
        }

        return $properties;
    }

    public function defaultShape(): array
    {
        return array_merge(self::commonDefaults(), $this->typeProperties());
    }

    public function typeValidationRules(string $prefix, bool $skipRelationExists = false): array
    {
        return match ($this) {
            self::Text, self::Email, self::Url => [
                "{$prefix}.min" => ['nullable', 'integer', 'min:0'],
                "{$prefix}.max" => ['nullable', 'integer', 'min:1'],
            ],
            self::File => [
                "{$prefix}.multiple" => ['sometimes', 'boolean'],
                "{$prefix}.min" => ['nullable', 'integer', 'min:0', "lte:{$prefix}.max"],
                "{$prefix}.max" => ['nullable', 'integer', 'min:1', "gte:{$prefix}.min"],
                "{$prefix}.max_size_kb" => ['nullable', 'integer', 'min:1'],
                "{$prefix}.allowed_mime_types" => ['nullable', 'array'],
                "{$prefix}.allowed_mime_types.*" => ['required', 'string', 'min:1'],
                "{$prefix}.protected" => ['sometimes', 'boolean'],
            ],
            self::Number => [
                "{$prefix}.min" => ['nullable', 'numeric'],
                "{$prefix}.max" => ['nullable', 'numeric'],
                "{$prefix}.allow_decimals" => ['sometimes', 'boolean'],
            ],
            self::Relation => [
                "{$prefix}.target_collection_id" => array_merge(
                    ['required', 'string'],
                    $skipRelationExists ? [] : ['exists:collections,id']
                ),
                "{$prefix}.cascade_on_delete" => ['sometimes', 'boolean'],
            ],
            self::RelationMany => [
                "{$prefix}.target_collection_id" => array_merge(
                    ['required', 'string'],
                    $skipRelationExists ? [] : ['exists:collections,id']
                ),
                "{$prefix}.pivot_fields" => ['sometimes', 'array'],
                "{$prefix}.pivot_fields.*.name" => ['required', 'string'],
                "{$prefix}.pivot_fields.*.type" => ['required', 'string', 'in:text,datetime,json,number,select,boolean,longtext'],
            ],
            self::Select => [
                "{$prefix}.options" => ['required', 'array', 'min:1'],
                "{$prefix}.options.*" => ['required', 'string', 'min:1', 'max:255'],
            ],
            self::LongText, self::RichText, self::Boolean, self::Datetime, self::Date, self::Json => [],
        };
    }

    public function recordValidationRule(): string
    {
        return match ($this) {
            self::Text, self::LongText, self::RichText, self::Email => 'string',
            self::Number => 'numeric',
            self::Boolean => 'boolean',
            self::Datetime, self::Date => 'date',
            self::Url => 'url',
            self::Json => 'json',
            self::File => 'array',
            self::RelationMany => 'array',
            self::Relation, self::Select => 'string',
        };
    }

    public function eloquentCast(): ?string
    {
        return match ($this) {
            self::Number => 'float',
            self::Boolean => 'boolean',
            self::Datetime => 'datetime',
            self::Date => 'date',
            self::Json => 'object',
            self::File => 'json',
            default => null,
        };
    }

    public function isIndexable(): bool
    {
        return ! in_array($this, [self::Json, self::LongText, self::Url, self::File, self::RelationMany], true);
    }

    public static function commonPropertyNames(): array
    {
        return ['id', 'name', 'type', 'order', 'nullable', 'unique', 'default'];
    }

    public static function commonDefaults(): array
    {
        return [
            'order' => 0,
            'nullable' => false,
            'unique' => false,
            'default' => null,
        ];
    }
}
