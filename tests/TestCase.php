<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Run before Laravel boots and before RefreshDatabase can migrate.
        TestDatabaseGuard::assertProcessEnvironmentIsSafe();

        parent::setUp();
    }

    protected function refreshApplication()
    {
        parent::refreshApplication();

        // Laravel is booted here, but database-refresh traits have not run yet.
        TestDatabaseGuard::assertSafe([
            'APP_ENV' => (string) $this->app->environment(),
            'DB_CONNECTION' => (string) $this->app['config']->get('database.default'),
            'DB_DATABASE' => (string) $this->app['config']->get('database.connections.sqlite.database'),
            'DB_URL' => $this->app['config']->get('database.connections.sqlite.url'),
        ]);
    }
}
