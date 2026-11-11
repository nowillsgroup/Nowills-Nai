<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workforce_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->timestamps();
        });
        Schema::create('workforce_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('workforce_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // manager, operator, viewer
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });
        Schema::create('workforce_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('workforce_companies')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->string('agent', 50)->nullable();
            $table->longText('instruction'); // encrypted by model cast
            $table->longText('proposal')->nullable();
            $table->longText('result')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });
        Schema::create('workforce_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('workforce_companies')->cascadeOnDelete();
            $table->foreignId('assignment_id')->unique()->constrained('workforce_assignments')->restrictOnDelete();
            $table->longText('title');
            $table->longText('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });
        Schema::create('workforce_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('workforce_companies')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('workforce_assignments')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_events');
        Schema::dropIfExists('workforce_work_items');
        Schema::dropIfExists('workforce_assignments');
        Schema::dropIfExists('workforce_memberships');
        Schema::dropIfExists('workforce_companies');
    }
};
