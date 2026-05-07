<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\BrokerZerodhaHelper;
use App\Models\OptionStrike9to12;
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
 * Fetch today's intraday OI + IV + Close into option_strike_9to12.
 * Open  = 9:15 AM candle
 * Current = 12:15 PM candle
 *
 * Schedule: 15 12 * * 1-5  php artisan options:fetch-9to12-oi
 */
class Fetch9to12OptionOICommand extends Command
{
    protected $signature = 'options:fetch-9to12-oi
                            {--broker= : Specific broker ID}
                            {--symbol= : Specific underlying symbol}
                            {--force : Force fetch even on holidays/weekends}
                            {--debug : Show detailed debug information}';

    protected $description = 'Fetch INTRADAY OI + IV + Close (9:15 AM → 12:15 PM) into option_strike_9to12';

    private float  $riskFreeRate  = 0.06;
    private int    $apiCallDelay  = 350000;
    private int    $maxRetries    = 3;
    private string $openTime      = '09:15:00';
    private string $currentTime   = '12:15:00';

    public function handle(): int
    {
        $today   = date('Y-m-d');
        $dayName = date('l');

        if (!$this->option('force')) {
            $isSpecialSunday = ($today === '2026-02-01');

            if (!$isSpecialSunday && ($dayName === 'Saturday' || $dayName === 'Sunday')) {
                $this->info("Skipped: Weekend ({$dayName})");
                return 0;
            }

            if (DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $today)->exists()) {
                $this->info("Skipped: Market Holiday ({$today})");
                return 0;
            }
        }

        try {
            $this->info('🚀 Starting INTRADAY OI Fetch (9:15 AM → 12:15 PM)');
            $this->info("   Date   : {$today}");
            $this->info('   Table  : option_strike_9to12');
            $this->newLine();

            $brokersQuery = BrokerApi::zerodha()->validToken();
            if ($this->option('broker')) {
                $brokersQuery->where('id', $this->option('broker'));
            }

            $brokers = $brokersQuery->get();

            if ($brokers->isEmpty()) {
                $this->error('❌ No active Zerodha brokers with valid tokens found!');
                return 1;
            }

            $this->info('📋 Found ' . $brokers->count() . " broker(s)\n");

            $totalProcessed = 0;
            $totalFailed    = 0;

            foreach ($brokers as $broker) {
                $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                $this->info("🔑 Broker: {$broker->client_name} (ID: {$broker->id})");

                $result          = $this->processBroker($broker, $today);
                $totalProcessed += $result['success'];
                $totalFailed    += $result['failed'];
            }

            $this->info("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info('✅ Done! Processed: ' . $totalProcessed . ' | Failed: ' . $totalFailed);
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

            return 0;

        } catch (Exception $e) {
            $this->error('Critical Error: ' . $e->getMessage());
            Log::error('9to12 OI fetch error: ' . $e->getMessage());
            return 1;
        }
    }

    // ─── Broker loop ──────────────────────────────────────────────────────────

