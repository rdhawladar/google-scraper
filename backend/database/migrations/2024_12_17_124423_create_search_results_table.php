<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('url')->nullable();
            $table->text('snippet')->nullable();
            $table->integer('position');
            $table->string('type')->default('organic');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['keyword_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_results');
    }
};
