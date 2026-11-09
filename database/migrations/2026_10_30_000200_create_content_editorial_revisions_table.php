<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_editorial_revisions', function (Blueprint $table): void {
            $table->id();
            // Deliberately retained even when the content row is deleted: this is a recovery archive.
            $table->unsignedBigInteger('content_item_id')->index();
            $table->string('slug');
            $table->string('revision');
            $table->char('before_hash', 64);
            $table->char('after_hash', 64);
            $table->char('before_state_hash', 64);
            $table->char('after_state_hash', 64)->nullable();
            $table->longText('snapshot');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_editorial_revisions');
    }
};
