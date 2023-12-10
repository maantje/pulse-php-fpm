<?php

namespace Maantje\Pulse\PhpFpm;

use Maantje\Pulse\PhpFpm\Livewire\PhpFpm;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

class PhpFpmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'fpm');

        $this->callAfterResolving('livewire', function (LivewireManager $livewire, Application $app) {
            $livewire->component('fpm', PhpFpm::class);
        });
    }
}
