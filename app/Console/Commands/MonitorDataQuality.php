<?php

namespace App\Console\Commands;

use App\Models\InstrumentHistoricalData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonitorDataQuality extends Command
{
    protected $signature = 'zerodha:monitor 
                            {--days=7 : Number of days to monitor}
                            {--underlying=* : Specific underlyings to check}';
    
    protected $description = 'Monitor data quality and identify gaps/issues';

    public function handle()
    {
        $this->info("🔍 Zerodha Data Quality Monitor\n");
        $this->info("═══════════════════════════════════════\n");

        $days = (int) $this->option('days');
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days);

        // Overall quality report
        $this->displayOverallQuality($startDate, $endDate);

        // Missing dates report
        $this->displayMissingDates($startDate, $endDate);

        // Low quality data report
        $this->displayLowQualityData($startDate, $endDate);

        // Per-underlying report
        $this->displayPerUnderlyingReport($startDate, $endDate);

        // Storage statistics
        $this->displayStorageStats($startDate, $endDate);

        // Recommendations
        $this->displayRecommendations($startDate, $endDate);

        return 0;
    }

    private function displayOverallQuality($startDate, $endDate)
    {
        $this->info("📊 Overall Data Quality ({$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')})");
        $this->line("─────────────────────────────────────");

        $report = InstrumentHistoricalData::getDataQualityReport(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records', number_format($report['total_records'])],
                ['High Quality (90%+)', number_format($report['high_quality']) . ' (' . round(($report['high_quality']/$report['total_records'])*100, 1) . '%)'],
                ['Medium Quality (70-89%)', number_format($report['medium_quality']) . ' (' . round(($report['medium_quality']/$report['total_records'])*100, 1) . '%)'],
                ['Low Quality (<70%)', number_format($report['low_quality']) . ' (' . round(($report['low_quality']/$report['total_records'])*100, 1) . '%)'],
                ['Average Score', $report['average_score'] . '%']
            ]
        );

        $this->newLine();
    }

    private function displayMissingDates($startDate, $endDate)
    {
        $this->info("📅 Missing Data Dates");
        $this->line("─────────────────────────────────────");

        $underlyings = $this->option('underlying') ?: ['NIFTY', 'BANKNIFTY', 'FINNIFTY'];
        $hasMissing = false;

        foreach ($underlyings as $underlying) {
            $missing = InstrumentHistoricalData::getMissingDates(
                $underlying,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            if (!empty($missing)) {
                $hasMissing = true;
                $this->warn("  ⚠️  {$underlying}: " . count($missing) . " missing date(s)");
                $this->line("     " . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? '...' : ''));
            }
        }

        if (!$hasMissing) {
            $this->info("  ✅ No missing dates detected!");
        }

        $this->newLine();
    }

    private function displayLowQualityData($startDate, $endDate)
    {
        $this->info("⚠️  Low Quality Data (Score < 70%)");
        $this->line("─────────────────────────────────────");

        $lowQuality = InstrumentHistoricalData::where('data_quality_score', '<', 70)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('underlying', 'date', DB::raw('COUNT(*) as count'), DB::raw('AVG(data_quality_score) as avg_score'))
            ->groupBy('underlying', 'date')
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        if ($lowQuality->isEmpty()) {
            $this->info("  ✅ No low quality data detected!");
        } else {
            $this->table(
                ['Date', 'Underlying', 'Affected Records', 'Avg Score'],
                $lowQuality->map(function($row) {
                    return [
                        $row->date->format('Y-m-d'),
                        $row->underlying,
                        $row->count,
                        round($row->avg_score, 2) . '%'
                    ];
                })
            );
        }

        $this->newLine();
    }

    private function displayPerUnderlyingReport($startDate, $endDate)
    {
        $this->info("📈 Per-Underlying Quality Report");
        $this->line("─────────────────────────────────────");

        $underlyings = $this->option('underlying');
        
        $query = InstrumentHistoricalData::whereBetween('date', [$startDate, $endDate])
            ->select(
                'underlying',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('AVG(data_quality_score) as avg_score'),
                DB::raw('MIN(data_quality_score) as min_score'),
                DB::raw('MAX(data_quality_score) as max_score'),
                DB::raw('COUNT(DISTINCT date) as trading_days')
            )
            ->groupBy('underlying')
            ->orderBy('underlying');

        if (!empty($underlyings)) {
            $query->whereIn('underlying', $underlyings);
        }

        $report = $query->get();

        if ($report->isEmpty()) {
            $this->warn("  ⚠️  No data found for the specified period.");
        } else {
            $this->table(
                ['Underlying', 'Records', 'Trading Days', 'Avg Score', 'Min Score', 'Max Score'],
                $report->map(function($row) {
                    return [
                        $row->underlying,
                        number_format($row->total_records),
                        $row->trading_days,
                        round($row->avg_score, 2) . '%',
                        round($row->min_score, 2) . '%',
                        round($row->max_score, 2) . '%'
                    ];
                })
            );
        }

        $this->newLine();
    }

    private function displayStorageStats($startDate, $endDate)
    {
        $this->info("💾 Storage Statistics");
        $this->line("─────────────────────────────────────");

        $stats = InstrumentHistoricalData::whereBetween('date', [$startDate, $endDate])
            ->select(
                DB::raw('COUNT(*) as total_records'),
                DB::raw('COUNT(DISTINCT underlying) as unique_underlyings'),
                DB::raw('COUNT(DISTINCT date) as trading_days'),
                DB::raw('SUM(CASE WHEN type = "FUT" THEN 1 ELSE 0 END) as futures_count'),
                DB::raw('SUM(CASE WHEN type = "CE" THEN 1 ELSE 0 END) as ce_count'),
                DB::raw('SUM(CASE WHEN type = "PE" THEN 1 ELSE 0 END) as pe_count')
            )
            ->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records', number_format($stats->total_records)],
                ['Unique Underlyings', $stats->unique_underlyings],
                ['Trading Days', $stats->trading_days],
                ['Future Contracts', number_format($stats->futures_count)],
                ['Call Options (CE)', number_format($stats->ce_count)],
                ['Put Options (PE)', number_format($stats->pe_count)],
                ['Avg Records/Day', number_format($stats->total_records / max($stats->trading_days, 1))]
            ]
        );

        // Storage size estimation
        $tableName = (new InstrumentHistoricalData)->getTable();
        $size = DB::select("
            SELECT 
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
            AND table_name = '{$tableName}'
        ");

        if (!empty($size)) {
            $this->info("  📦 Database Size: {$size[0]->size_mb} MB");
        }

        $this->newLine();
    }

    private function displayRecommendations($startDate, $endDate)
    {
        $this->info("💡 Recommendations");
        $this->line("─────────────────────────────────────");

        $recommendations = [];

        // Check for missing data
        $underlyings = ['NIFTY', 'BANKNIFTY', 'FINNIFTY'];
        foreach ($underlyings as $underlying) {
            $missing = InstrumentHistoricalData::getMissingDates(
                $underlying,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            if (!empty($missing)) {
                $recommendations[] = "⚠️  Run backfill for {$underlying}: php artisan zerodha:store-historical --backfill --symbol={$underlying} --days=7";
            }
        }

        // Check for low quality data
        $lowQualityCount = InstrumentHistoricalData::where('data_quality_score', '<', 70)
            ->whereBetween('date', [$startDate, $endDate])
            ->count();

        if ($lowQualityCount > 0) {
            $recommendations[] = "⚠️  {$lowQualityCount} low quality records found. Consider re-fetching: php artisan zerodha:store-historical --backfill --days=7";
        }

        // Check last update
        $lastUpdate = InstrumentHistoricalData::max('updated_at');
        if ($lastUpdate) {
            $hoursSinceUpdate = Carbon::parse($lastUpdate)->diffInHours(now());
            if ($hoursSinceUpdate > 24) {
                $recommendations[] = "⚠️  Data not updated for {$hoursSinceUpdate} hours. Run: php artisan zerodha:store-historical";
            }
        }

        // Check average quality
        $avgQuality = InstrumentHistoricalData::whereBetween('date', [$startDate, $endDate])
            ->avg('data_quality_score');

        if ($avgQuality < 85) {
            $recommendations[] = "⚠️  Average quality score is low ({$avgQuality}%). Review API connection and data sources.";
        }

        if (empty($recommendations)) {
            $this->info("  ✅ Everything looks good! No action needed.");
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line("  " . $recommendation);
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════");
    }
}