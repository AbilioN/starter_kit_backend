<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Run by the `scheduler` service in docker-compose.yml (`php artisan
| schedule:work`). Before it existed, nothing time-based had anywhere to run
| at all — see roadmap 5.2.
|
| Two rules for anything added here:
|
|  - **Guard every task with withoutOverlapping().** Under database-per-tenant
|    a task iterates every tenant, so its runtime grows with the customer base.
|    A task that starts taking longer than its own interval would otherwise
|    pile up copies of itself until the host gives out.
|  - **A task that touches tenant data must iterate tenants explicitly**
|    (RunForEachTenantUseCase). Laravel's own maintenance commands run against
|    the default connection, which under this architecture reaches none of the
|    data that matters — `sanctum:prune-expired` is exactly that trap, which is
|    why tenant:prune-tokens exists instead.
|
*/

// Feeds the readiness probe's `tenant_databases` check. Ten minutes against a
// 30-minute staleness threshold, so two consecutive misses are needed before
// the probe complains — one skipped run is not an incident.
Schedule::command('tenant:health-check')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Expired tokens are dead for authentication but stay in the table forever.
// GodAdmin support sessions (5.6) make this matter: they mint short-lived
// tokens continuously.
Schedule::command('tenant:prune-tokens')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Horizon's own metrics snapshot — without it the dashboard's throughput and
// wait-time graphs stay empty.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
