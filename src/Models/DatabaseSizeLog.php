<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class DatabaseSizeLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'database_size_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'database_name',
        'driver',
        'total_size_bytes',
        'total_size_mb',
        'total_size_gb',
        'max_size_bytes',
        'max_size_mb',
        'max_size_gb',
        'usage_percentage',
        'table_count',
        'total_rows',
        'growth_bytes',
        'growth_mb',
        'growth_percentage',
        'days_until_full',
        'estimated_full_date',
        'largest_tables',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_size_bytes' => 'integer',
        'total_size_mb' => 'decimal:2',
        'total_size_gb' => 'decimal:2',
        'max_size_bytes' => 'integer',
        'max_size_mb' => 'decimal:2',
        'max_size_gb' => 'decimal:2',
        'usage_percentage' => 'decimal:2',
        'table_count' => 'integer',
        'total_rows' => 'integer',
        'growth_bytes' => 'decimal:2',
        'growth_mb' => 'decimal:2',
        'growth_percentage' => 'decimal:4',
        'days_until_full' => 'integer',
        'estimated_full_date' => 'datetime',
        'largest_tables' => AsArrayObject::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the previous log entry for the same database.
     */
    public function getPreviousLog(): ?self
    {
        return static::where('database_name', $this->database_name)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
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

        $this->growth_bytes = $this->total_size_bytes - $previous->total_size_bytes;
        $this->growth_mb = $this->total_size_mb - $previous->total_size_mb;

        if ($previous->total_size_bytes > 0) {
            $this->growth_percentage = (($this->total_size_bytes - $previous->total_size_bytes) / $previous->total_size_bytes) * 100;
        }
    }

    /**
     * Calculate prediction for when database will be full.
     */
    public function calculatePrediction(): void
    {
        // Need at least 2 data points for prediction
        $recentLogs = static::where('database_name', $this->database_name)
            ->orderBy('created_at', 'desc')
            ->limit(30) // Use last 30 days for better accuracy
            ->get();

        if ($recentLogs->count() < 2 || !$this->max_size_bytes) {
            return;
        }

        // Calculate average daily growth
        $oldest = $recentLogs->last();
        $daysDiff = $this->created_at->diffInDays($oldest->created_at);

        if ($daysDiff == 0) {
            return;
        }

        $totalGrowth = $this->total_size_bytes - $oldest->total_size_bytes;
        $avgDailyGrowth = $totalGrowth / $daysDiff;

        // If growth is negative or zero, we can't predict
        if ($avgDailyGrowth <= 0) {
            return;
        }

        // Calculate remaining space
        $remainingBytes = $this->max_size_bytes - $this->total_size_bytes;

        if ($remainingBytes <= 0) {
            $this->days_until_full = 0;
            $this->estimated_full_date = now();
            return;
        }

        // Calculate days until full
        $this->days_until_full = (int) ceil($remainingBytes / $avgDailyGrowth);
        $this->estimated_full_date = now()->addDays($this->days_until_full);
    }

    /**
     * Check if database size is approaching warning threshold.
     */
    public function isApproachingWarningThreshold(): bool
    {
        $threshold = config('optimize-mcp.database_monitoring.warning_threshold', 80);
        return $this->usage_percentage !== null && $this->usage_percentage >= $threshold;
    }

    /**
     * Check if database size is approaching critical threshold.
     */
    public function isApproachingCriticalThreshold(): bool
    {
        $threshold = config('optimize-mcp.database_monitoring.critical_threshold', 90);
        return $this->usage_percentage !== null && $this->usage_percentage >= $threshold;
    }

    /**
     * Scope to filter by database name.
     */
    public function scopeForDatabase($query, string $database)
    {
        return $query->where('database_name', $database);
    }

    /**
     * Scope to get logs within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get logs older than a given date.
     */
    public function scopeOlderThan($query, $date)
    {
        return $query->where('created_at', '<', $date);
    }
}
