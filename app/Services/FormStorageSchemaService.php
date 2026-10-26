<?php

namespace App\Services;

use App\Models\FormField;
use App\Support\FormFieldNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormStorageSchemaService
{
    private const CACHE_SECONDS = 60;

    private const WIDE_NUMBER_PRECISION = 65;

    private const WIDE_NUMBER_SCALE = 2;

    /** @var list<string> */
    private const SYSTEM_COLUMNS = [
        'id', 'created_at', 'updated_at', 'date', 'request_id', 'agent', 'lead_id', 'phone_number',
    ];

    public function provision(string $tableName, Collection $fields): void
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            throw new \InvalidArgumentException('Invalid form storage table name.');
        }

        $this->invalidate($tableName);

        $fieldMap = $this->resolveStorageFieldMap($tableName, $fields);
        foreach ($fields as $field) {
            $field->setAttribute('storage_field_name', $fieldMap[$field->field_name] ?? $field->field_name);
        }

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->date('date')->index();
                $table->string('request_id', 255)->index();
                $table->string('agent', 255)->index();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn($tableName, 'date')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->date('date')->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'request_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('request_id', 255)->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'agent')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('agent', 255)->index();
                });
            }
            if (! Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->timestamp('created_at')->nullable();
                });
            }
            if (! Schema::hasColumn($tableName, 'updated_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->timestamp('updated_at')->nullable();
                });
            }
        }

        $missingFieldColumns = [];
        foreach ($fields as $field) {
            $columnName = $field->storage_field_name ?? $field->field_name;
            if (in_array($columnName, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, $columnName)) {
                $missingFieldColumns[] = $field;
            }
        }

        if ($missingFieldColumns !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($missingFieldColumns): void {
                foreach ($missingFieldColumns as $field) {
                    /** @var FormField $field */
                    $columnName = $field->storage_field_name ?? $field->field_name;
                    // Historical rows cannot supply a value for a newly added field.
                    // Keep the new physical column nullable; request validation still
                    // enforces required values for all new submissions.
                    $nullable = true;
                    switch ((string) $field->field_type) {
                        case 'textarea':
                            $table->text($columnName)->nullable($nullable);

                            break;
                        case 'date':
                            $table->date($columnName)->nullable($nullable);

                            break;
                        case 'select':
                            $table->string($columnName, 255)->nullable($nullable);

                            break;
                        case 'multiselect':
                            $table->text($columnName)->nullable($nullable);

                            break;
                        case 'number':
                            if ($this->isIdentifierField($columnName)) {
                                $table->string($columnName, 255)->nullable($nullable);
                            } else {
                                $table->decimal($columnName, self::WIDE_NUMBER_PRECISION, self::WIDE_NUMBER_SCALE)->nullable($nullable);
                            }

                            break;
                        case 'percentage':
                            $table->string($columnName, 50)->nullable($nullable);

                            break;
                        default:
                            $table->string($columnName, 255)->nullable($nullable);

                            break;
                    }
                }
            });
        }

        $this->alignNumericStorageColumns($tableName, $fields);
        $this->alignStorageNullability($tableName, $fields);
        $this->invalidate($tableName);
    }

    /**
     * Check storage against the current field definition without creating or altering anything.
     * The field fingerprint acts as a short-lived readiness version for this form configuration.
     */
    public function isReady(string $tableName, Collection $fields): bool
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            return false;
        }

        $signature = $fields->map(fn ($field): array => [
            $field->field_name,
            $field->storage_field_name ?? $field->field_name,
            $field->field_type,
            (bool) $field->is_required,
            $field->visibility,
        ])->values()->all();
        $cacheKey = $this->readinessCacheKey($tableName, $signature);

        return Cache::remember($cacheKey, self::CACHE_SECONDS, fn (): bool => $this->inspectReadiness($tableName, $fields));
    }

    /** @return array<string, string> configured field name => physical storage column */
    public function resolveStorageFieldMap(string $tableName, Collection $fields): array
    {
        $storageColumns = $this->columnListing($tableName);
        if ($storageColumns === []) {
            return [];
        }

        $fieldNames = $fields->pluck('field_name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
        $unmatchedFields = array_values(array_diff($fieldNames, $storageColumns));
        $unrepresentedColumns = array_values(array_diff(array_diff($storageColumns, self::SYSTEM_COLUMNS), $fieldNames));

        return count($unmatchedFields) === 1 && count($unrepresentedColumns) === 1
            ? [$unmatchedFields[0] => $unrepresentedColumns[0]]
            : [];
    }

    public function storesPercentageAsNumeric(string $tableName, string $columnName): bool
    {
        $column = $this->columnDetails($tableName)->get($columnName);
        if (! is_array($column)) {
            return false;
        }

        return in_array(strtolower((string) ($column['type_name'] ?? '')), [
            'bigint', 'decimal', 'double', 'float', 'integer', 'numeric', 'smallint', 'tinyint',
        ], true);
    }

    public function invalidate(string $tableName): void
    {
        $generationKey = $this->generationCacheKey($tableName);
        if (! Cache::add($generationKey, 1, now()->addDays(30))) {
            Cache::increment($generationKey);
        }
    }

    private function inspectReadiness(string $tableName, Collection $fields): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        $columns = $this->columnDetails($tableName);
        foreach (['date', 'request_id', 'agent', 'created_at', 'updated_at'] as $requiredColumn) {
            if (! $columns->has($requiredColumn)) {
                return false;
            }
        }

        $activeFields = $fields->keyBy(fn ($field): string => (string) ($field->storage_field_name ?? $field->field_name));
        foreach ($fields as $field) {
            $name = (string) ($field->storage_field_name ?? $field->field_name);
            if (in_array($name, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            $column = $columns->get($name);
            if (! is_array($column)) {
                return false;
            }
            if ((! $field->is_required || ! empty($field->visibility)) && ! ($column['nullable'] ?? false)) {
                return false;
            }
        }

        foreach ($columns as $name => $column) {
            if (in_array($name, self::SYSTEM_COLUMNS, true)
                || ($column['nullable'] ?? true)
                || ($column['auto_increment'] ?? false)
                || ($column['generation'] ?? null) !== null) {
                continue;
            }
            if ($activeFields->has($name)) {
                continue;
            }
            if (($column['default'] ?? null) === null) {
                return false;
            }
        }

        foreach ($fields as $field) {
            $name = (string) ($field->storage_field_name ?? $field->field_name);
            if (in_array($name, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            $column = $columns->get($name);
            if (! is_array($column)) {
                continue;
            }
            $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
            if (! $this->isNumericColumnType($type)) {
                continue;
            }
            if ($this->isIdentifierField($name)) {
                return false;
            }
            if ((string) $field->field_type === 'number' && ! $this->isWideDecimalColumn($column)) {
                return false;
            }
        }

        return true;
    }

    private function alignStorageNullability(string $tableName, Collection $fields): void
    {
        $activeFields = $fields->keyBy(fn ($field): string => $field->storage_field_name ?? $field->field_name);
        $columns = array_filter($this->columnDetails($tableName)->all(), function (array $column) use ($activeFields): bool {
            $name = $column['name'];
            if (in_array($name, self::SYSTEM_COLUMNS, true)
                || $column['nullable']
                || $column['auto_increment']
                || $column['generation'] !== null) {
                return false;
            }

            $field = $activeFields->get($name);

            return $field !== null
                ? (! $field->is_required || ! empty($field->visibility))
                : $column['default'] === null;
        });

        if ($columns === []) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $extras = in_array($driver, ['mysql', 'mariadb'], true)
            ? collect($connection->select('SHOW FULL COLUMNS FROM '.$connection->getQueryGrammar()->wrapTable($tableName)))->keyBy('Field')
            : collect();

        Schema::table($tableName, function (Blueprint $table) use ($columns, $driver, $connection, $extras): void {
            foreach ($columns as $column) {
                $definition = $table->rawColumn($column['name'], $column['type'])->nullable()->change();
                if ($column['collation'] !== null) {
                    $definition->collation($column['collation']);
                }
                if ($column['comment'] !== null) {
                    $definition->comment($column['comment']);
                }

                $extra = strtolower((string) ($extras->get($column['name'])->Extra ?? ''));
                if ($column['default'] !== null) {
                    $rawDefault = $driver === 'sqlite'
                        || $driver === 'mariadb'
                        || ($driver === 'mysql' && $connection->isMaria())
                        || str_contains($extra, 'default_generated');
                    $definition->default($rawDefault ? DB::raw($column['default']) : $column['default']);
                }
                if (str_contains($extra, 'on update current_timestamp')) {
                    $definition->useCurrentOnUpdate();
                }
                if (str_contains($extra, 'invisible')) {
                    $definition->invisible();
                }
            }
        });
    }

    private function alignNumericStorageColumns(string $tableName, Collection $fields): void
    {
        $needsAlignment = $fields->contains(function ($field): bool {
            $name = $field->storage_field_name ?? $field->field_name;

            return (string) $field->field_type === 'number' || $this->isIdentifierField($name);
        });
        if (! $needsAlignment) {
            return;
        }

        $columns = $this->columnDetails($tableName);
        $stringColumns = [];
        $wideNumberColumns = [];
        foreach ($fields as $field) {
            $name = $field->storage_field_name ?? $field->field_name;
            if (in_array($name, self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            $column = $columns->get($name);
            if (! is_array($column) || ! $this->isNumericColumnType(strtolower((string) ($column['type_name'] ?? $column['type'] ?? '')))) {
                continue;
            }

            $nullable = (bool) ($column['nullable'] ?? true);
            if ($this->isIdentifierField($name)) {
                $stringColumns[$name] = $nullable;
            } elseif ((string) $field->field_type === 'number' && ! $this->isWideDecimalColumn($column)) {
                $wideNumberColumns[$name] = $nullable;
            }
        }

        if ($stringColumns === [] && $wideNumberColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($stringColumns, $wideNumberColumns): void {
            foreach ($stringColumns as $name => $nullable) {
                $table->string($name, 255)->nullable($nullable)->change();
            }
            foreach ($wideNumberColumns as $name => $nullable) {
                $table->decimal($name, self::WIDE_NUMBER_PRECISION, self::WIDE_NUMBER_SCALE)->nullable($nullable)->change();
            }
        });
    }

    private function isIdentifierField(string $columnName): bool
    {
        return FormFieldNumber::isIdentifierLike($columnName);
    }

    private function isNumericColumnType(string $columnType): bool
    {
        return preg_match('/^(bigint|decimal|double|float|integer|mediumint|numeric|real|smallint|tinyint)/', $columnType) === 1;
    }

    /** @param array<string, mixed> $column */
    private function isWideDecimalColumn(array $column): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite'
            && strtolower((string) ($column['type_name'] ?? '')) === 'numeric') {
            return true;
        }

        return preg_match('/decimal\(\s*'.self::WIDE_NUMBER_PRECISION.'\s*,\s*'.self::WIDE_NUMBER_SCALE.'\s*\)/', strtolower((string) ($column['type'] ?? ''))) === 1;
    }

    /** @return list<string> */
    private function columnListing(string $tableName): array
    {
        return Cache::remember(
            $this->tableCacheKey($tableName, 'columns'),
            self::CACHE_SECONDS,
            fn (): array => Schema::hasTable($tableName) ? Schema::getColumnListing($tableName) : [],
        );
    }

    /** @return Collection<string, array<string, mixed>> */
    private function columnDetails(string $tableName): Collection
    {
        return Cache::remember(
            $this->tableCacheKey($tableName, 'details'),
            self::CACHE_SECONDS,
            fn (): Collection => Schema::hasTable($tableName)
                ? collect(Schema::getColumns($tableName))->keyBy('name')
                : collect(),
        );
    }

    /** @param array<mixed> $signature */
    private function readinessCacheKey(string $tableName, array $signature): string
    {
        $generation = (int) Cache::get($this->generationCacheKey($tableName), 0);

        return 'form-storage-readiness:v1:'.hash('sha256', $tableName.'|'.$generation.'|'.json_encode($signature));
    }

    private function generationCacheKey(string $tableName): string
    {
        return 'form-storage-generation:'.hash('sha256', $tableName);
    }

    private function tableCacheKey(string $tableName, string $kind): string
    {
        $generation = (int) Cache::get($this->generationCacheKey($tableName), 0);

        return 'form-storage-table:'.$kind.':'.hash('sha256', $tableName.'|'.$generation);
    }
}
