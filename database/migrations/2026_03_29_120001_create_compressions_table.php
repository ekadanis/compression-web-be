<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();

            $table->string('format');
            $table->string('codec')->nullable();

            // Video fields
            $table->integer('bitrate')->nullable();
            $table->string('resolution')->nullable();
            $table->integer('fps')->nullable();

            // Audio fields
            $table->integer('audio_bitrate')->nullable();
            $table->integer('sample_rate')->nullable();
            $table->string('channel')->nullable(); // mono | stereo

            $table->bigInteger('size')->nullable();
            $table->string('path')->nullable();

            $table->boolean('is_recommended')->default(false);

            $table->string('status')->default('processing'); // processing | done | failed
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compressions');
    }
};
