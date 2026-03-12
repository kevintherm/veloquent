<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

readonly class SchemaDDLService
{
    public function __construct(
        private SchemaPolicy $namingPolicy
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function createTable(string $table, array $columns): void
    {
        $this->namingPolicy->assertValidTableName($table);

        // Conflict action: fail if table exists

        Schema::create($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->ulid('id')->primary();

            foreach ($columns as $column) {
                $this->columnBlueprint($blueprint, $column);
            }

            $blueprint->timestamp('created_at')->useCurrent();
            $blueprint->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function renameTable(string $from, string $to): void
    {
        $this->namingPolicy->assertValidTableName($to);

        if (Schema::hasTable($to)) {
            throw new InvalidArgumentException('Table already exists');
        }

        if (! Schema::hasTable($from)) {
            throw new InvalidArgumentException('Table does not exist');
        }

        Schema::rename($from, $to);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function columnBlueprint(Blueprint $blueprint, array $column, ?string $after = null, bool $change = false): void
    {
        $this->namingPolicy->assertValidColumnDefinition($column);

        $name = $column['name'];
        $type = CollectionFieldType::tryFrom($column['type']);

        $col = match ($type) {
            CollectionFieldType::Text => $blueprint->text($name),
            CollectionFieldType::Number => $blueprint->float($name),
            CollectionFieldType::Boolean => $blueprint->boolean($name),
            CollectionFieldType::Datetime => $blueprint->timestamp($name),
            CollectionFieldType::Email => $blueprint->text($name),
            CollectionFieldType::Url => $blueprint->text($name),
            CollectionFieldType::Json => $blueprint->json($name),
            CollectionFieldType::Relation => $blueprint->char($name, 26),
            default => throw new InvalidArgumentException('Unsupported column type: '.$type)
        };

        $col->nullable();

        if (($column['unique'] ?? false) === true) {
            $col->unique();
        }

        if (array_key_exists('default', $column)) {
            $col->default($column['default']);
        }

        if ($change) {
            $col->change();
        }

        if ($after) {
            $col->after($after);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function updateTable(string $table, array $before, array $after): void
    {
        $plan = SchemaChangePlan::buildPlan($before, $after);

        Schema::table($table, function (Blueprint $t) use ($plan) {
            foreach ($plan->renames as [$from, $to]) {
                $t->renameColumn($from, $to);
            }

            foreach ($plan->adds as $field) {
                $this->columnBlueprint($t, $field, after: 'id');
            }

            foreach ($plan->modifies as [, $field]) {
                $this->columnBlueprint($t, $field, after: 'id', change: true);
            }

            foreach ($plan->drops as $field) {
                $t->dropColumn($field['name']);
            }
        });
    }

    public function deleteTable(string $table): void
    {
        Schema::dropIfExists($table);
    }
}
