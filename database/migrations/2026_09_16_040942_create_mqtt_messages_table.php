<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mqtt_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('node_id', 32);
            $table->uuid('message_id');
            $table->timestamp('received_at');
            $table->unique(['node_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mqtt_messages');
    }
};
