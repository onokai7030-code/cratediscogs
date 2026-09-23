<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('normalized_name')->unique();
            $table->timestamps();
        });

        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->timestamps();

            $table->unique(['genre_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('styles');
        Schema::dropIfExists('genres');
    }
};
