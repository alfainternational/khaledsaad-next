<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Workspace\Models\Workspace;
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
}
