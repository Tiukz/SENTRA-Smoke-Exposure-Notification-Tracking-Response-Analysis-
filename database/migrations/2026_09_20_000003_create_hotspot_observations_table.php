<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_observations', function (Blueprint $table): void {
            $table->id();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 10, 6);
            $table->unsignedTinyInteger('confidence');
            $table->decimal('frp', 10, 2)->nullable();
            $table->string('satellite', 40)->nullable();
            $table->string('instrument', 40)->nullable();
            $table->timestamp('acquired_at');
            $table->string('source', 80);
            $table->timestamps();

            $table->unique(['latitude', 'longitude', 'acquired_at', 'source'], 'hotspot_observation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_observations');
    }
};
