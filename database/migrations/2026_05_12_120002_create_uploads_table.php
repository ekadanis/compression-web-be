<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('uploadable');
            $table->string('platform')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('category_id')->nullable();
            $table->string('visibility')->default('private');
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('external_id')->nullable()->index();
            $table->string('url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
