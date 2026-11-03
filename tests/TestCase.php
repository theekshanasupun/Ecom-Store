<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private bool $madeLock = false;

    private bool $madeEnv = false;

    protected function setUp(): void
    {
        // the app only treats itself as installed when these two files exist
        if (! is_file(dirname(__DIR__).'/.env')) { file_put_contents(dirname(__DIR__).'/.env', "APP_KEY=\n"); $this->madeEnv = true; }
        if (! is_file(dirname(__DIR__).'/storage/app/installed.lock')) { @mkdir(dirname(__DIR__).'/storage/app', 0775, true); file_put_contents(dirname(__DIR__).'/storage/app/installed.lock', '1'); $this->madeLock = true; }
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]); // fresh in-memory SQLite for every test
        \App\Support\Settings::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->madeLock) @unlink(dirname(__DIR__).'/storage/app/installed.lock');
        if ($this->madeEnv) @unlink(dirname(__DIR__).'/.env');
    }
}
