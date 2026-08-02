<?php

declare(strict_types=1);

namespace VoteTrust\Election\ORM;

use PDO;
use VoteTrust\Election\Core\Database;
use VoteTrust\Election\Exceptions\ConcurrencyException;

abstract class Model
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    /** @var list<string> */
    protected static array $fillable = [];

    protected static bool $timestamps = true;

    protected static ?string $versionColumn = null;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, mixed> */
    protected array $original = [];

    protected bool $exists = false;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function tableName(): string
    {
        return static::$table;
    }

    public static function query(): Query
    {
        return new Query(static::class);
    }

    public static function where(string $column, mixed $value, string $operator = '='): Query
    {
        return static::query()->where($column, $value, $operator);
    }

    /** @return list<static> */
    public static function all(): array
    {
        /** @var list<static> $models */
        $models = static::query()->get();

        return $models;
    }

    public static function find(int|string $id): ?static
    {
        /** @var static|null $model */
        $model = static::where(static::$primaryKey, $id)->first();

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    public static function hydrate(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;
        $model->exists = true;

        return $model;
    }

    /** @param array<string, mixed> $attributes */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function set(string $key, mixed $value): static
    {
        if (!in_array($key, static::$fillable, true)) {
            throw new \InvalidArgumentException("Attribute is not fillable: {$key}");
        }
        $this->attributes[$key] = $value;

        return $this;
    }

    public function id(): int
    {
        return (int) $this->get(static::$primaryKey);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function save(): void
    {
        $now = date('Y-m-d H:i:s');
        if (static::$timestamps) {
            $this->attributes['updated_at'] = $now;
            if (!$this->exists) {
                $this->attributes['created_at'] = $now;
            }
        }

        if (!$this->exists) {
            $this->insert();

            return;
        }

        $this->update();
    }

    private function insert(): void
    {
        if (static::$versionColumn !== null && !isset($this->attributes[static::$versionColumn])) {
            $this->attributes[static::$versionColumn] = 1;
        }

        $columns = array_keys($this->attributes);
        if ($columns === []) {
            throw new \RuntimeException('Cannot insert a model without attributes.');
        }

        $quoted = array_map(static fn (string $column): string => "`{$column}`", $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            static::$table,
            implode(', ', $quoted),
            $placeholders
        );

        $statement = Database::connection()->prepare($sql);
        $statement->execute(array_values($this->attributes));

        if (!isset($this->attributes[static::$primaryKey])) {
            $this->attributes[static::$primaryKey] = (int) Database::connection()->lastInsertId();
        }
        $this->exists = true;
        $this->original = $this->attributes;
    }

    private function update(): void
    {
        $primaryKey = static::$primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;
        if ($id === null) {
            throw new \RuntimeException('Cannot update a model without a primary key.');
        }

        $columns = array_values(array_filter(
            array_keys($this->attributes),
            static fn (string $column): bool => $column !== $primaryKey
        ));

        $versionColumn = static::$versionColumn;
        $oldVersion = null;
        if ($versionColumn !== null) {
            $oldVersion = (int) ($this->original[$versionColumn] ?? 1);
            $this->attributes[$versionColumn] = $oldVersion + 1;
        }

        $assignments = array_map(static fn (string $column): string => "`{$column}` = ?", $columns);
        $parameters = array_map(fn (string $column): mixed => $this->attributes[$column], $columns);
        $sql = sprintf('UPDATE `%s` SET %s WHERE `%s` = ?', static::$table, implode(', ', $assignments), $primaryKey);
        $parameters[] = $id;

        if ($versionColumn !== null) {
            $sql .= " AND `{$versionColumn}` = ?";
            $parameters[] = $oldVersion;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        if ($versionColumn !== null && $statement->rowCount() !== 1) {
            $this->attributes[$versionColumn] = $oldVersion;
            throw new ConcurrencyException();
        }

        $this->original = $this->attributes;
    }

    public function delete(): void
    {
        if (!$this->exists) {
            return;
        }

        $statement = Database::connection()->prepare(
            sprintf('DELETE FROM `%s` WHERE `%s` = ?', static::$table, static::$primaryKey)
        );
        $statement->execute([$this->attributes[static::$primaryKey]]);
        $this->exists = false;
    }
}

