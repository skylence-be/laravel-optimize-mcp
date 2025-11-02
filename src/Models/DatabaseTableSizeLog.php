<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseTableSizeLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'database_table_size_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'database_size_log_id',
        'table_name',
        'size_bytes',
        'size_mb',
        'data_size_mb',
        'index_size_mb',
        'row_count',
        'growth_bytes',
        'growth_mb',
        'growth_percentage',
        'row_growth',
        'row_growth_percentage',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'database_size_log_id' => 'integer',
        'size_bytes' => 'integer',
        'size_mb' => 'decimal:2',
        'data_size_mb' => 'decimal:2',
        'index_size_mb' => 'decimal:2',
        'row_count' => 'integer',
        'growth_bytes' => 'integer',
        'growth_mb' => 'decimal:2',
        'growth_percentage' => 'decimal:4',
        'row_growth' => 'integer',
        'row_growth_percentage' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the database size log that owns this table log.
     */
    public function databaseSizeLog(): BelongsTo
    {
        return $this->belongsTo(DatabaseSizeLog::class);
    }

    /**
     * Get the previous log entry for the same table.
     */
    public function getPreviousLog(): ?self
    {
        // Get the previous database size log
        $previousDbLog = $this->databaseSizeLog->getPreviousLog();

        if (!$previousDbLog) {
            return null;
        }

        // Find this table's entry in the previous log
        return static::where('database_size_log_id', $previousDbLog->id)
            ->where('table_name', $this->table_name)
            ->first();
    }

    /**
     * Calculate growth metrics based on the previous log.
     */
    public function calculateGrowth(): void
    {
        $previous = $this->getPreviousLog();

        if (!$previous) {
            return;
        }

        // Size growth
        $this->growth_bytes = $this->size_bytes - $previous->size_bytes;
        $this->growth_mb = $this->size_mb - $previous->size_mb;

        if ($previous->size_bytes > 0) {
            $this->growth_percentage = (($this->size_bytes - $previous->size_bytes) / $previous->size_bytes) * 100;
        }

        // Row count growth
        $this->row_growth = $this->row_count - $previous->row_count;

        if ($previous->row_count > 0) {
            $this->row_growth_percentage = (($this->row_count - $previous->row_count) / $previous->row_count) * 100;
        }
    }

    /**
     * Scope to filter by table name.
     */
    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    /**
     * Scope to get logs within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get fastest growing tables.
     */
    public function scopeFastestGrowing($query, int $limit = 10)
    {
        return $query->whereNotNull('growth_percentage')
            ->orderBy('growth_percentage', 'desc')
            ->limit($limit);
    }

    /**
     * Scope to get largest tables.
     */
    public function scopeLargest($query, int $limit = 10)
    {
        return $query->orderBy('size_bytes', 'desc')
            ->limit($limit);
    }
}
