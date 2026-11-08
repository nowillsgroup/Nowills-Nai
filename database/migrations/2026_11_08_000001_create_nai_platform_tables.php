<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('arena_connections', function (Blueprint $table) {
            $table->id();
            $table->string('base_url', 500)->nullable();
            $table->text('api_key')->nullable();
            $table->string('workspace_ref', 160)->nullable();
            $table->string('chat_path', 200)->default('/v1/chat');
            $table->string('health_path', 200)->default('/v1/health');
            $table->string('status', 20)->default('disconnected');
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('environment', 20)->default('production');
            $table->string('status', 20)->default('unknown');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tech_stack')->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'environment']);
        });

        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('url', 500);
            $table->string('provider', 30)->default('github');
            $table->string('default_branch', 120)->default('main');
            $table->string('language', 80)->nullable();
            $table->string('visibility', 20)->default('private');
            $table->string('status', 20)->default('active');
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['provider', 'status']);
        });

        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('engine', 30)->default('mysql');
            $table->string('version', 40)->nullable();
            $table->string('host', 255);
            $table->unsignedInteger('port')->nullable();
            $table->string('environment', 20)->default('production');
            $table->string('status', 20)->default('online');
            $table->decimal('size_mb', 12, 2)->nullable();
            $table->string('backup_status', 30)->default('not_configured');
            $table->timestamp('last_backup_at')->nullable();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['engine', 'status']);
        });

        Schema::create('infrastructures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('type', 30)->default('server');
            $table->string('provider', 120);
            $table->string('region', 120)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('environment', 20)->default('production');
            $table->string('status', 20)->default('online');
            $table->string('specs', 500)->nullable();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->date('ssl_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('key', 20)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planning');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('backlog');
            $table->string('priority', 20)->default('medium');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180)->default('New conversation');
            $table->string('arena_session_id', 180)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->string('status', 20)->default('complete');
            $table->string('arena_request_id', 180)->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'role']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('description', 500);
            $table->string('subject_type', 180)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['activities', 'messages', 'conversations', 'tasks', 'projects', 'infrastructures', 'database_connections', 'repositories', 'applications', 'arena_connections', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
