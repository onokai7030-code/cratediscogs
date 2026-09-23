<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('release_search', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->primary(['release_id', 'search_id']);
            $table->unique(['search_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('release_search');
    }
};
