<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private bool $madeLock = false;

    private bool $madeEnv = false;

    protected function setUp(): void
    {
        // the app only treats itself as installed when these two files exist
        if (! is_file(base_path('.env'))) { file_put_contents(base_path('.env'), "APP_KEY=\n"); $this->madeEnv = true; }
        if (! is_file(storage_path('app/installed.lock'))) { @mkdir(storage_path('app'), 0775, true); file_put_contents(storage_path('app/installed.lock'), '1'); $this->madeLock = true; }
        parent::setUp();
        \App\Support\Settings::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->madeLock) @unlink(storage_path('app/installed.lock'));
        if ($this->madeEnv) @unlink(base_path('.env'));
    }
}
