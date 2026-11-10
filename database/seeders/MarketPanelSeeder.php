<?php

namespace Database\Seeders;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\LabCatalog;
use Illuminate\Database\Seeder;

/**
 * لوحة السوق المحاكى (plan 2026-11-09 البند 3) — a one-shot additive seeder, not a rerun of LabSeeder.
 *
 * Production already carries `.seeded-stage2`, so LabSeeder will never run there again — and must not: the tool studio
 * owns every template change since. This seeder adds the one new tool and nothing else, and it refuses to touch a
 * `market-panel` row that already exists, so a template an editor tuned in the studio survives every later deploy.
 */
class MarketPanelSeeder extends Seeder
{
    public function run(): void
    {
        $definition = collect(LabCatalog::tools())->firstWhere('slug', 'market-panel');
        if (! is_array($definition)) {
            return;
        }

        $tool = Tool::query()->where('slug', 'market-panel')->first();
        if (! $tool) {
            $tool = Tool::query()->create($definition);
        }

        LabSeeder::ensureVersion($tool);
    }
}
