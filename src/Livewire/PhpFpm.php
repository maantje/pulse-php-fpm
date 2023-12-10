<?php

namespace Maantje\Pulse\PhpFpm\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Livewire\Concerns\HasPeriod;
use Laravel\Pulse\Livewire\Concerns\RemembersQueries;
use Livewire\Attributes\Lazy;
use Maantje\Pulse\PhpFpm\Recorders\PhpFpmRecorder;

class PhpFpm extends Card
{
    use HasPeriod;
    use RemembersQueries;

    private Collection $datasets;

    public function __construct()
    {
        $defaultDatasets = [
            'active processes' => '#9333ea',
            'total processes' => 'rgba(147,51,234,0.5)',
            'idle processes' => '#eab308',
            'listen queue' => '#e11d48',
        ];

        $this->datasets = collect(config('pulse.recorders.'.PhpFpmRecorder::class.'.datasets', $defaultDatasets))->filter();
    }

    #[Lazy]
    public function render()
    {
        [$servers, $time, $runAt] = $this->remember(function () {
            $graphs = Pulse::graph($this->datasets->keys()->toArray(), 'avg', $this->periodAsInterval());

            return Pulse::values('php_fpm')
                ->map(function ($fpm, $slug) use ($graphs) {
                    $values = json_decode($fpm->value, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        ...((array) $values),
                        'datasets' => $this->datasets->map(function ($color, $set) use ($slug, $graphs) {
                            return $graphs->get($slug)?->get($set) ?? collect();
                        }),
                        'updated_at' => $updatedAt = CarbonImmutable::createFromTimestamp($fpm->timestamp),
                        'recently_reported' => $updatedAt->isAfter(now()->subSeconds(30)),
                    ];
                });
        });

        if (Request::hasHeader('X-Livewire')) {
            $this->dispatch('fpm-chart-update', servers: $servers);
        }

        return View::make('fpm::livewire.fpm-card', [
            'servers' => $servers,
            'datasets' => $this->datasets,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
