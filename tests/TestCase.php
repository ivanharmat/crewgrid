<?php

namespace CrewGrid\Tests;

use CrewGrid\CrewGridServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Self-contained harness: an in-memory sqlite orders table drives every test,
 * so the suite runs identically on each Laravel/Livewire pairing in the CI
 * matrix with nothing external to set up.
 */
abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('customer');
            $table->integer('total');
            $table->string('status')->default('open');
            $table->timestamp('placed_at')->nullable();
        });

        Fixtures\Order::insert([
            ['reference' => 'ORD-001', 'customer' => 'Acme', 'total' => 100, 'status' => 'open', 'placed_at' => '2026-01-05 10:00:00'],
            ['reference' => 'ORD-002', 'customer' => 'Bravo', 'total' => 250, 'status' => 'paid', 'placed_at' => '2026-02-10 10:00:00'],
            ['reference' => 'ORD-003', 'customer' => 'Acme', 'total' => 75, 'status' => 'paid', 'placed_at' => '2026-03-15 10:00:00'],
            ['reference' => 'ORD-004', 'customer' => 'Cedar & Sons', 'total' => 500, 'status' => 'open', 'placed_at' => '2026-04-20 10:00:00'],
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            CrewGridServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
