<?php

declare(strict_types=1);

namespace VoteTrust\Election\ORM;

use PDO;
use VoteTrust\Election\Core\Database;

final class Query
{
    /** @var list<array{column:string,operator:string,value:mixed}> */
    private array $conditions = [];

    /** @var list<array{column:string,direction:string}> */
    private array $orders = [];

    private ?int $limit = null;

    /** @param class-string<Model> $modelClass */
    public function __construct(private readonly string $modelClass)
    {
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->assertIdentifier($column);
        $allowedOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE'];
        $operator = strtoupper($operator);
        if (!in_array($operator, $allowedOperators, true)) {
            throw new \InvalidArgumentException('Unsupported query operator.');
        }

        $clone = clone $this;
        $clone->conditions[] = compact('column', 'operator', 'value');

        return $clone;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->assertIdentifier($column);
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Order direction must be ASC or DESC.');
        }

        $clone = clone $this;
        $clone->orders[] = compact('column', 'direction');

        return $clone;
    }

    public function limit(int $limit): self
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Query limit must be between 1 and 1000.');
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    /** @return list<Model> */
    public function get(): array
    {
        [$whereSql, $parameters] = $this->compileWhere();
        $table = $this->modelClass::tableName();
        $sql = "SELECT * FROM `{$table}`{$whereSql}";

        if ($this->orders !== []) {
            $orderParts = array_map(
                static fn (array $order): string => "`{$order['column']}` {$order['direction']}",
                $this->orders
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return array_map(
            fn (array $row): Model => $this->modelClass::hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function first(): ?Model
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function count(): int
    {
        [$whereSql, $parameters] = $this->compileWhere();
        $table = $this->modelClass::tableName();
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM `{$table}`{$whereSql}");
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @return array{0:string,1:list<mixed>} */
    private function compileWhere(): array
    {
        if ($this->conditions === []) {
            return ['', []];
        }

        $parts = [];
        $parameters = [];
        foreach ($this->conditions as $condition) {
            $parts[] = "`{$condition['column']}` {$condition['operator']} ?";
            $parameters[] = $condition['value'];
        }

        return [' WHERE ' . implode(' AND ', $parts), $parameters];
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Unsafe database identifier.');
        }
    }
}

