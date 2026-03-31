<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type');       // audio | video
            $table->string('mime_type');

            $table->string('original_path');
            $table->bigInteger('size');
            $table->integer('duration')->nullable();

            $table->string('status')->default('uploaded'); // uploaded | processing | done | failed

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
