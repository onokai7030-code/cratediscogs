<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_explorations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discogs_label_id');
            $table->string('label_name');
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message')->nullable();
            $table->json('results')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_explorations');
    }
};
