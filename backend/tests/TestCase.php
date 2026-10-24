<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase hace migrate:fresh: jamás debe ejecutarse contra la base de desarrollo.
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Los tests deben usar una base terminada en _test (actual: {$database}).");
        }

        // Ninguna llamada HTTP real (frontend, R2…): toda petición sin Http::fake hace fallar el test.
        Http::preventStrayRequests();
    }
}
