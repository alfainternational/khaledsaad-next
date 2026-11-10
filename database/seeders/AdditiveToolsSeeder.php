<?php

namespace Database\Seeders;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\LabCatalog;
use Illuminate\Database\Seeder;

/**
 * أدوات جديدة تُضاف بعد المرحلة 2 (plan 2026-11-09 البندان 3 و4) — وليست إعادة تشغيل لـ LabSeeder.
 *
 * Production carries `.seeded-stage2`, so the big lab seeder never runs there again, and must not: the tool studio
 * has owned the templates since. This one adds the catalog tools that do not exist yet, by slug, and **never
 * touches a row that does** — so a template an editor tuned in the studio survives every later deploy.
 */
class AdditiveToolsSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Tool::query()->pluck('slug')->all();

        foreach (LabCatalog::tools() as $definition) {
            if (in_array($definition['slug'], $existing, true)) {
                continue;
            }
            LabSeeder::ensureVersion(Tool::query()->create($definition));
        }
    }
}
