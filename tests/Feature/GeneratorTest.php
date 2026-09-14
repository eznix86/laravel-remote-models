<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/remote-models-'.bin2hex(random_bytes(6));

    File::makeDirectory($this->root.'/config', 0755, true);
    File::makeDirectory($this->root.'/database', 0755, true);

    app()->useConfigPath($this->root.'/config');
    app()->useDatabasePath($this->root.'/database');
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
    File::deleteDirectory(app_path('Models/Remote'));
});

it('writes the model under the connection namespace', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo'])->assertSuccessful();

    $path = app_path('Models/Remote/Github/Repo.php');

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('namespace App\Models\Remote\Github;')
        ->and(File::get($path))->toContain('class Repo extends RemoteModel');
});

it('writes the factory where eloquent looks for it', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo', '--factory' => true])->assertSuccessful();

    $expected = Factory::resolveFactoryName('App\Models\Remote\Github\Repo');

    $path = $this->root.'/database/factories/'.str_replace(['Database\\Factories\\', '\\'], ['', '/'], $expected).'.php';

    expect($expected)->toBe('Database\Factories\Remote\Github\RepoFactory')
        ->and(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('namespace Database\Factories\Remote\Github;')
        ->and(File::get($path))->toContain('class RepoFactory extends RemoteFactory')
        ->and(File::get($path))->toContain('protected $model = Repo::class;');
});

it('adds the connection to the config file', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo'])->assertSuccessful();

    expect(File::get($this->root.'/config/remote.php'))
        ->toContain("'github' => [")
        ->toContain("env('GITHUB_URL')");
});

it('adds the connection once across runs', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo'])->assertSuccessful();
    $this->artisan('make:remote-model', ['name' => 'Github/Issue'])->assertSuccessful();

    expect(substr_count(File::get($this->root.'/config/remote.php'), "'github' => ["))->toBe(1);
});

it('declares the model with attributes by default', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo'])->assertSuccessful();

    expect(File::get(app_path('Models/Remote/Github/Repo.php')))
        ->toContain("#[Endpoint('/repos')]");
});

it('declares the model with route methods when asked', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Github/Repo', '--methods' => true])->assertSuccessful();

    $contents = File::get(app_path('Models/Remote/Github/Repo.php'));

    expect($contents)->toContain('protected function index(PendingRequest $http, array $query): Response')
        ->and($contents)->toContain("return \$http->get('/repos', \$query);")
        ->and($contents)->not->toContain('#[Endpoint');
});

it('names the connection from the option when the model sits at the root', function (): void {
    $this->artisan('make:remote-model', ['name' => 'Repo', '--connection' => 'gitlab'])->assertSuccessful();

    expect(File::get($this->root.'/config/remote.php'))->toContain("'gitlab' => [");
});
