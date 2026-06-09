<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compressions', function (Blueprint $table) {
            $table->unsignedInteger('estimated_seconds_remaining')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table('compressions', function (Blueprint $table) {
            $table->dropColumn('estimated_seconds_remaining');
        });
    }
};
