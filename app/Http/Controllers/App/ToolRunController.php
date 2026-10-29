<?php

namespace App\Http\Controllers\App;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Lab\Services\ExperimentPicker;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * «حسّن بالذكاء الاصطناعي» (blueprint §4.7): a member run that generates the base asset with the lab engine, improves it through
 * AiGateway when a key is configured (charging credit_cost once, reserve → capture, released on failure) and saves the result as a
 * new version in the project. Without a key the base output is saved with a notice and nothing is charged.
 */
class ToolRunController extends Controller
{
    public function store(Request $request, Tool $tool, GeneratorEngine $engine, AiGateway $ai, CreditLedger $ledger, AssetVault $vault, ExperimentPicker $experiments, EventRecorder $events): RedirectResponse
    {
        abort_unless($tool->is_published && $tool->kind === 'generator', 404);
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);
        $version = $experiments->versionFor($tool, $request);
        $generated = $engine->generate($tool, $request->all(), $version);
        if ($generated['missing'] !== []) {
            return back()->withErrors(['tool' => 'أكمل الحقول: '.implode('، ', $generated['missing'])])->withInput();
        }
        $aiEnabled = filled(config('services.openai.key'));
        $cost = $aiEnabled ? (int) $tool->credit_cost : 0;

        $publicId = (string) Str::uuid();
        try {
            $runId = DB::transaction(function () use ($workspace, $project, $tool, $version, $request, $generated, $publicId, $ledger, $cost): int {
                if ($cost > 0) {
                    $ledger->reserve($workspace, "run:{$publicId}:reserve", $cost, 'حجز تحسين '.$tool->name);
                }

                return DB::table('tool_runs')->insertGetId([
                    'workspace_id' => $workspace->id, 'project_id' => $project->id, 'tool_id' => $tool->id, 'tool_version_id' => $version?->id,
                    'user_id' => $request->user()->id, 'public_id' => $publicId, 'status' => 'running',
                    'input_payload' => json_encode(['input' => $generated['input'], 'enhance' => true], JSON_UNESCAPED_UNICODE),
                    'output_payload' => null, 'reserved_credits' => $cost, 'used_credits' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }, 3);
        } catch (DomainException $exception) {
            return back()->withErrors(['tool' => $exception->getMessage()])->withInput();
        }

        $markdown = $generated['markdown'];
        $enhanced = false;
        $notice = null;
        try {
            if ($aiEnabled) {
                $answer = $ai->generate("الأصل القاعدي:\n\n".$generated['markdown']."\n\nحسّنه مع إبقاء البنية والجداول والعناوين.", $version?->ai_instructions ?: 'أنت مساعد تسويق عربي دقيق. حسّن صياغة الأصل التالي دون اختراع أرقام أو مصادر، وأبقِ جمهوراً واحداً وقناة واحدة.');
                $markdown = trim($answer['text']) !== '' ? trim($answer['text']) : $markdown;
                $enhanced = true;
                if ($cost > 0) {
                    $ledger->record($workspace, "run:{$publicId}:release", 'release', $cost, 'تحويل الحجز إلى استخدام');
                    $ledger->record($workspace, "run:{$publicId}:capture", 'capture', -$cost, 'تحسين '.$tool->name, ['run_id' => $runId]);
                }
            } else {
                $notice = 'الذكاء الاصطناعي غير مفعّل على المنصة الآن؛ عُرض المخرج القاعدي وحُفظ بلا خصم رصيد.';
            }
            DB::table('tool_runs')->where('id', $runId)->update(['status' => 'completed', 'output_payload' => json_encode(['text' => $markdown, 'enhanced' => $enhanced], JSON_UNESCAPED_UNICODE), 'reserved_credits' => 0, 'used_credits' => $enhanced ? $cost : 0, 'updated_at' => now()]);
            $quality = $enhanced ? app(\App\Domain\Lab\Services\QualityChecker::class)->check($markdown, $generated['values'], $tool->quality_rules ?? [], $tool->fields()) : $generated['quality'];
            $output = $vault->save($request->user(), $project, $tool, $generated['title'], (string) $tool->output_type, $markdown, $generated['input'], $quality, null, $version, ['source' => 'tool', 'enhanced' => $enhanced, 'run_id' => $runId]);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($cost, $ledger, $workspace, $publicId, $runId): void {
                if ($cost > 0) {
                    $ledger->record($workspace, "run:{$publicId}:release", 'release', $cost, 'فك حجز تحسين فاشل');
                }
                DB::table('tool_runs')->where('id', $runId)->update(['status' => 'failed', 'reserved_credits' => 0, 'error_code' => 'provider_unavailable', 'updated_at' => now()]);
            });
            report($exception);
            $message = $exception instanceof DomainException ? $exception->getMessage() : 'تعذر إكمال التحسين. تم استرجاع الرصيد المحجوز ويمكن إعادة المحاولة؛ المخرج القاعدي ما زال متاحاً.';

            return back()->withErrors(['tool' => $message])->withInput();
        }

        session(['lab.project_id' => $project->id, 'lab.result.'.$tool->slug => ['markdown' => $markdown, 'title' => $generated['title'], 'input' => $generated['input'], 'quality' => $quality, 'version_id' => $version?->id, 'enhanced' => $enhanced, 'notice' => $notice, 'at' => now()->toDateTimeString()]]);
        $assets = (array) session('lab.assets', []);
        $assets[$tool->output_type] = ['input' => $generated['input'], 'title' => $generated['title'], 'slug' => $tool->slug, 'markdown' => $markdown, 'at' => now()->toDateTimeString()];
        session(['lab.assets' => $assets]);
        $experiments->convert($tool, $request);
        $events->record($request, $enhanced ? 'tool.enhanced' : 'tool.generated', ['tool_id' => $tool->id, 'meta' => ['version_id' => $version?->id, 'credits' => $enhanced ? $cost : 0]]);
        $events->record($request, 'asset.saved', ['tool_id' => $tool->id, 'meta' => ['version' => $output->version, 'enhanced' => $enhanced]]);

        return redirect()->to(route('tools.show', [$tool, 'project' => $project->id]).'#asset')
            ->with('status', $enhanced ? 'حُسّن الأصل بالذكاء الاصطناعي وحُفظ كإصدار v'.$output->version.' ('.$cost.' رصيد).' : 'حُفظ المخرج القاعدي كإصدار v'.$output->version.'.')
            ->with($notice ? 'info' : 'none', $notice);
    }
}
