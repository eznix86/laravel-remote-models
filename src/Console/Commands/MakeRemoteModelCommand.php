<?php

declare(strict_types=1);

namespace RemoteModels\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeRemoteModelCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:remote-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new remote model';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Remote model';

    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        $this->writeConnection();

        if ($this->option('factory') === true) {
            $this->writeFactory();
        }

        return null;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['factory', 'f', InputOption::VALUE_NONE, 'Create a factory for the remote model'],
            ['connection', 'c', InputOption::VALUE_REQUIRED, 'The remote connection the model reads'],
            ['methods', null, InputOption::VALUE_NONE, 'Declare the routes as methods instead of attributes'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite the model when it already exists'],
        ];
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath($this->option('methods') === true
            ? '/stubs/remote-model.methods.stub'
            : '/stubs/remote-model.stub');
    }

    protected function getDefaultNamespace(mixed $rootNamespace): string
    {
        return $rootNamespace.'\Models\Remote';
    }

    protected function buildClass(mixed $name): string
    {
        return str_replace('{{ endpoint }}', $this->endpoint($name), parent::buildClass($name));
    }

    protected function resolveStubPath(string $stub): string
    {
        $published = $this->laravel->basePath(trim($stub, '/'));

        return file_exists($published) ? $published : __DIR__.'/../../..'.$stub;
    }

    protected function endpoint(string $name): string
    {
        return '/'.Str::kebab(Str::pluralStudly(class_basename($name)));
    }

    protected function connection(): ?string
    {
        $connection = $this->option('connection');

        if (is_string($connection) && $connection !== '') {
            return $connection;
        }

        $segments = explode('/', str_replace('\\', '/', trim($this->getNameInput(), '/\\')));

        return count($segments) > 1 ? Str::kebab($segments[0]) : null;
    }

    protected function writeConnection(): void
    {
        $connection = $this->connection();

        if ($connection === null) {
            return;
        }

        $path = $this->laravel->configPath('remote.php');

        if (! $this->files->exists($path)) {
            $this->files->copy(__DIR__.'/../../../config/remote.php', $path);
        }

        $contents = $this->files->get($path);

        if (str_contains($contents, "'{$connection}' => [")) {
            return;
        }

        $prefix = Str::upper(Str::snake(Str::camel($connection)));

        $entry = "'connections' => [\n\n        '{$connection}' => [\n"
            ."            'url' => env('{$prefix}_URL'),\n"
            ."            'token' => env('{$prefix}_TOKEN'),\n"
            ."            'headers' => [],\n"
            ."        ],\n";

        $updated = Str::replaceFirst("'connections' => [\n", $entry, $contents);

        if ($updated === $contents) {
            $this->components->warn("Add the [{$connection}] connection to config/remote.php by hand.");

            return;
        }

        $this->files->put($path, $updated);

        $this->components->info("Connection [{$connection}] added to config/remote.php.");
    }

    protected function writeFactory(): void
    {
        $model = $this->qualifyClass($this->getNameInput());

        $relative = Str::after($model, $this->rootNamespace().'Models\\');

        $factory = $relative.'Factory';

        $path = $this->laravel->databasePath('factories/'.str_replace('\\', '/', $factory).'.php');

        if ($this->files->exists($path)) {
            $this->components->warn("Factory [{$factory}] already exists.");

            return;
        }

        $this->makeDirectory($path);

        $namespace = Str::of('Database\Factories\\'.$factory)->beforeLast('\\')->toString();

        $this->files->put($path, str_replace(
            ['{{ factoryNamespace }}', '{{ namespacedModel }}', '{{ factory }}', '{{ model }}'],
            [$namespace, $model, class_basename($factory), class_basename($model)],
            $this->files->get($this->resolveStubPath('/stubs/remote-factory.stub')),
        ));

        $relative = 'database/factories/'.str_replace('\\', '/', $factory).'.php';

        $this->components->info("Factory [{$relative}] created successfully.");
    }
}
