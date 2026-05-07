<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionStrike;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class RecalculatePECERatioCommand extends Command
{
    protected $signature = 'options:recalculate-pece-ratio 
                            {--from= : From date (Y-m-d)} 
                            {--to= : To date (Y-m-d)}
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--dry-run : Show what would be updated without actually updating}';

    protected $description = 'Recalculate PE/CE Ratio to CE/PE Ratio for historical data and refresh all dependent fields';

    public function handle()
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now()->subDays(30)->format('Y-m-d');
            $toDate = $this->option('to') ?: Carbon::now()->format('Y-m-d');
            $isDryRun = $this->option('dry-run');

            $this->info("🔄 Starting PE/CE Ratio Recalculation (Inverted to CE/PE)");
            $this->info("   From: {$fromDate}");
            $this->info("   To: {$toDate}");
            if ($isDryRun) {
                $this->warn("   Mode: DRY RUN (No changes will be made)");
            }
            $this->newLine();

            // Build query for FUT records with PE/CE data
            $query = OptionStrike::where('strike_position', 'FUT')
                ->whereBetween('trading_date', [$fromDate, $toDate])
                ->whereNotNull('pe_ce_ratio');

            if ($this->option('broker')) {
                $query->where('broker_api_id', $this->option('broker'));
            }

            if ($this->option('symbol')) {
                $query->where('underlying_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
            }

            $futRecords = $query->orderBy('trading_date', 'ASC')->get();

            if ($futRecords->isEmpty()) {
                $this->warn('⚠️  No records found with PE/CE ratio data in the specified date range.');
                return 0;
            }

            $this->info("📊 Found {$futRecords->count()} FUT records to process");
            $this->newLine();

            $totalUpdated = 0;
            $totalFailed = 0;

            $this->output->progressStart($futRecords->count());

            foreach ($futRecords as $futRecord) {
                try {
                    $broker = BrokerApi::find($futRecord->broker_api_id);
                    
                    if (!$broker) {
                        $totalFailed++;
                        $this->output->progressAdvance();
                        continue;
                    }

                    // Fetch CE and PE records for this date and symbol
                    $ceRecord = OptionStrike::where('broker_api_id', $futRecord->broker_api_id)
                        ->where('underlying_symbol', $futRecord->underlying_symbol)
                        ->where('strike_position', 'CE_MERGED')
                        ->where('trading_date', $futRecord->trading_date)
                        ->first();

                    $peRecord = OptionStrike::where('broker_api_id', $futRecord->broker_api_id)
                        ->where('underlying_symbol', $futRecord->underlying_symbol)
                        ->where('strike_position', 'PE_MERGED')
                        ->where('trading_date', $futRecord->trading_date)
                        ->first();

                    if (!$ceRecord || !$peRecord) {
                        $totalFailed++;
                        $this->output->progressAdvance();
                        continue;
                    }

                    $ceOI = $ceRecord->daily_oi ?? 0;
                    $peOI = $peRecord->daily_oi ?? 0;
                    $futOIChangePct = $futRecord->daily_oi_change_pct ?? 0;

                    // ✅ NEW INVERTED FORMULA: CE/PE instead of PE/CE
                    $newPeCeRatio = $peOI > 0 ? round($ceOI / $peOI, 2) : 0;

                    // ✅ RECALCULATE ALL DEPENDENT FIELDS WITH CORRECTED LOGIC
                    if ($newPeCeRatio > 1.2) {
                        // More CE OI than PE OI = More Call Buying
                        $oiInterpretation = 'Call Writing';
                        $optionsSentiment = 'Bearish';
                        $baseAction = 'BUY PE';
                        
                    } elseif ($newPeCeRatio < 0.8) {
                        // More PE OI than CE OI = More Put Buying
                        $oiInterpretation = 'Put Writing';
                        $optionsSentiment = 'Bullish';
                        $baseAction = 'BUY CE';
                        
                    } else {
                        // Balanced
                        $oiInterpretation = 'Balanced OI';
                        $optionsSentiment = 'Neutral';
                        $baseAction = 'BOTH CE AND PE';
                    }

                    // Determine Futures OI View
                    if ($futOIChangePct > 5) {
                        $futuresOIView = 'Strong Build-up';
                    } elseif ($futOIChangePct < -5) {
                        $futuresOIView = 'Position Unwinding';
                    } else {
                        $futuresOIView = 'Normal';
                    }

                    // ✅ Determine Final Sentiment
                    $finalSentiment = $optionsSentiment;

                    if ($optionsSentiment == 'Bullish' && $futuresOIView == 'Strong Build-up') {
                        $finalSentiment = 'Strong Bullish';
                    } elseif ($optionsSentiment == 'Bearish' && $futuresOIView == 'Strong Build-up') {
                        $finalSentiment = 'Mixed Signals';
                    } elseif ($optionsSentiment == 'Bearish' && $futuresOIView == 'Position Unwinding') {
                        $finalSentiment = 'Strong Bearish';
                    } elseif ($optionsSentiment == 'Bullish' && $futuresOIView == 'Position Unwinding') {
                        $finalSentiment = 'Mixed Signals';
                    }

                    $tradeAction = $baseAction;

                    // Show what would be updated in dry-run mode
                    if ($isDryRun) {
                        if ($this->option('verbose')) {
                            $this->info("\n  📝 {$futRecord->underlying_symbol} on {$futRecord->trading_date}:");
                            $this->line("     Old PE/CE Ratio: {$futRecord->pe_ce_ratio}");
                            $this->line("     New CE/PE Ratio: {$newPeCeRatio}");
                            $this->line("     New Sentiment: {$finalSentiment}");
                            $this->line("     New Action: {$tradeAction}");
                        }
                    } else {
                        // ✅ UPDATE THE RECORD
                        $futRecord->update([
                            'pe_ce_ratio' => $newPeCeRatio,
                            'oi_interpretation' => $oiInterpretation,
                            'options_sentiment' => $optionsSentiment,
                            'futures_oi_view' => $futuresOIView,
                            'final_sentiment' => $finalSentiment,
                            'trade_action' => $tradeAction
                        ]);
                    }

                    $totalUpdated++;

                } catch (Exception $e) {
                    Log::error("Failed to recalculate PE/CE for record ID {$futRecord->id}: " . $e->getMessage());
                    $totalFailed++;
                }

                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
            $this->newLine();

            // Summary
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            if ($isDryRun) {
                $this->info("✅ DRY RUN Completed!");
                $this->info("   Would Update: {$totalUpdated} records");
                $this->info("   Would Skip: {$totalFailed} records");
            } else {
                $this->info("✅ PE/CE Ratio Recalculation Completed!");
                $this->info("   Total Updated: {$totalUpdated} records");
                $this->info("   Total Failed: {$totalFailed} records");
            }
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();

            return 0;

        } catch (Exception $e) {
            $this->error("Critical Error: " . $e->getMessage());
            Log::error('PE/CE Ratio Recalculation Error: ' . $e->getMessage());
            return 1;
        }
    }
}