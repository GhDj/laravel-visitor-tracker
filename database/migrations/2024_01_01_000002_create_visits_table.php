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
        Schema::create(config('visitor-tracker.tables.visits', 'visits'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')
                ->constrained(config('visitor-tracker.tables.visitors', 'visitors'))
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->text('url');
            $table->string('path')->index();
            $table->string('method', 10);
            $table->text('referrer')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('visitor-tracker.tables.visits', 'visits'));
    }
};
