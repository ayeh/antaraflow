<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Models\Admin;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Models\OrganizationAiBudget;
use App\Domain\AI\Services\OrgBudgetService;
use App\Infrastructure\AI\Exceptions\OrgBudgetExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spendForOrg(int $orgId, float $amount): void
{
    AiUsageLog::query()->create([
        'organization_id' => $orgId,
        'provider' => 'openai',
        'model' => 'gpt-5.4-mini',
        'operation' => 'chat',
        'cost' => $amount,
    ]);
}

test('org with no budget is never over limit', function () {
    $org = Organization::factory()->create();
    spendForOrg($org->id, 999.0);

    expect(app(OrgBudgetService::class)->isOverLimit($org->id))->toBeFalse();
});

test('org is over limit when daily spend meets the daily limit', function () {
    $org = Organization::factory()->create();
    OrganizationAiBudget::query()->create(['organization_id' => $org->id, 'daily_limit' => 10.0]);

    spendForOrg($org->id, 9.0);
    expect(app(OrgBudgetService::class)->isOverLimit($org->id))->toBeFalse();

    spendForOrg($org->id, 2.0); // total 11 ≥ 10
    expect(app(OrgBudgetService::class)->isOverLimit($org->id))->toBeTrue();
});

test('guard throws when the org is over its budget', function () {
    $org = Organization::factory()->create();
    OrganizationAiBudget::query()->create(['organization_id' => $org->id, 'monthly_limit' => 5.0]);
    spendForOrg($org->id, 6.0);

    expect(fn () => app(OrgBudgetService::class)->guard($org->id))
        ->toThrow(OrgBudgetExceededException::class);
});

test('guard is a no-op for a null org or an under-budget org', function () {
    $org = Organization::factory()->create();
    OrganizationAiBudget::query()->create(['organization_id' => $org->id, 'daily_limit' => 100.0]);
    spendForOrg($org->id, 1.0);

    app(OrgBudgetService::class)->guard(null);
    app(OrgBudgetService::class)->guard($org->id);

    expect(true)->toBeTrue(); // no exception thrown
});

test('utilisation reports the highest ratio across limits', function () {
    $org = Organization::factory()->create();
    OrganizationAiBudget::query()->create(['organization_id' => $org->id, 'daily_limit' => 10.0, 'monthly_limit' => 100.0]);
    spendForOrg($org->id, 8.0); // daily 80%, monthly 8%

    expect(app(OrgBudgetService::class)->utilisation($org->id))->toBe(0.8);
});

test('admin can set an org budget', function () {
    $admin = Admin::factory()->create();
    $org = Organization::factory()->create();

    $this->actingAs($admin, 'admin')
        ->put(route('admin.ai.org-budgets.update', $org), ['daily_limit' => 25, 'monthly_limit' => 400])
        ->assertRedirect(route('admin.ai.org-budgets.index'));

    $budget = OrganizationAiBudget::query()->where('organization_id', $org->id)->firstOrFail();
    expect($budget->daily_limit)->toBe(25.0);
    expect($budget->monthly_limit)->toBe(400.0);
});

test('admin can view the org budgets page', function () {
    $admin = Admin::factory()->create();
    Organization::factory()->create(['name' => 'Acme Corp']);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ai.org-budgets.index'))
        ->assertStatus(200)
        ->assertSee('Acme Corp');
});
