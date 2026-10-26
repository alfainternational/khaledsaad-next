<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Workspace\Models\Workspace;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreditLedger
{
    public function record(Workspace $workspace, string $operationKey, string $type, int $amount, string $description, array $meta = []): void
    {
        DB::table('credit_entries')->insertOrIgnore([
            'workspace_id' => $workspace->id,
            'operation_key' => $operationKey,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function balance(Workspace $workspace): int
    {
        return (int) DB::table('credit_entries')->where('workspace_id', $workspace->id)->sum('amount');
    }

    public function reserve(Workspace $workspace, string $operationKey, int $amount, string $description, array $meta = []): void
    {
        DB::transaction(function () use ($workspace, $operationKey, $amount, $description, $meta): void {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            if (DB::table('credit_entries')->where('operation_key', $operationKey)->exists()) {
                return;
            }

            if ($amount < 1 || $this->balance($workspace) < $amount) {
                throw new DomainException('الرصيد غير كافٍ لتشغيل هذه الأداة.');
            }

            $this->record($workspace, $operationKey, 'reserve', -$amount, $description, $meta);
        }, 3);
    }
}
