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
            $table->integer('total_ads')->nullable();
            $table->integer('total_links')->nullable();
            $table->text('html_cache')->nullable();
            $table->json('organic_results')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            // Add indexes for common queries
            $table->index('status');
            $table->index('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_results');
    }
};
