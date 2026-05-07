<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\IndexOptionStrike;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Services\OiAnalysisServiceNew;
use App\Services\IVAnalysisServiceNew;
use App\Helpers\IVCalculator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

/**
 * FetchIndexOICommand
 *
 * Fetches EOD OI + IV + Close for index symbols defined in $symbols array.
 * Saves into dedicated `index_option_strikes` table via IndexOptionStrike model.
 *
 * To add more symbols: just add to the $symbols array below.
 *
 * Usage:
 *   php artisan index:fetch-oi
 *   php artisan index:fetch-oi --symbol=BANKNIFTY
 *   php artisan index:fetch-oi --from=2026-02-01 --to=2026-02-10
 *   php artisan index:fetch-oi --broker=2 --debug
 */
class FetchIndexOICommand extends Command
{
    protected $signature = 'index:fetch-oi
                            {--from=    : From date (Y-m-d). Defaults to today.}
                            {--to=      : To date (Y-m-d). Defaults to today.}
                            {--broker=  : Specific broker ID}
                            {--symbol=  : Filter to a specific symbol (e.g. BANKNIFTY)}
                            {--force    : Force fetch even on holidays}
                            {--debug    : Show detailed debug information}';

    protected $description = 'Fetch EOD OI + IV + Close for index symbols — saves to index_option_strikes table';

    // =========================================================================
    // ✅ ADD MORE SYMBOLS HERE — no other code changes needed
    // 'SYMBOL' => strike_interval
    // =========================================================================
    private array $symbols = [
        'BANKNIFTY'  => 100,
        // 'NIFTY'      => 50,
        // 'FINNIFTY'   => 50,
        // 'MIDCPNIFTY' => 25,
    ];

    private float $riskFreeRate = 0.06;
    private int   $apiCallDelay = 350000;
    private int   $maxRetries   = 3;

    // =========================================================================
    public function handle(): int
    {
        try {
            $fromDate = $this->option('from') ?: Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $toDate   = $this->option('to')   ?: Carbon::now('Asia/Kolkata')->format('Y-m-d');

            $symbols = $this->symbols;
            if ($this->option('symbol')) {
                $filter  = strtoupper($this->option('symbol'));
                $symbols = array_filter($symbols, fn($k) => $k === $filter, ARRAY_FILTER_USE_KEY);
                if (empty($symbols)) {
                    $this->error("❌ '{$filter}' not in symbols array. Available: " . implode(', ', array_keys($this->symbols)));
                    return 1;
                }
            }

            $this->info('');
            $this->info('🚀  Index OI / IV / Close — Daily Fetch');
            $this->info("    Table   : index_option_strikes");
            $this->info("    From    : {$fromDate}");
            $this->info("    To      : {$toDate}");
            $this->info("    Symbols : " . implode(', ', array_keys($symbols)));
            $this->newLine();

            $brokerQuery = BrokerApi::zerodha()->validToken();
            if ($this->option('broker')) {
                $brokerQuery->where('id', $this->option('broker'));
            }
            $brokers = $brokerQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌  No active Zerodha brokers with valid tokens found!');
                return 1;
            }

            $this->info('📋  ' . $brokers->count() . ' broker(s)');
            $this->newLine();

            $totalSuccess = $totalFailed = 0;

            foreach ($brokers as $broker) {
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info("🔑  Broker: {$broker->client_name} (ID: {$broker->id})");

                $zerodhaHelper = new BrokerZerodhaHelper($broker);
                $tradingDays   = $this->getTradingDays($fromDate, $toDate);

                $this->info('    📅  ' . count($tradingDays) . ' trading day(s)');
                $this->newLine();

                foreach ($tradingDays as $date) {
                    $this->info("    ╔═══ Date: {$date} ═══");
                    try {
                        $result        = $this->processDay($broker, $zerodhaHelper, $date, $symbols);
                        $totalSuccess += $result['success'];
                        $totalFailed  += $result['failed'];
                        $this->info("    ║  Summary: ✓ {$result['success']} | ✗ {$result['failed']}");
                    } catch (Exception $e) {
                        $this->error("    ║  ✗ " . $e->getMessage());
                        $totalFailed++;
                    }
                    $this->info("    ╚═══════════════════════════════");
                    $this->newLine();
                }
            }

            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("✅  Done!  Processed: {$totalSuccess}   Failed: {$totalFailed}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            return 0;

        } catch (Exception $e) {
            $this->error('Critical: ' . $e->getMessage());
            Log::error('[FetchIndexOICommand] ' . $e->getMessage());
            return 1;
        }
    }

