<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('arena_connections', function (Blueprint $table) {
            $table->string('provider', 20)->default('bridge');
            $table->string('model', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('arena_connections', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model']);
        });
    }
};
