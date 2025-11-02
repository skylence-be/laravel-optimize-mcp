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
        Schema::create('database_table_size_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_size_log_id')
                ->constrained('database_size_logs')
                ->onDelete('cascade');

            // Table identification
            $table->string('table_name');

            // Size information
            $table->unsignedBigInteger('size_bytes');
            $table->decimal('size_mb', 12, 2);

            // Additional size breakdown (for MySQL/PostgreSQL)
            $table->decimal('data_size_mb', 12, 2)->nullable();
            $table->decimal('index_size_mb', 12, 2)->nullable();

            // Row count
            $table->unsignedBigInteger('row_count')->default(0);

            // Growth metrics (compared to previous log for this table)
            $table->bigInteger('growth_bytes')->nullable();
            $table->decimal('growth_mb', 12, 2)->nullable();
            $table->decimal('growth_percentage', 8, 4)->nullable();
            $table->bigInteger('row_growth')->nullable();
            $table->decimal('row_growth_percentage', 8, 4)->nullable();

            $table->timestamps();

            // Indexes for faster querying
            $table->index('table_name');
            $table->index(['database_size_log_id', 'table_name']);
            $table->index(['table_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_table_size_logs');
    }
};
