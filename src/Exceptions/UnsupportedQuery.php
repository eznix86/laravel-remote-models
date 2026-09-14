<?php

declare(strict_types=1);

namespace RemoteModels\Exceptions;

use LogicException;

final class UnsupportedQuery extends LogicException
{
    public static function database(): self
    {
        return new self('Remote models do not use a database connection.');
    }

    public static function where(string $type, string $model): self
    {
        return new self("Remote model [{$model}] cannot translate a [{$type}] where clause into query parameters. Override toQuery() on the model.");
    }

    public static function operator(string $operator, string $model): self
    {
        return new self("Remote model [{$model}] cannot translate the [{$operator}] operator into query parameters. Override toQuery() on the model.");
    }

    public static function orOperator(string $model): self
    {
        return new self("Remote model [{$model}] cannot translate an orWhere clause into query parameters. Override toQuery() on the model.");
    }

    public static function value(string $type, string $model): self
    {
        return new self("Remote model [{$model}] cannot send a value of type [{$type}] as a query parameter. Override toQuery() on the model.");
    }

    public static function filters(string $model): self
    {
        return new self("Remote model [{$model}] has no filter style. Add #[BracketFilters] or #[SuffixFilters] to the model, or override toQuery().");
    }

    public static function relationExistence(string $model): self
    {
        return new self("Remote model [{$model}] cannot answer has() or whereHas(). Relation existence is a database join. Filter on a field the api exposes instead.");
    }

    public static function aggregate(string $model): self
    {
        return new self("Remote model [{$model}] cannot answer withCount() or withSum(). Read the relation instead.");
    }

    public static function orders(string $model): self
    {
        return new self("Remote model [{$model}] cannot translate more than one orderBy into query parameters. Override toQuery() on the model.");
    }

    public static function offset(string $model): self
    {
        return new self("Remote model [{$model}] can only translate an offset that is a whole number of pages. Override toQuery() on the model.");
    }

    public static function method(string $method, string $model): self
    {
        return new self("Remote model [{$model}] cannot send a [{$method}] request.");
    }

    public static function builder(string $builder, string $model): self
    {
        return new self("Remote model [{$model}] needs a builder that extends RemoteBuilder, [{$builder}] given.");
    }

    public static function asynchronous(string $model): self
    {
        return new self("Remote model [{$model}] received a promise where a response was expected.");
    }

    public static function factoryHas(string $factory): self
    {
        return new self("Remote factory [{$factory}] cannot create related records with has(). Create the related models with their own factory.");
    }

    public static function total(string $model): self
    {
        return new self("Remote model [{$model}] cannot read a total from the response. Override total() on the model, or use simplePaginate().");
    }

    public static function massWrite(string $method, string $model): self
    {
        return new self("Remote model [{$model}] does not support [{$method}] on a query. Write through a model instance instead.");
    }
}
