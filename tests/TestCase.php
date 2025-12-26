<?php

namespace Climactic\Workspaces\Tests;

use Climactic\Workspaces\WorkspacesServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Climactic\\Workspaces\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            WorkspacesServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        // Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Run the package migration manually since it's a .stub file
        $migrationFile = __DIR__.'/../database/migrations/create_workspaces_tables.php.stub';
        $migration = include $migrationFile;
        $migration->up();
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure the package to use test User model
        config()->set('workspaces.user_model', \Climactic\Workspaces\Tests\Fixtures\User::class);
    }
}
