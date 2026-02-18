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
        Schema::create('batch_operations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status', 50)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_operations');
    }
};
