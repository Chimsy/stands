<?php

use App\Enums\HealthStatus;
use App\Http\Middleware\EnsureHealthInspector;
use App\Models\Account;
use App\Models\Branch;
use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * A check that always reports the status it was configured with, so the
 * endpoints can be exercised against a broken dependency without breaking one.
 */
class StubbedHealthCheck implements HealthCheck
{
    public static HealthStatus $status = HealthStatus::Failed;

    public static bool $critical = true;

    public function name(): string
    {
        return 'stubbed';
    }

    public function isCritical(): bool
    {
        return self::$critical;
    }

    public function run(): CheckResult
    {
        return match (self::$status) {
            HealthStatus::Ok => CheckResult::ok($this->name(), 'Fine.', ['secret' => 'internal']),
            HealthStatus::Degraded => CheckResult::degraded($this->name(), 'Slow.', ['secret' => 'internal']),
            HealthStatus::Failed => CheckResult::failed($this->name(), 'Down.', ['secret' => 'internal']),
        };
    }
}

/** A check that throws rather than returning a result, which must not cost the report. */
class ExplodingHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'exploding';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function run(): CheckResult
    {
        throw new RuntimeException('The connection went away.');
    }
}

beforeEach(function () {
    StubbedHealthCheck::$status = HealthStatus::Failed;
    StubbedHealthCheck::$critical = true;
});

it('answers liveness without a token', function () {
    $this->getJson(route('api.v1.health.live'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['status', 'app', 'environment', 'version', 'time']);
});

it('answers liveness without touching a single dependency', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->getJson(route('api.v1.health.live'))->assertOk();

    /** The point of a liveness probe: a database outage must not restart a healthy container. */
    expect($queries)->toBe(0);
});

it('never lets a health response be cached', function () {
    $response = $this->getJson(route('api.v1.health.live'))->assertOk();

    /** Symfony reorders the directives, so assert the one that matters is in there. */
    expect($response->headers->get('cache-control'))->toContain('no-store');
});

it('answers readiness with the critical checks and no others', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $response = $this->getJson(route('api.v1.health.ready'))
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect(array_keys($response->json('checks')))
        ->toBe(['database', 'migrations', 'cache', 'storage', 'chart-of-accounts'])
        ->and($response->json('checks.database.status'))->toBe('ok');
});

it('keeps the deployment detail out of the readiness response', function () {
    $this->seed(ChartOfAccountsSeeder::class);
    config(['health.checks' => [StubbedHealthCheck::class]]);

    $response = $this->getJson(route('api.v1.health.ready'));

    expect($response->json('checks.stubbed'))->toHaveKeys(['status', 'message', 'durationMs'])
        ->and($response->json('checks.stubbed'))->not->toHaveKey('detail');
});

it('answers 503 when a critical check fails', function () {
    config(['health.checks' => [StubbedHealthCheck::class]]);

    $this->getJson(route('api.v1.health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('checks.stubbed.message', 'Down.');
});

it('stays in rotation when a check is only degraded', function () {
    StubbedHealthCheck::$status = HealthStatus::Degraded;
    config(['health.checks' => [StubbedHealthCheck::class]]);

    $this->getJson(route('api.v1.health.ready'))
        ->assertOk()
        ->assertJsonPath('status', 'degraded');
});

it('reports a failure rather than throwing when a check itself breaks', function () {
    config(['health.checks' => [ExplodingHealthCheck::class]]);

    $this->getJson(route('api.v1.health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.exploding.status', 'failed');
});

it('fails readiness when the chart of accounts is missing', function () {
    $this->getJson(route('api.v1.health.ready'))
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.chart-of-accounts.status', 'failed');
});

it('refuses diagnostics without a token', function () {
    $this->getJson(route('api.v1.health.diagnostics'))->assertUnauthorized();
});

it('refuses diagnostics to a sales agent', function () {
    $branch = Branch::factory()->create();

    $this->actingAs(agentAt($branch), 'sanctum')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertForbidden();
});

it('reports diagnostics to an administrator', function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $branch = Branch::factory()->create();

    $response = $this->actingAs(adminAt($branch), 'sanctum')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure([
            'status', 'app', 'environment', 'version', 'time', 'durationMs',
            'checks' => ['database' => ['status', 'message', 'durationMs', 'detail']],
            'runtime' => ['php', 'laravel', 'debug', 'database', 'cacheStore', 'queueConnection'],
            'footprint' => ['branches', 'users', 'stands', 'sales', 'payments', 'journalEntries'],
        ]);

    /** The expensive checks run here and nowhere else. */
    expect(array_keys($response->json('checks')))->toContain('queue', 'ledger')
        ->and($response->json('footprint.branches'))->toBe(1);
});

it('reports diagnostics to a monitor presenting the configured secret', function () {
    $this->seed(ChartOfAccountsSeeder::class);
    config(['health.token' => 'a-monitoring-secret']);

    $this->withHeader(EnsureHealthInspector::HEADER, 'a-monitoring-secret')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertOk()
        ->assertJsonPath('checks.ledger.status', 'ok');
});

it('refuses a monitoring secret that does not match', function () {
    config(['health.token' => 'a-monitoring-secret']);

    $this->withHeader(EnsureHealthInspector::HEADER, 'not-it')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertUnauthorized();
});

it('treats an unconfigured secret as no way in rather than as any way in', function () {
    config(['health.token' => null]);

    $this->withHeader(EnsureHealthInspector::HEADER, '')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertUnauthorized();
});

it('reports the ledger as failed when journal lines do not balance', function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $branch = Branch::factory()->create();

    /** Written behind the posting action's back, which is the only way this can happen. */
    $entryId = DB::table('journal_entries')->insertGetId([
        'branch_id' => $branch->id,
        'reference' => 'JE-TEST-000001',
        'entry_date' => now()->toDateString(),
        'description' => 'Hand written',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('journal_lines')->insert([
        'journal_entry_id' => $entryId,
        'account_id' => Account::query()->where('code', Account::BANK)->value('id'),
        'debit_cents' => 1_000_00,
        'credit_cents' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(adminAt($branch), 'sanctum')
        ->getJson(route('api.v1.health.diagnostics'))
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('checks.ledger.status', 'failed')
        ->assertJsonPath('checks.ledger.detail.differenceCents', 100000);
});