    // =========================================================================
    private function processDay(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date, array $symbols): array
    {
        $success = $failed = 0;

        foreach ($symbols as $baseSymbol => $strikeInterval) {
            try {
                // 1. Nearest FUT instrument
                $futInstrument = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', 'FUT')
                    ->whereDate('expiry', '>=', $date)
                    ->orderBy('expiry', 'ASC')
                    ->first();

                if (!$futInstrument) {
                    $this->debugWarn("{$baseSymbol}: FUT not found for {$date}");
                    $failed++; continue;
                }

                // 2. Spot price
                $spotPrice = $this->getSpotPrice($zerodhaHelper, $futInstrument->instrument_token, $date);
                if (!$spotPrice) {
                    $this->debugWarn("{$baseSymbol}: No spot price for {$date}");
                    $failed++; continue;
                }

                // 3. ATM strike
                $atmStrike = (int) (round($spotPrice / $strikeInterval) * $strikeInterval);

                // 4. Fetch FUT / CE / PE
                $futAnalysis = $this->fetchFutureOIAndIV($broker, $futInstrument, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceAnalysis  = $this->fetchAndMergeCEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peAnalysis  = $this->fetchAndMergePEOIAndIV($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                // 5. Signals
                if ($futAnalysis && $ceAnalysis && $peAnalysis) {
                    $this->calculateAndStoreBTSTSignal($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    $this->calculateAndStoreCEPEOIAnalysis($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    $this->info("    ║    ✓ {$baseSymbol}: stored in index_option_strikes");
                    $success++;
                } else {
                    $this->debugWarn("{$baseSymbol}: FUT=" . ($futAnalysis ? 'ok' : 'null') . " CE=" . ($ceAnalysis ? 'ok' : 'null') . " PE=" . ($peAnalysis ? 'ok' : 'null'));
                    $failed++;
                }

            } catch (Exception $e) {
                Log::error("[FetchIndexOICommand] {$baseSymbol}/{$date}: " . $e->getMessage());
                $this->error("    ║    ✗ {$baseSymbol}: " . $e->getMessage());
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // =========================================================================
    // OI SIGNAL
    // =========================================================================
    private function getOISignal(float $ceChangePct, float $peChangePct): array
    {
        $ceUp = $ceChangePct > 0; $ceDown = $ceChangePct < 0;
        $peUp = $peChangePct > 0; $peDown = $peChangePct < 0;

        if ($ceUp && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',  'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp) return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',  'condition' => 'CE ↓ + PE ↑'];

        if ($ceUp && $peUp) {
            return $ceChangePct > $peChangePct
                ? ['signal' => 'BEARISH', 'reason' => "Both buildup but CE stronger (CE: +{$ceChangePct}% > PE: +{$peChangePct}%)", 'condition' => 'Both ↑ (CE > PE)']
                : ['signal' => 'BULLISH', 'reason' => "Both buildup but PE stronger (PE: +{$peChangePct}% > CE: +{$ceChangePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        }

        if ($ceDown && $peDown) {
            return $ceChangePct < $peChangePct
                ? ['signal' => 'BULLISH', 'reason' => "Both unwinding but CE stronger (CE: {$ceChangePct}% < PE: {$peChangePct}%)", 'condition' => 'Both ↓ (CE < PE)']
                : ['signal' => 'BEARISH', 'reason' => "Both unwinding but PE stronger (PE: {$peChangePct}% < CE: {$ceChangePct}%)", 'condition' => 'Both ↓ (PE < CE)'];
        }

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================================
    // CE/PE OI ANALYSIS → updates FUT row in index_option_strikes
    // =========================================================================
    private function calculateAndStoreCEPEOIAnalysis(BrokerApi $broker, string $baseSymbol, string $date, array $futAnalysis, array $ceAnalysis, array $peAnalysis): void
    {
        $ceChangePct = $ceAnalysis['oi']['daily_oi_change_pct'] ?? 0;
        $peChangePct = $peAnalysis['oi']['daily_oi_change_pct'] ?? 0;
        $oiSignal    = $this->getOISignal($ceChangePct, $peChangePct);

        $optionsSentiment = $oiSignal['signal'];
        $tradeAction      = match ($optionsSentiment) { 'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT' };

        $ceOI      = $ceAnalysis['oi']['daily_oi'] ?? 0;
        $peOI      = $peAnalysis['oi']['daily_oi'] ?? 0;
        $peCeRatio = $ceOI > 0 ? round($peOI / $ceOI, 2) : 0;

        IndexOptionStrike::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update([
                'oi_interpretation' => $oiSignal['reason'],
                'oi_condition'      => $oiSignal['condition'],
                'ce_oi_change_pct'  => $ceChangePct,
                'pe_oi_change_pct'  => $peChangePct,
                'options_sentiment' => $optionsSentiment,
                'final_sentiment'   => $optionsSentiment,
                'trade_action'      => $tradeAction,
                'futures_oi_view'   => $futAnalysis['oi']['market_bias'] ?? 'Normal',
                'pe_ce_ratio'       => $peCeRatio,
            ]);

        if ($this->option('debug')) {
            $this->info("      📊 CE OI Δ: " . number_format($ceChangePct, 2) . "% | PE OI Δ: " . number_format($peChangePct, 2) . "%");
            $this->info("      📊 {$oiSignal['condition']} → {$optionsSentiment} → {$tradeAction}");
        }
    }

    // =========================================================================
    // BTST → updates FUT row in index_option_strikes
    // =========================================================================
    private function calculateAndStoreBTSTSignal(BrokerApi $broker, string $baseSymbol, string $date, array $futAnalysis, array $ceAnalysis, array $peAnalysis): void
    {
        $btst = IVAnalysisServiceNew::getBTSTSignal(
            $futAnalysis['oi'], $ceAnalysis['oi'], $ceAnalysis['iv'], $peAnalysis['oi'], $peAnalysis['iv']
        );

        IndexOptionStrike::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update([
                'btst_signal'     => $btst['btst_signal'],
                'btst_confidence' => $btst['confidence'],
                'btst_reason'     => $btst['reason'],
            ]);

        if ($this->option('debug')) {
            $this->info("      🎯 BTST: {$btst['btst_signal']} ({$btst['confidence']}%) — {$btst['reason']}");
        }
    }

    // =========================================================================
    // FUT OI + CLOSE → index_option_strikes
    // =========================================================================
    private function fetchFutureOIAndIV(BrokerApi $broker, ZerodhaInstrument $futInstrument, BrokerZerodhaHelper $zerodhaHelper, string $date, string $baseSymbol, float $spotPrice, int $atmStrike): ?array
    {
        try {
            $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $futInstrument->instrument_token, $date);
            if (!$eodData || !isset($eodData['oi'], $eodData['close'])) return null;

            $prevDayOI    = $this->getPreviousDayOI($broker, $baseSymbol, 'FUT', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'FUT', $date);

            $closeChange    = $prevDayClose > 0 ? $eodData['close'] - $prevDayClose : null;
            $closeChangePct = ($prevDayClose > 0 && $closeChange !== null) ? ($closeChange / $prevDayClose) * 100 : null;

            $oiAnalysis        = OiAnalysisServiceNew::analyzeFuturesOI($eodData['oi'], $prevDayOI, $baseSymbol);
            $reversedDirection = $this->reverseFutSignal($oiAnalysis['direction']);
            $expiryCode        = strtoupper(Carbon::parse($futInstrument->expiry)->format('yM'));

            IndexOptionStrike::updateOrCreate(
                [
                    'broker_api_id'    => $broker->id,
                    'underlying_symbol'=> $baseSymbol,
                    'trading_symbol'   => $futInstrument->trading_symbol,
                    'strike_position'  => 'FUT',
                    'trading_date'     => $date,
                ],
                [
                    'option_type'            => 'FUT',
                    'strike_price'           => $spotPrice,
                    'expiry'                 => $expiryCode,
                    'expiry_date'            => $futInstrument->expiry,
                    'instrument_token'       => $futInstrument->instrument_token,
                    'exchange'               => 'NFO',
                    'lot_size'               => $futInstrument->lot_size ?? 1,
                    'is_active'              => true,
                    'daily_oi'               => $oiAnalysis['daily_oi'],
                    'daily_oi_prev'          => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change'        => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct'    => $oiAnalysis['daily_oi_change_pct'],
                    'direction'              => $reversedDirection,
                    'strength'               => $oiAnalysis['strength'],
                    'market_bias'            => $oiAnalysis['market_bias'] ?? null,
                    'daily_close'            => $eodData['close'],
                    'daily_close_prev'       => $prevDayClose,
                    'daily_close_change'     => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price'             => $spotPrice,
                    'last_synced_at'         => now(),
                ]
            );

            return ['oi' => $oiAnalysis];

        } catch (Exception $e) {
            Log::error("[FetchIndexOICommand] FUT {$baseSymbol}/{$date}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // CE MERGED → index_option_strikes
    // =========================================================================
    private function fetchAndMergeCEOIAndIV(BrokerApi $broker, string $baseSymbol, BrokerZerodhaHelper $zerodhaHelper, string $date, int $atmStrike, int $strikeInterval, float $spotPrice): ?array
    {
        try {
            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) return null;

            $expiryCode   = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = max(1, Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date)));
            $strikes = [
                $atmStrike - ($strikeInterval * 2),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + ($strikeInterval * 2),
            ];

            $totalOI = $totalIV = $totalClose = $ivCount = $closeCount = 0;
            $instruments = [];

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int) $strike) . 'CE';
                $instrument    = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->where('exchange', 'NFO')->first();
                if (!$instrument) continue;

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                if (!$eodData || ($eodData['close'] ?? 0) <= 0) continue;

                $totalOI += $eodData['oi']; $totalClose += $eodData['close']; $closeCount++;
                $instruments[] = $tradingSymbol;

                $iv = IVCalculator::calculate($eodData['close'], $spotPrice, $strike, $daysToExpiry, 'CE', $this->riskFreeRate);
                if ($iv !== null) { $totalIV += $iv; $ivCount++; }
            }

            if ($totalOI == 0) return null;

            $avgIV    = $ivCount    > 0 ? $totalIV    / $ivCount    : null;
            $avgClose = $closeCount > 0 ? $totalClose / $closeCount : null;

            $prevDayOI    = $this->getPreviousDayOI($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayIV    = $this->getPreviousDayIV($broker, $baseSymbol, 'CE_MERGED', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'CE_MERGED', $date);

            $oiAnalysis = OiAnalysisServiceNew::analyzeCallOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $ivAnalysis = IVAnalysisServiceNew::analyzeCallOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            $ivChange       = ($avgIV !== null && $prevDayIV > 0)          ? $avgIV    - $prevDayIV    : null;
            $ivChangePct    = ($ivChange !== null && $prevDayIV > 0)       ? ($ivChange    / $prevDayIV)    * 100 : null;
            $closeChange    = ($avgClose !== null && $prevDayClose > 0)    ? $avgClose - $prevDayClose : null;
            $closeChangePct = ($closeChange !== null && $prevDayClose > 0) ? ($closeChange / $prevDayClose) * 100 : null;

            IndexOptionStrike::updateOrCreate(
                [
                    'broker_api_id'    => $broker->id,
                    'underlying_symbol'=> $baseSymbol,
                    'strike_position'  => 'CE_MERGED',
                    'trading_date'     => $date,
                ],
                [
                    'trading_symbol'         => implode(',', $instruments),
                    'option_type'            => 'CE',
                    'strike_price'           => $atmStrike,
                    'expiry'                 => $expiryCode,
                    'expiry_date'            => $expiryDate,
                    'instrument_token'       => null,
                    'exchange'               => 'NFO',
                    'lot_size'               => 1,
                    'is_active'              => true,
                    'daily_oi'               => $oiAnalysis['daily_oi'],
                    'daily_oi_prev'          => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change'        => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct'    => $oiAnalysis['daily_oi_change_pct'],
                    'direction'              => $this->reverseCESignal($oiAnalysis['direction']),
                    'strength'               => $oiAnalysis['strength'],
                    'daily_iv'               => $avgIV,
                    'daily_iv_prev'          => $prevDayIV,
                    'daily_iv_change'        => $ivChange,
                    'daily_iv_change_pct'    => $ivChangePct,
                    'iv_direction'           => $ivAnalysis['iv_direction'],
                    'iv_strength'            => $ivAnalysis['iv_strength'],
                    'daily_close'            => $avgClose,
                    'daily_close_prev'       => $prevDayClose,
                    'daily_close_change'     => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price'             => $spotPrice,
                    'last_synced_at'         => now(),
                ]
            );

            return ['oi' => $oiAnalysis, 'iv' => $ivAnalysis];

        } catch (Exception $e) {
            Log::error("[FetchIndexOICommand] CE_MERGED {$baseSymbol}/{$date}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // PE MERGED → index_option_strikes
    // =========================================================================
    private function fetchAndMergePEOIAndIV(BrokerApi $broker, string $baseSymbol, BrokerZerodhaHelper $zerodhaHelper, string $date, int $atmStrike, int $strikeInterval, float $spotPrice): ?array
    {
        try {
            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) return null;

            $expiryCode   = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = max(1, Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date)));
            $strikes = [
                $atmStrike - ($strikeInterval * 2),
                $atmStrike - $strikeInterval,
                $atmStrike,
                $atmStrike + $strikeInterval,
                $atmStrike + ($strikeInterval * 2),
            ];

            $totalOI = $totalIV = $totalClose = $ivCount = $closeCount = 0;
            $instruments = [];

            foreach ($strikes as $strike) {
                $tradingSymbol = $baseSymbol . $expiryCode . ((int) $strike) . 'PE';
                $instrument    = ZerodhaInstrument::where('trading_symbol', $tradingSymbol)->where('exchange', 'NFO')->first();
                if (!$instrument) continue;

                $eodData = $this->fetchEODDataWithRetry($zerodhaHelper, $instrument->instrument_token, $date);
                if (!$eodData || ($eodData['close'] ?? 0) <= 0) continue;

                $totalOI += $eodData['oi']; $totalClose += $eodData['close']; $closeCount++;
                $instruments[] = $tradingSymbol;

                $iv = IVCalculator::calculate($eodData['close'], $spotPrice, $strike, $daysToExpiry, 'PE', $this->riskFreeRate);
                if ($iv !== null) { $totalIV += $iv; $ivCount++; }
            }

            if ($totalOI == 0) return null;

            $avgIV    = $ivCount    > 0 ? $totalIV    / $ivCount    : null;
            $avgClose = $closeCount > 0 ? $totalClose / $closeCount : null;

            $prevDayOI    = $this->getPreviousDayOI($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayIV    = $this->getPreviousDayIV($broker, $baseSymbol, 'PE_MERGED', $date);
            $prevDayClose = $this->getPreviousDayClose($broker, $baseSymbol, 'PE_MERGED', $date);

            $oiAnalysis = OiAnalysisServiceNew::analyzePutOptionsOI($totalOI, $prevDayOI, $baseSymbol);
            $ivAnalysis = IVAnalysisServiceNew::analyzePutOptionsIV($avgIV, $prevDayIV, $baseSymbol);

            $ivChange       = ($avgIV !== null && $prevDayIV > 0)          ? $avgIV    - $prevDayIV    : null;
            $ivChangePct    = ($ivChange !== null && $prevDayIV > 0)       ? ($ivChange    / $prevDayIV)    * 100 : null;
            $closeChange    = ($avgClose !== null && $prevDayClose > 0)    ? $avgClose - $prevDayClose : null;
            $closeChangePct = ($closeChange !== null && $prevDayClose > 0) ? ($closeChange / $prevDayClose) * 100 : null;

            IndexOptionStrike::updateOrCreate(
                [
                    'broker_api_id'    => $broker->id,
                    'underlying_symbol'=> $baseSymbol,
                    'strike_position'  => 'PE_MERGED',
                    'trading_date'     => $date,
                ],
                [
                    'trading_symbol'         => implode(',', $instruments),
                    'option_type'            => 'PE',
                    'strike_price'           => $atmStrike,
                    'expiry'                 => $expiryCode,
                    'expiry_date'            => $expiryDate,
                    'instrument_token'       => null,
                    'exchange'               => 'NFO',
                    'lot_size'               => 1,
                    'is_active'              => true,
                    'daily_oi'               => $oiAnalysis['daily_oi'],
                    'daily_oi_prev'          => $oiAnalysis['daily_oi_prev'],
                    'daily_oi_change'        => $oiAnalysis['daily_oi_change'],
                    'daily_oi_change_pct'    => $oiAnalysis['daily_oi_change_pct'],
                    'direction'              => $this->reversePESignal($oiAnalysis['direction']),
                    'strength'               => $oiAnalysis['strength'],
                    'daily_iv'               => $avgIV,
                    'daily_iv_prev'          => $prevDayIV,
                    'daily_iv_change'        => $ivChange,
                    'daily_iv_change_pct'    => $ivChangePct,
                    'iv_direction'           => $ivAnalysis['iv_direction'],
                    'iv_strength'            => $ivAnalysis['iv_strength'],
                    'daily_close'            => $avgClose,
                    'daily_close_prev'       => $prevDayClose,
                    'daily_close_change'     => $closeChange,
                    'daily_close_change_pct' => $closeChangePct,
                    'spot_price'             => $spotPrice,
                    'last_synced_at'         => now(),
                ]
            );

            return ['oi' => $oiAnalysis, 'iv' => $ivAnalysis];

        } catch (Exception $e) {
            Log::error("[FetchIndexOICommand] PE_MERGED {$baseSymbol}/{$date}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // EOD DATA
    // =========================================================================
    private function fetchEODDataWithRetry(BrokerZerodhaHelper $zerodhaHelper, int $token, string $date): ?array
    {
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            usleep($attempt === 0 ? $this->apiCallDelay : $this->apiCallDelay * (2 ** $attempt));
            try {
                $data = $this->fetchEODData($zerodhaHelper, $token, $date);
                if ($data) return $data;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Too many requests')) continue;
                return null;
            }
        }
        return null;
    }

    private function fetchEODData(BrokerZerodhaHelper $zerodhaHelper, int $token, string $date): ?array
    {
        $data = $zerodhaHelper->getHistoricalDataByToken(
            $token, 'day',
            Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:00:00',
            $date . ' 15:00:00'
        );
        if (empty($data)) return null;
        $last = end($data);
        return [
            'oi'    => ($last->oi    ?? null) !== 'null' ? (float) ($last->oi    ?? 0) : 0,
            'close' => ($last->close ?? null) !== 'null' ? (float) ($last->close ?? 0) : 0,
        ];
    }

    private function getSpotPrice(BrokerZerodhaHelper $zerodhaHelper, int $token, string $date): ?float
    {
        $data = $zerodhaHelper->getHistoricalDataByToken(
            $token, 'day',
            Carbon::parse($date)->subDay()->format('Y-m-d') . ' 15:00:00',
            $date . ' 15:00:00'
        );
        if (empty($data)) return null;
        $last = end($data);
        return isset($last->close) ? (float) $last->close : null;
    }

    // =========================================================================
    // PREVIOUS DAY LOOKUPS — query index_option_strikes, not option_strikes
    // =========================================================================
    private function getPreviousDayOI(BrokerApi $broker, string $symbol, string $position, string $date): int    { return (int)   $this->lookupPrev($broker, $symbol, $position, $date, 'daily_oi'); }
    private function getPreviousDayIV(BrokerApi $broker, string $symbol, string $position, string $date): float  { return (float) $this->lookupPrev($broker, $symbol, $position, $date, 'daily_iv'); }
    private function getPreviousDayClose(BrokerApi $broker, string $symbol, string $position, string $date): float { return (float) $this->lookupPrev($broker, $symbol, $position, $date, 'daily_close'); }

    private function lookupPrev(BrokerApi $broker, string $symbol, string $position, string $currentDate, string $field): float|int
    {
        $prevDate = Carbon::parse($currentDate)->subDay();
        for ($i = 0; $i < 10; $i++) {
            $dateStr = $prevDate->format('Y-m-d');
            if ($prevDate->isWeekend() && $dateStr !== '2026-02-01') { $prevDate->subDay(); continue; }
            if ($this->isHoliday($dateStr))                           { $prevDate->subDay(); continue; }

            $record = IndexOptionStrike::where('broker_api_id', $broker->id)
                ->where('underlying_symbol', $symbol)
                ->where('strike_position', $position)
                ->where('trading_date', $dateStr)
                ->first();

            if ($record && $record->{$field} > 0) return $record->{$field};
            $prevDate->subDay();
        }
        return 0;
    }

    // =========================================================================
    // SIGNAL HELPERS
    // =========================================================================
    private function reverseFutSignal(?string $s): string { return match ($s) { 'BUILDUP' => 'BULLISH', 'UNWINDING' => 'BEARISH', default => 'NEUTRAL' }; }
    private function reverseCESignal(?string $s): string  { return match ($s) { 'BULLISH' => 'BEARISH', 'BEARISH' => 'BULLISH', default => 'NEUTRAL' }; }
    private function reversePESignal(?string $s): string  { return match ($s) { 'BULLISH' => 'BEARISH', 'BEARISH' => 'BULLISH', default => 'NEUTRAL' }; }

    // =========================================================================
    // DATE HELPERS
    // =========================================================================
    private function getTradingDays(string $from, string $to): array
    {
        $dates = []; $current = Carbon::parse($from); $end = Carbon::parse($to);
        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');
            if (($dateStr === '2026-02-01' || !$current->isWeekend()) && !$this->isHoliday($dateStr)) {
                $dates[] = $dateStr;
            }
            $current->addDay();
        }
        return $dates;
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }

    private function getNextExpiry(string $baseSymbol, string $date): ?Carbon
    {
        $record = ZerodhaInstrument::where('name', $baseSymbol)->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')->whereDate('expiry', '>=', $date)->orderBy('expiry')->first();
        return $record ? Carbon::parse($record->expiry) : null;
    }

    private function debugWarn(string $msg): void
    {
        if ($this->option('debug')) $this->warn("    ║    ⚠ {$msg}");
    }
}