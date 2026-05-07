<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionStrike;
use App\Models\BrokerApi;
use App\Services\OiAnalysisServiceNew;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalculateMarketBiasCommand extends Command
{
    protected $signature = 'options:calculate-bias
                            {--from= : From date (Y-m-d)}
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}';

    protected $description = 'Calculate market bias from FUT + CE + PE daily OI';

    public function handle()
    {
        $fromDate = $this->option('from') ?: Carbon::now()->subDays(7)->format('Y-m-d');
        $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');

        $this->info("🧠 Calculating Market Bias");
        $this->info("   From: {$fromDate}");
        $this->info("   To: {$toDate}");
        $this->newLine();

        // Get unique combinations
        $query = OptionStrike::select('broker_api_id', 'underlying_symbol', 'trading_date')
            ->whereNotNull('trading_date')
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->distinct();

        if ($this->option('broker')) {
            $query->where('broker_api_id', $this->option('broker'));
        }

        if ($this->option('symbol')) {
            $query->where('underlying_symbol', strtoupper($this->option('symbol')));
        }

        $combinations = $query->get();

        if ($combinations->isEmpty()) {
            $this->warn('No data found to process');
            return 1;
        }

        $this->info("Processing " . $combinations->count() . " combinations\n");

        $processed = 0;
        $failed = 0;

        foreach ($combinations as $combo) {
            try {
                // Get FUT, CE, PE rows
                $fut = OptionStrike::where('broker_api_id', $combo->broker_api_id)
                    ->where('underlying_symbol', $combo->underlying_symbol)
                    ->where('trading_date', $combo->trading_date)
                    ->where('strike_position', 'FUT')
                    ->first();

                $ce = OptionStrike::where('broker_api_id', $combo->broker_api_id)
                    ->where('underlying_symbol', $combo->underlying_symbol)
                    ->where('trading_date', $combo->trading_date)
                    ->where('strike_position', 'CE_MERGED')
                    ->first();

                $pe = OptionStrike::where('broker_api_id', $combo->broker_api_id)
                    ->where('underlying_symbol', $combo->underlying_symbol)
                    ->where('trading_date', $combo->trading_date)
                    ->where('strike_position', 'PE_MERGED')
                    ->first();

                // Need all 3 to calculate bias
                if (!$fut || !$ce || !$pe) {
                    $this->warn("   ⚠️  {$combo->underlying_symbol} {$combo->trading_date}: Missing FUT/CE/PE");
                    $failed++;
                    continue;
                }

                // Prepare analysis arrays
                $futAnalysis = [
                    'direction' => $fut->direction,
                    'strength' => $fut->strength
                ];

                $ceAnalysis = [
                    'direction' => $ce->direction
                ];

                $peAnalysis = [
                    'direction' => $pe->direction
                ];

                // Calculate market bias
                $marketBias = OiAnalysisServiceNew::calculateMarketBias(
                    $futAnalysis,
                    $ceAnalysis,
                    $peAnalysis
                );

                // Update all 3 rows with the same market bias
                DB::table('option_strikes')
                    ->where('broker_api_id', $combo->broker_api_id)
                    ->where('underlying_symbol', $combo->underlying_symbol)
                    ->where('trading_date', $combo->trading_date)
                    ->whereIn('strike_position', ['FUT', 'CE_MERGED', 'PE_MERGED'])
                    ->update(['market_bias' => $marketBias]);

                $this->info("   ✓ {$combo->underlying_symbol} {$combo->trading_date}: {$marketBias}");
                $processed++;

            } catch (\Exception $e) {
                $this->error("   ✗ Error: {$combo->underlying_symbol} {$combo->trading_date}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("✅ Market Bias Calculation Complete");
        $this->info("   Processed: {$processed}");
        $this->info("   Failed: {$failed}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        return 0;
    }
}