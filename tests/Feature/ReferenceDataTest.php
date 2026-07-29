<?php

namespace Tests\Feature;

use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    public function test_valid_reference_queries_return_stable_typed_results(): void
    {
        $this->getJson('/references/provinces')
            ->assertOk()
            ->assertJsonPath('meta.status', 'ok')
            ->assertJsonPath('meta.type', 'province')
            ->assertJsonPath('data.0.id', 'bkk')
            ->assertJsonPath('data.1.id', 'cbi');

        $this->getJson('/select/amphoes?province_id=cbi')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => 'cbi-sattahip',
                    'code' => '2009',
                    'label' => 'สัตหีบ',
                ]],
                'meta' => [
                    'status' => 'ok',
                    'parent_type' => 'province',
                    'parent_id' => 'cbi',
                ],
                'errors' => [],
            ]);

        $this->getJson('/select/tambons?amphoe_id=cbi-sattahip')
            ->assertOk()
            ->assertJsonPath('data.0.postcode', '20250')
            ->assertJsonPath('meta.status', 'ok');
    }

    public function test_missing_malformed_unknown_and_empty_reference_results_are_explicit(): void
    {
        $this->getJson('/select/amphoes')
            ->assertUnprocessable()
            ->assertJsonPath('meta.status', 'missing-parent')
            ->assertJsonPath('data', []);

        $this->getJson('/select/amphoes?province_id=%3Cscript%3E')
            ->assertUnprocessable()
            ->assertJsonPath('meta.status', 'malformed-parent')
            ->assertJsonPath('data', []);

        $this->getJson('/select/amphoes?province_id=unknown')
            ->assertOk()
            ->assertJsonPath('meta.status', 'unknown-parent')
            ->assertJsonPath('data', []);

        $this->getJson('/select/amphoes?province_id=empty')
            ->assertOk()
            ->assertJsonPath('meta.status', 'empty')
            ->assertJsonPath('data', []);
    }
}
