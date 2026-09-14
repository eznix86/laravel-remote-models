<?php

declare(strict_types=1);

namespace RemoteModels\Concerns;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RemoteModels\Attributes\Fields;
use RemoteModels\Attributes\Filters;
use RemoteModels\Attributes\Paging;
use RemoteModels\Exceptions\UnsupportedQuery;
use Stringable;
use UnitEnum;

trait TranslatesRemoteQueries
{
    use ReadsRemoteAttributes;

    /**
     * @var array<string, string>
     */
    protected array $remoteOperators = [
        '!=' => 'ne',
        '<>' => 'ne',
        '<' => 'lt',
        '<=' => 'lte',
        '>' => 'gt',
        '>=' => 'gte',
        'like' => 'like',
        'not like' => 'nlike',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toQuery(QueryBuilder $query): array
    {
        $parameters = $this->translateWheres($query->wheres, []);

        $parameters = $this->translateColumns($query->columns, $parameters);

        $parameters = $this->translateOrders(is_array($query->orders) ? $query->orders : [], $parameters);

        return $this->translatePaging($query->limit, $query->offset, $parameters);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateColumns(mixed $columns, array $parameters): array
    {
        $fields = static::remoteAttribute(Fields::class);

        if (! $fields instanceof Fields || ! is_array($columns)) {
            return $parameters;
        }

        $names = [];

        foreach ($columns as $column) {
            if (is_string($column) && $column !== '*') {
                $names[] = $this->remoteColumn($column);
            }
        }

        if ($names !== []) {
            $parameters[$fields->parameter] = implode($fields->separator, $names);
        }

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $wheres
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateWheres(array $wheres, array $parameters): array
    {
        foreach ($wheres as $where) {
            if (is_array($where)) {
                $parameters = $this->translateWhere($where, $parameters);
            }
        }

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $where
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateWhere(array $where, array $parameters): array
    {
        if (($where['boolean'] ?? 'and') !== 'and') {
            throw UnsupportedQuery::orOperator(static::class);
        }

        $type = is_string($where['type'] ?? null) ? $where['type'] : 'Unknown';

        if ($type === 'Nested') {
            return $this->translateNested($where, $parameters);
        }

        $column = is_string($where['column'] ?? null) ? $this->remoteColumn($where['column']) : null;

        if ($column === null) {
            throw UnsupportedQuery::where($type, static::class);
        }

        return match ($type) {
            'Basic' => $this->translateBasic($where, $column, $parameters),
            'In' => $this->translateIn($where, $column, $parameters),
            'NotIn' => $this->filters()->compare($parameters, $column, 'nin', $this->remoteValues($where)),
            'Null' => $this->filters()->compare($parameters, $column, 'null', 'true'),
            'NotNull' => $this->filters()->compare($parameters, $column, 'null', 'false'),
            'between' => $this->translateBetween($where, $column, $parameters),
            default => throw UnsupportedQuery::where($type, static::class),
        };
    }

    /**
     * @param  array<array-key, mixed>  $where
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateNested(array $where, array $parameters): array
    {
        $nested = $where['query'] ?? null;

        if (! $nested instanceof QueryBuilder) {
            throw UnsupportedQuery::where('Nested', static::class);
        }

        return $this->translateWheres($nested->wheres, $parameters);
    }

    /**
     * @param  array<array-key, mixed>  $where
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateBasic(array $where, string $column, array $parameters): array
    {
        $operator = is_string($where['operator'] ?? null) ? $where['operator'] : '';
        $value = $this->remoteQueryValue($where['value'] ?? null);

        if ($operator === '=') {
            $parameters[$column] = $value;

            return $parameters;
        }

        $translated = $this->remoteOperators[strtolower($operator)] ?? null;

        if ($translated === null) {
            throw UnsupportedQuery::operator($operator, static::class);
        }

        return $this->filters()->compare($parameters, $column, $translated, $value);
    }

    /**
     * @param  array<array-key, mixed>  $where
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateIn(array $where, string $column, array $parameters): array
    {
        $parameters[$column] = $this->remoteValues($where);

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $where
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateBetween(array $where, string $column, array $parameters): array
    {
        $values = is_array($where['values'] ?? null) ? array_values($where['values']) : [];

        if (($where['not'] ?? false) === true || count($values) !== 2) {
            throw UnsupportedQuery::where('between', static::class);
        }

        $parameters = $this->filters()->compare($parameters, $column, 'gte', $this->remoteQueryValue($values[0]));

        return $this->filters()->compare($parameters, $column, 'lte', $this->remoteQueryValue($values[1]));
    }

    /**
     * @param  array<array-key, mixed>  $where
     */
    protected function remoteValues(array $where): string
    {
        $values = is_array($where['values'] ?? null) ? $where['values'] : [];

        return implode(',', array_map(fn (mixed $value): string => $this->remoteQueryValue($value), $values));
    }

    protected function remoteQueryValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Stringable => (string) $value,
            default => throw UnsupportedQuery::value(get_debug_type($value), static::class),
        };
    }

    protected function filters(): Filters
    {
        $filters = static::remoteAttribute(Filters::class);

        if (! $filters instanceof Filters) {
            throw UnsupportedQuery::filters(static::class);
        }

        return $filters;
    }

    /**
     * @param  array<array-key, mixed>  $orders
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translateOrders(array $orders, array $parameters): array
    {
        if ($orders === []) {
            return $parameters;
        }

        if (count($orders) > 1) {
            throw UnsupportedQuery::orders(static::class);
        }

        $order = reset($orders);

        if (! is_array($order) || ! is_string($order['column'] ?? null)) {
            throw UnsupportedQuery::orders(static::class);
        }

        $parameters['sort'] = $this->remoteColumn($order['column']);
        $parameters['direction'] = is_string($order['direction'] ?? null) ? $order['direction'] : 'asc';

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function translatePaging(mixed $limit, mixed $offset, array $parameters): array
    {
        $paging = static::remoteAttribute(Paging::class) ?? new Paging;

        if (is_int($limit) && $limit > 0) {
            $parameters[$paging->perPage] = $limit;
        }

        if (! is_int($offset) || $offset === 0) {
            return $parameters;
        }

        if ($paging->page === null || ! is_int($limit) || $limit <= 0 || $offset % $limit !== 0) {
            throw UnsupportedQuery::offset(static::class);
        }

        $parameters[$paging->page] = intdiv($offset, $limit) + 1;

        return $parameters;
    }

    protected function remoteColumn(string $column): string
    {
        $prefix = $this->getTable().'.';

        return str_starts_with($column, $prefix) ? substr($column, strlen($prefix)) : $column;
    }
}