    private function processBroker(BrokerApi $broker, string $date): array
    {
        $success = 0;
        $failed  = 0;

        try {
            $zerodhaHelper = new BrokerZerodhaHelper($broker);
            $result        = $this->processDaySymbols($broker, $zerodhaHelper, $date);
            $success       = $result['success'];
            $failed        = $result['failed'];

            $this->info("   Summary: ✓ {$success} | ✗ {$failed}");
            $this->newLine();

        } catch (Exception $e) {
            $this->error('   Broker failed: ' . $e->getMessage());
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ─── Symbol loop ──────────────────────────────────────────────────────────

    private function processDaySymbols(BrokerApi $broker, BrokerZerodhaHelper $zerodhaHelper, string $date): array
    {
        $success = 0;
        $failed  = 0;

        $futureSymbolsQuery = DB::table('symbols_monitored')
            ->where('broker_api_id', $broker->id)
            ->where('is_active', true)
            ->where('interval', '5minute')
            ->where('trading_symbol', 'LIKE', '%FUT');

        if ($this->option('symbol')) {
            $futureSymbolsQuery->where('trading_symbol', 'LIKE', '%' . strtoupper($this->option('symbol')) . '%');
        }

        $futureSymbols = $futureSymbolsQuery->get();

        $validSymbols = collect();
        foreach ($futureSymbols as $sym) {
            $base = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $sym->trading_symbol);
            if (ZerodhaInstrument::where('name', $base)->where('exchange', 'NFO')->whereIn('instrument_type', ['CE', 'PE'])->exists()) {
                $validSymbols->push($sym);
            }
        }

        if ($validSymbols->isEmpty()) {
            $this->warn('   ⚠️  No valid symbols found');
            return ['success' => 0, 'failed' => 0];
        }

        $this->info('   📊 Processing ' . $validSymbols->count() . ' symbols');
        $this->newLine();

        foreach ($validSymbols as $futSymbol) {
            try {
                $baseSymbol = preg_replace('/\d{2}[A-Z]{3}FUT$/i', '', $futSymbol->trading_symbol);
                $this->info("   └─ {$baseSymbol}");

                $futInstrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)->where('exchange', 'NFO')->first();
                if (!$futInstrument) { $failed++; continue; }

                $spotPrice = $this->getCandleClose($zerodhaHelper, $futInstrument->instrument_token, $date, $this->currentTime);
                if (!$spotPrice) { $failed++; continue; }

                $strikeInterval = $this->getStrikeInterval($baseSymbol);
                $atmStrike      = (int) (round($spotPrice / $strikeInterval) * $strikeInterval);

                $futAnalysis = $this->fetchFut($broker, $futSymbol, $zerodhaHelper, $date, $baseSymbol, $spotPrice, $atmStrike);
                $ceAnalysis  = $this->fetchCE($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);
                $peAnalysis  = $this->fetchPE($broker, $baseSymbol, $zerodhaHelper, $date, $atmStrike, $strikeInterval, $spotPrice);

                if ($futAnalysis && $ceAnalysis && $peAnalysis) {
                    $this->storeBTST($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    $this->storePECESignals($broker, $baseSymbol, $date, $futAnalysis, $ceAnalysis, $peAnalysis);
                    $this->info('      ✓ Stored');
                    $success++;
                } else {
                    $this->warn('      ⚠️ No data');
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error('      ✗ ' . $e->getMessage());
                Log::error("9to12 failed: {$baseSymbol}", ['date' => $date, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ─── FUT fetch ────────────────────────────────────────────────────────────

    private function fetchFut(BrokerApi $broker, $futSymbol, BrokerZerodhaHelper $zh, string $date, string $baseSymbol, float $spotPrice, int $atmStrike): ?array
    {
        try {
            $instrument = ZerodhaInstrument::where('trading_symbol', $futSymbol->trading_symbol)->where('exchange', 'NFO')->first();
            if (!$instrument) return null;

            $openData = $this->fetchCandle($zh, $instrument->instrument_token, $date, $this->openTime);
            $curData  = $this->fetchCandle($zh, $instrument->instrument_token, $date, $this->currentTime);
            if (!$openData || !$curData) return null;

            $openOI      = (int)   ($openData['oi']    ?? 0);
            $curOI       = (int)   ($curData['oi']     ?? 0);
            $oiChange    = $curOI - $openOI;
            $oiChangePct = $openOI > 0 ? round(($oiChange / $openOI) * 100, 4) : 0;

            $openCl     = (float) ($openData['close'] ?? 0);
            $curCl      = (float) ($curData['close']  ?? 0);
            $clChange   = $curCl - $openCl;
            $clChangePct = $openCl > 0 ? round(($clChange / $openCl) * 100, 4) : null;

            $oiAnalysis = OiAnalysisServiceNew::analyzeFuturesOI($curOI, $openOI, $baseSymbol);

            OptionStrike9to12::updateOrCreate(
                ['broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'trading_symbol' => $futSymbol->trading_symbol, 'strike_position' => 'FUT', 'trading_date' => $date],
                [
                    'option_type' => 'FUT', 'strike_price' => $spotPrice,
                    'expiry'      => preg_replace('/.*(\d{2}[A-Z]{3})FUT$/i', '$1', $futSymbol->trading_symbol),
                    'expiry_date' => $this->getNextExpiry($baseSymbol, $date),
                    'instrument_token' => $instrument->instrument_token,
                    'exchange' => 'NFO', 'lot_size' => $instrument->lot_size ?? 1, 'is_active' => true, 'spot_price' => $spotPrice,
                    'open_oi' => $openOI, 'current_oi' => $curOI, 'oi_change' => $oiChange, 'oi_change_pct' => $oiChangePct,
                    'direction' => $this->reverseFut($oiAnalysis['direction']), 'strength' => $oiAnalysis['strength'], 'market_bias' => $oiAnalysis['market_bias'] ?? null,
                    'open_close' => $openCl, 'current_close' => $curCl, 'close_change' => $clChange, 'close_change_pct' => $clChangePct,
                    'last_synced_at' => now(),
                ]
            );

            return ['oi' => array_merge($oiAnalysis, ['oi_change_pct' => $oiChangePct, 'daily_oi_change_pct' => $oiChangePct])];

        } catch (Exception $e) {
            Log::error("9to12 FUT failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── CE fetch ─────────────────────────────────────────────────────────────

    private function fetchCE(BrokerApi $broker, string $baseSymbol, BrokerZerodhaHelper $zh, string $date, int $atmStrike, int $interval, float $spotPrice): ?array
    {
        try {
            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) return null;

            $expiryCode   = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = max(1, Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date)));
            $strikes      = [$atmStrike - $interval, $atmStrike, $atmStrike + $interval];

            $openOITotal = $curOITotal = $openIVTotal = $curIVTotal = 0;
            $openClTotal = $curClTotal = $ivCount = $closeCount = 0;
            $instruments = [];

            foreach ($strikes as $strike) {
                $sym = $baseSymbol . $expiryCode . ((int) $strike) . 'CE';
                $ins = ZerodhaInstrument::where('trading_symbol', $sym)->where('exchange', 'NFO')->first();
                if (!$ins) continue;

                $od = $this->fetchCandle($zh, $ins->instrument_token, $date, $this->openTime);
                $cd = $this->fetchCandle($zh, $ins->instrument_token, $date, $this->currentTime);
                if (!$od || !$cd || (float)($cd['close'] ?? 0) <= 0) continue;

                $openOITotal += (int) ($od['oi'] ?? 0);
                $curOITotal  += (int) ($cd['oi'] ?? 0);
                $openClTotal += (float) ($od['close'] ?? 0);
                $curClTotal  += (float) ($cd['close'] ?? 0);
                $closeCount++;
                $instruments[] = $sym;

                $iv = IVCalculator::calculate((float)$cd['close'], $spotPrice, $strike, $daysToExpiry, 'CE', $this->riskFreeRate);
                if ($iv !== null) { $curIVTotal += $iv; $ivCount++; }
                if ((float)($od['close'] ?? 0) > 0) {
                    $openIV = IVCalculator::calculate((float)$od['close'], $spotPrice, $strike, $daysToExpiry, 'CE', $this->riskFreeRate);
                    if ($openIV !== null) $openIVTotal += $openIV;
                }
            }

            if ($curOITotal == 0) return null;

            $avgOpenIV = $ivCount > 0 ? $openIVTotal / $ivCount : null;
            $avgCurIV  = $ivCount > 0 ? $curIVTotal  / $ivCount : null;
            $avgOpenCl = $closeCount > 0 ? $openClTotal / $closeCount : null;
            $avgCurCl  = $closeCount > 0 ? $curClTotal  / $closeCount : null;

            $oiChange    = $curOITotal - $openOITotal;
            $oiChangePct = $openOITotal > 0 ? round(($oiChange / $openOITotal) * 100, 4) : 0;

            $ivChange    = ($avgCurIV !== null && $avgOpenIV !== null) ? $avgCurIV - $avgOpenIV : null;
            $ivChangePct = ($ivChange !== null && $avgOpenIV > 0) ? ($ivChange / $avgOpenIV) * 100 : null;
            $clChange    = ($avgCurCl !== null && $avgOpenCl !== null) ? $avgCurCl - $avgOpenCl : null;
            $clChangePct = ($clChange !== null && $avgOpenCl > 0) ? ($clChange / $avgOpenCl) * 100 : null;

            $oiAnalysis = OiAnalysisServiceNew::analyzeCallOptionsOI($curOITotal, $openOITotal, $baseSymbol);
            $ivAnalysis = IVAnalysisServiceNew::analyzeCallOptionsIV($avgCurIV, $avgOpenIV, $baseSymbol);

            OptionStrike9to12::updateOrCreate(
                ['broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'strike_position' => 'CE_MERGED', 'trading_date' => $date],
                [
                    'trading_symbol' => implode(',', $instruments), 'option_type' => 'CE',
                    'strike_price' => $atmStrike, 'expiry' => $expiryCode, 'expiry_date' => $expiryDate,
                    'instrument_token' => null, 'exchange' => 'NFO', 'lot_size' => 1, 'is_active' => true, 'spot_price' => $spotPrice,
                    'open_oi' => $openOITotal, 'current_oi' => $curOITotal, 'oi_change' => $oiChange, 'oi_change_pct' => $oiChangePct,
                    'direction' => $this->reverseCE($oiAnalysis['direction']), 'strength' => $oiAnalysis['strength'],
                    'open_iv' => $avgOpenIV, 'current_iv' => $avgCurIV, 'iv_change' => $ivChange, 'iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'], 'iv_strength' => $ivAnalysis['iv_strength'],
                    'open_close' => $avgOpenCl, 'current_close' => $avgCurCl, 'close_change' => $clChange, 'close_change_pct' => $clChangePct,
                    'last_synced_at' => now(),
                ]
            );

            $oiAnalysis['daily_oi_change_pct'] = $oiChangePct;
            $oiAnalysis['daily_oi']            = $curOITotal;

            return ['oi' => $oiAnalysis, 'iv' => $ivAnalysis];

        } catch (Exception $e) {
            Log::error("9to12 CE failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── PE fetch ─────────────────────────────────────────────────────────────

    private function fetchPE(BrokerApi $broker, string $baseSymbol, BrokerZerodhaHelper $zh, string $date, int $atmStrike, int $interval, float $spotPrice): ?array
    {
        try {
            $expiryDate = $this->getNextExpiry($baseSymbol, $date);
            if (!$expiryDate) return null;

            $expiryCode   = strtoupper($expiryDate->format('yM'));
            $daysToExpiry = max(1, Carbon::parse($expiryDate)->diffInDays(Carbon::parse($date)));
            $strikes      = [$atmStrike - $interval, $atmStrike, $atmStrike + $interval];

            $openOITotal = $curOITotal = $openIVTotal = $curIVTotal = 0;
            $openClTotal = $curClTotal = $ivCount = $closeCount = 0;
            $instruments = [];

            foreach ($strikes as $strike) {
                $sym = $baseSymbol . $expiryCode . ((int) $strike) . 'PE';
                $ins = ZerodhaInstrument::where('trading_symbol', $sym)->where('exchange', 'NFO')->first();
                if (!$ins) continue;

                $od = $this->fetchCandle($zh, $ins->instrument_token, $date, $this->openTime);
                $cd = $this->fetchCandle($zh, $ins->instrument_token, $date, $this->currentTime);
                if (!$od || !$cd || (float)($cd['close'] ?? 0) <= 0) continue;

                $openOITotal += (int) ($od['oi'] ?? 0);
                $curOITotal  += (int) ($cd['oi'] ?? 0);
                $openClTotal += (float) ($od['close'] ?? 0);
                $curClTotal  += (float) ($cd['close'] ?? 0);
                $closeCount++;
                $instruments[] = $sym;

                $iv = IVCalculator::calculate((float)$cd['close'], $spotPrice, $strike, $daysToExpiry, 'PE', $this->riskFreeRate);
                if ($iv !== null) { $curIVTotal += $iv; $ivCount++; }
                if ((float)($od['close'] ?? 0) > 0) {
                    $openIV = IVCalculator::calculate((float)$od['close'], $spotPrice, $strike, $daysToExpiry, 'PE', $this->riskFreeRate);
                    if ($openIV !== null) $openIVTotal += $openIV;
                }
            }

            if ($curOITotal == 0) return null;

            $avgOpenIV = $ivCount > 0 ? $openIVTotal / $ivCount : null;
            $avgCurIV  = $ivCount > 0 ? $curIVTotal  / $ivCount : null;
            $avgOpenCl = $closeCount > 0 ? $openClTotal / $closeCount : null;
            $avgCurCl  = $closeCount > 0 ? $curClTotal  / $closeCount : null;

            $oiChange    = $curOITotal - $openOITotal;
            $oiChangePct = $openOITotal > 0 ? round(($oiChange / $openOITotal) * 100, 4) : 0;

            $ivChange    = ($avgCurIV !== null && $avgOpenIV !== null) ? $avgCurIV - $avgOpenIV : null;
            $ivChangePct = ($ivChange !== null && $avgOpenIV > 0) ? ($ivChange / $avgOpenIV) * 100 : null;
            $clChange    = ($avgCurCl !== null && $avgOpenCl !== null) ? $avgCurCl - $avgOpenCl : null;
            $clChangePct = ($clChange !== null && $avgOpenCl > 0) ? ($clChange / $avgOpenCl) * 100 : null;

            $oiAnalysis = OiAnalysisServiceNew::analyzePutOptionsOI($curOITotal, $openOITotal, $baseSymbol);
            $ivAnalysis = IVAnalysisServiceNew::analyzePutOptionsIV($avgCurIV, $avgOpenIV, $baseSymbol);

            OptionStrike9to12::updateOrCreate(
                ['broker_api_id' => $broker->id, 'underlying_symbol' => $baseSymbol, 'strike_position' => 'PE_MERGED', 'trading_date' => $date],
                [
                    'trading_symbol' => implode(',', $instruments), 'option_type' => 'PE',
                    'strike_price' => $atmStrike, 'expiry' => $expiryCode, 'expiry_date' => $expiryDate,
                    'instrument_token' => null, 'exchange' => 'NFO', 'lot_size' => 1, 'is_active' => true, 'spot_price' => $spotPrice,
                    'open_oi' => $openOITotal, 'current_oi' => $curOITotal, 'oi_change' => $oiChange, 'oi_change_pct' => $oiChangePct,
                    'direction' => $this->reversePE($oiAnalysis['direction']), 'strength' => $oiAnalysis['strength'],
                    'open_iv' => $avgOpenIV, 'current_iv' => $avgCurIV, 'iv_change' => $ivChange, 'iv_change_pct' => $ivChangePct,
                    'iv_direction' => $ivAnalysis['iv_direction'], 'iv_strength' => $ivAnalysis['iv_strength'],
                    'open_close' => $avgOpenCl, 'current_close' => $avgCurCl, 'close_change' => $clChange, 'close_change_pct' => $clChangePct,
                    'last_synced_at' => now(),
                ]
            );

            $oiAnalysis['daily_oi_change_pct'] = $oiChangePct;
            $oiAnalysis['daily_oi']            = $curOITotal;

            return ['oi' => $oiAnalysis, 'iv' => $ivAnalysis];

        } catch (Exception $e) {
            Log::error("9to12 PE failed: {$baseSymbol}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ─── Signals ──────────────────────────────────────────────────────────────

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup but CE stronger (CE: +{$cePct}% > PE: +{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup but PE stronger (PE: +{$pePct}% > CE: +{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding but CE stronger (CE: {$cePct}% < PE: {$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding but PE stronger (PE: {$pePct}% < CE: {$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    private function storePECESignals(BrokerApi $broker, string $baseSymbol, string $date, array $fut, array $ce, array $pe): void
    {
        $cePct    = $ce['oi']['daily_oi_change_pct'] ?? 0;
        $pePct    = $pe['oi']['daily_oi_change_pct'] ?? 0;
        $signal   = $this->getOISignal($cePct, $pePct);
        $sentiment = $signal['signal'];
        $ceOI     = $ce['oi']['daily_oi'] ?? 0;
        $peOI     = $pe['oi']['daily_oi'] ?? 0;

        OptionStrike9to12::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update([
                'oi_interpretation' => $signal['reason'],
                'oi_condition'      => $signal['condition'],
                'ce_oi_change_pct'  => $cePct,
                'pe_oi_change_pct'  => $pePct,
                'pe_ce_ratio'       => $ceOI > 0 ? round($peOI / $ceOI, 2) : 0,
                'options_sentiment' => $sentiment,
                'futures_oi_view'   => $fut['oi']['market_bias'] ?? 'Normal',
                'final_sentiment'   => $sentiment,
                'trade_action'      => match($sentiment) { 'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT' },
            ]);

        if ($this->option('debug')) {
            $this->info("      CE%: {$cePct}  PE%: {$pePct}  → {$sentiment}");
        }
    }

    private function storeBTST(BrokerApi $broker, string $baseSymbol, string $date, array $fut, array $ce, array $pe): void
    {
        $btst = IVAnalysisServiceNew::getBTSTSignal($fut['oi'], $ce['oi'], $ce['iv'], $pe['oi'], $pe['iv']);

        OptionStrike9to12::where('broker_api_id', $broker->id)
            ->where('underlying_symbol', $baseSymbol)
            ->where('strike_position', 'FUT')
            ->where('trading_date', $date)
            ->update(['btst_signal' => $btst['btst_signal'], 'btst_confidence' => $btst['confidence'], 'btst_reason' => $btst['reason']]);
    }

    // ─── API helpers ──────────────────────────────────────────────────────────

    private function fetchCandle(BrokerZerodhaHelper $zh, int $token, string $date, string $time): ?array
    {
        // Zerodha returns 0 candles if from == to for 5-minute interval.
        // We need a small window: from = target time, to = target time + 5 min.
        $fromDt = Carbon::parse("{$date} {$time}");
        $toDt   = $fromDt->copy()->addMinutes(5);

        $fromStr = $fromDt->format('Y-m-d H:i:s');
        $toStr   = $toDt->format('Y-m-d H:i:s');

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                usleep($attempt > 0 ? $this->apiCallDelay * (2 ** $attempt) : $this->apiCallDelay);

                $data = $zh->getHistoricalDataByToken($token, '5minute', $fromStr, $toStr);

                if (empty($data)) { continue; }

                // Always use the FIRST candle — that's the one at our target time
                $c = reset($data);
                return [
                    'oi'    => ($c->oi    ?? null) !== 'null' ? (float)($c->oi    ?? 0) : 0,
                    'close' => ($c->close ?? null) !== 'null' ? (float)($c->close ?? 0) : 0,
                ];

            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Too many requests')) { continue; }
                Log::error("Candle fetch failed token {$token} @ {$time}: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    private function getCandleClose(BrokerZerodhaHelper $zh, int $token, string $date, string $time): ?float
    {
        $data = $this->fetchCandle($zh, $token, $date, $time);
        return $data ? ($data['close'] ?: null) : null;
    }

    // ─── Misc helpers ─────────────────────────────────────────────────────────

    private function reverseFut(?string $s): string
    {
        return match($s) { 'BUILDUP' => 'BULLISH', 'UNWINDING' => 'BEARISH', default => 'NEUTRAL' };
    }

    private function reverseCE(?string $s): string
    {
        return match($s) { 'BULLISH' => 'BEARISH', 'BEARISH' => 'BULLISH', default => 'NEUTRAL' };
    }

    private function reversePE(?string $s): string { return $this->reverseCE($s); }

    private function getNextExpiry(string $baseSymbol, string $date): ?Carbon
    {
        $r = ZerodhaInstrument::where('name', $baseSymbol)->where('exchange', 'NFO')->where('instrument_type', 'CE')->whereDate('expiry', '>=', $date)->orderBy('expiry')->first();
        return $r ? Carbon::parse($r->expiry) : null;
    }

    private function getStrikeInterval(string $baseSymbol): int
    {
        return [
            'NIFTY' => 100, 'BANKNIFTY' => 100, 'FINNIFTY' => 50, 'MIDCPNIFTY' => 25,
            'AXISBANK' => 10, 'ICICIBANK' => 10, 'INDUSINDBK' => 10, 'BHARTIARTL' => 20,
            'SHRIRAMFIN' => 10, 'LTF' => 5, 'PAYTM' => 20, 'POLICYBZR' => 20,
            'BAJAJFINSV' => 20, 'INFY' => 20, 'TATAELXSI' => 50, 'TATATECH' => 10,
            'HAVELLS' => 20, 'TITAN' => 20, 'ASIANPAINT' => 20, 'TATACONSUMER' => 10,
            'VOLTAS' => 20, 'AUROPHARMA' => 10, 'LAURUSLABS' => 10, 'SRF' => 20,
            'JSWSTEEL' => 10, 'LT' => 20, 'BHEL' => 5, 'ADANIPORTS' => 20,
            'HAL' => 50, 'BDL' => 20, 'MCX' => 20, 'BSE' => 50, 'CDSL' => 20,
            'LICHSG' => 5, 'DELHIVERY' => 10, 'BHARATFORG' => 20, 'PGEL' => 10, 'TMPV' => 5,
            'HINDALCO' => 10, 'VEDL' => 10, 'DRREDDY' => 50, 'LICHSGFIN' => 5,
            'TATACONSUM' => 10, 'ABCCAPITAL' => 10, 'SBIN' => 10, 'VBL' => 20,
            'BAJFINANCE' => 50, 'TCS' => 50, 'COFORGE' => 50, 'EICHERMOT' => 50,
            'HEROMOTOCO' => 20, 'AMBUJACEM' => 5, 'FORTIS' => 5, 'UPL' => 10,
            'M&M' => 20, 'NATIONALUM' => 5, 'BPCL' => 10, 'ETERNAL' => 10,
        ][$baseSymbol] ?? 100;
    }
}