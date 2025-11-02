<?php

declare(strict_types=1);

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
        Schema::create('database_size_logs', function (Blueprint $table) {
            $table->id();
            $table->string('database_name');
            $table->string('driver');

            // Size information
            $table->unsignedBigInteger('total_size_bytes');
            $table->decimal('total_size_mb', 12, 2);
            $table->decimal('total_size_gb', 12, 2);

            // Max size information (nullable for databases without limits)
            $table->unsignedBigInteger('max_size_bytes')->nullable();
            $table->decimal('max_size_mb', 12, 2)->nullable();
            $table->decimal('max_size_gb', 12, 2)->nullable();
            $table->decimal('usage_percentage', 5, 2)->nullable();

            // Table count and row count
            $table->integer('table_count')->default(0);
            $table->unsignedBigInteger('total_rows')->default(0);

            // Growth metrics (calculated from previous log)
            $table->decimal('growth_bytes', 20, 2)->nullable();
            $table->decimal('growth_mb', 12, 2)->nullable();
            $table->decimal('growth_percentage', 8, 4)->nullable();

            // Prediction (nullable until we have enough data)
            $table->integer('days_until_full')->nullable();
            $table->timestamp('estimated_full_date')->nullable();

            // Additional metadata
            $table->json('largest_tables')->nullable(); // Store top 5 largest tables
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for faster querying
            $table->index('database_name');
            $table->index('created_at');
            $table->index(['database_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_size_logs');
    }
};
