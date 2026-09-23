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
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discogs_id')->unique();
            $table->string('artist');
            $table->string('title');
            $table->string('catalog_number')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->json('formats');
            $table->json('genres');
            $table->unsignedInteger('have')->default(0);
            $table->unsignedInteger('want')->default(0);
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
