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
        Schema::create(config('visitor-tracker.tables.visitors', 'visitors'), function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('platform')->nullable();
            $table->string('platform_version')->nullable();
            $table->string('device_type')->nullable()->index();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('visitor-tracker.tables.visitors', 'visitors'));
    }
};
