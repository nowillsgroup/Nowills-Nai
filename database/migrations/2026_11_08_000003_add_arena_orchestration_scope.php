<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('subject_type', 30)->nullable()->after('title');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('playbook', 40)->nullable()->after('subject_id');
            $table->index(['subject_type', 'subject_id'], 'conversations_subject_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('source_message_id')->nullable()->after('due_date')->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_message_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_subject_idx');
            $table->dropColumn(['subject_type', 'subject_id', 'playbook']);
        });
    }
};
