<?php

declare(strict_types=1);

namespace Skylence\OptimizeMcp\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Skylence\OptimizeMcp\Models\DatabaseSizeLog;

class DatabaseSizeWarning extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DatabaseSizeLog $log,
        public string $level = 'warning'
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isCritical = $this->level === 'critical';
        $threshold = $isCritical
            ? config('optimize-mcp.database_monitoring.critical_threshold', 90)
            : config('optimize-mcp.database_monitoring.warning_threshold', 80);

        $subject = $isCritical
            ? "🚨 CRITICAL: Database {$this->log->database_name} at {$this->log->usage_percentage}%"
            : "⚠️ WARNING: Database {$this->log->database_name} at {$this->log->usage_percentage}%";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($isCritical ? 'Critical Alert!' : 'Warning Alert')
            ->line($this->getIntroLine($threshold))
            ->line('')
            ->line('**Database Information:**')
            ->line("- Database: {$this->log->database_name}")
            ->line("- Driver: {$this->log->driver}")
            ->line("- Current Size: {$this->log->total_size_gb} GB ({$this->log->total_size_mb} MB)")
            ->line("- Usage: {$this->log->usage_percentage}% of available disk space")
            ->line("- Table Count: {$this->log->table_count}")
            ->line("- Total Rows: " . number_format($this->log->total_rows));

        // Add growth information if available
        if ($this->log->growth_mb !== null) {
            $growthSign = $this->log->growth_mb >= 0 ? '+' : '';
            $message->line('')
                ->line('**Growth Information:**')
                ->line("- Size Change: {$growthSign}{$this->log->growth_mb} MB ({$growthSign}{$this->log->growth_percentage}%)")
                ->line("- Since: " . $this->log->getPreviousLog()?->created_at?->diffForHumans() ?? 'N/A');
        }

        // Add prediction if available
        if ($this->log->days_until_full !== null) {
            $urgency = $this->log->days_until_full <= 7 ? '🚨' : ($this->log->days_until_full <= 30 ? '⚠️' : 'ℹ️');
            $message->line('')
                ->line('**Prediction:**')
                ->line("{$urgency} Database may be full in **{$this->log->days_until_full} days**")
                ->line("Estimated: {$this->log->estimated_full_date?->format('Y-m-d H:i:s')}");
        }

        // Add largest tables
        if (!empty($this->log->largest_tables)) {
            $message->line('')
                ->line('**Largest Tables:**');
            foreach ($this->log->largest_tables as $table) {
                $size = $table['size_mb'] ?? 'N/A';
                $rows = isset($table['rows']) ? number_format($table['rows']) : 'N/A';
                $message->line("- {$table['name']}: {$size} MB ({$rows} rows)");
            }
        }

        // Add recommended actions
        $message->line('')
            ->line('**Recommended Actions:**')
            ->action('View Database Dashboard', url('/'))
            ->line($this->getRecommendations());

        return $message;
    }

    /**
     * Get the intro line based on threshold.
     */
    protected function getIntroLine(float $threshold): string
    {
        if ($this->level === 'critical') {
            return "Your database **{$this->log->database_name}** has reached **{$this->log->usage_percentage}%** of available disk space, exceeding the critical threshold of {$threshold}%. Immediate action is required to prevent database failure.";
        }

        return "Your database **{$this->log->database_name}** has reached **{$this->log->usage_percentage}%** of available disk space, exceeding the warning threshold of {$threshold}%. Please review and take action soon.";
    }

    /**
     * Get recommended actions based on the situation.
     */
    protected function getRecommendations(): string
    {
        $recommendations = [];

        if ($this->level === 'critical') {
            $recommendations[] = '1. **URGENT:** Review and delete unnecessary data immediately';
            $recommendations[] = '2. Consider archiving old records to external storage';
            $recommendations[] = '3. Increase disk space allocation';
            $recommendations[] = '4. Check for table bloat and run OPTIMIZE TABLE';
        } else {
            $recommendations[] = '1. Review largest tables and consider data retention policies';
            $recommendations[] = '2. Prune old logs and unnecessary data (e.g., `php artisan telescope:prune`)';
            $recommendations[] = '3. Archive historical records if applicable';
            $recommendations[] = '4. Monitor growth trend and plan for capacity increase';
        }

        if (!empty($this->log->largest_tables)) {
            $largestTable = $this->log->largest_tables[0] ?? null;
            if ($largestTable) {
                $recommendations[] = "5. Focus on **{$largestTable['name']}** - it's your largest table";
            }
        }

        return implode("\n", $recommendations);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'database' => $this->log->database_name,
            'usage_percentage' => $this->log->usage_percentage,
            'level' => $this->level,
            'log_id' => $this->log->id,
        ];
    }
}
