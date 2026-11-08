<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_checks', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 30);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_name', 160);
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->string('target', 500)->nullable();
            $table->string('message', 500)->nullable();
            $table->string('source', 20)->default('probe');
            $table->timestamp('checked_at');
            $table->index(['resource_type', 'resource_id', 'checked_at'], 'resource_checks_resource_idx');
            $table->index('checked_at', 'resource_checks_checked_idx');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 30);
            $table->unsignedBigInteger('resource_id');
            $table->string('resource_name', 160);
            $table->string('title', 200);
            $table->string('severity', 20)->default('major');
            $table->string('status', 20)->default('open');
            $table->string('trigger_status', 20);
            $table->string('detail', 500)->nullable();
            $table->unsignedInteger('check_count')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 1000)->nullable();
            $table->string('source', 20)->default('probe');
            $table->timestamps();
            $table->index(['status', 'started_at'], 'incidents_status_idx');
            $table->index(['resource_type', 'resource_id'], 'incidents_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('resource_checks');
    }
};
