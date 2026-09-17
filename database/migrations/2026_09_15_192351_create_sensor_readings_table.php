<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table): void {
            $table->id();
            $table->string('node_id');
            $table->string('sensor_id');
            $table->double('value');
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->index(['node_id', 'sensor_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
