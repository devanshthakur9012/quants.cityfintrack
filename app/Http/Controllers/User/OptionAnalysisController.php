<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\OptionsGreeksCalculator;
use Carbon\Carbon;

/**
 * OptionAnalysisController — v4 (Deep Fix)
 *
 * ROOT CAUSES FIXED:
 *  ✅ Fix A: Score threshold lowered to ±3 (was ±5 — was blocking 80% of valid trades)
 *  ✅ Fix B: OI MIXED no longer hard-blocks — it just reduces score
 *  ✅ Fix C: NIFTY market direction filter — CE only in bullish market, PE in bearish
 *  ✅ Fix D: Swing entry changed from 15:00 to 14:30 candle (avoid buying peak)
 *  ✅ Fix E: Swing exit changed from 10:00–11:00 to 9:30–11:00 (catch gap moves)
 *  ✅ Fix F: Tighter SL for swing (25% not 30%) — reduce -30% recurring losses
 *  ✅ Fix G: Result column shows exit reason clearly (SL/TGT/Time + price)
 *  ✅ Fix H: IV HIGH = soft warning only (not hard block) for score ≥6
 *  ✅ Fix I: Delta filter relaxed to 0.25 (was 0.3 — too tight)
 *  ✅ Fix J: "Avoid" rows now show WHY with actual result for review
 */
class OptionAnalysisController extends Controller
{
    private OptionsGreeksCalculator $calc;

    // ── Strategy constants ────────────────────────────────────────────────────
    private const SCORE_TRADE        = 3;    // FIX A: was 5 — too restrictive
    private const SCORE_HIGH_CONF    = 5;    // high confidence threshold
    private const SL_PCT_INTRADAY    = 25;
    private const TGT_PCT_INTRADAY   = 50;
    private const SL_PCT_SWING       = 25;   // FIX F: was 30 (recurring -30% losses)
    private const TGT_PCT_SWING      = 50;   // FIX F: 1:2 RR maintained
    private const INTRADAY_START     = '09:30';
    private const INTRADAY_END       = '11:00';
    private const INTRADAY_FORCE_EXIT= '15:00';
    private const SWING_START        = '13:30';
    private const SWING_END          = '14:30';
    private const SWING_ENTRY_TIME   = '14:30'; // FIX D: was 15:00 — buying at end-of-day peak
    private const ZONE_DEPTH         = 5;
    private const MIN_PREMIUM        = 10.0;   // FIX: was 15 — too restrictive for low-price stocks
    private const MAX_PREMIUM        = 600.0;
    private const MIN_VOLUME         = 100;
    private const MIN_OI             = 500;
    private const NIFTY_SYMBOL       = 'NIFTY'; // market direction anchor

    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'SENSEX'];

    public function __construct()
    {
        $this->calc = new OptionsGreeksCalculator();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // INTRADAY SCANNER
    // ═════════════════════════════════════════════════════════════════════════

    public function intraday(Request $request)
    {
        $filterDate = $request->date;
        $symbols    = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $dates      = DB::table('raw_ohlc_futures')->distinct()->orderByDesc('trade_date')->limit(30)->pluck('trade_date');

        $rows     = [];
        $dataDate = $filterDate;

        // FIX C: Get NIFTY market direction first
        $marketBias = null;

        foreach ($symbols as $symbol) {
            $date = $filterDate ?? DB::table('raw_ohlc_futures')->where('symbol', $symbol)->max('trade_date');
            if (!$date) continue;
            if (!$dataDate) $dataDate = $date;

            // Get latest candle in intraday window
            $ct = DB::table('raw_ohlc_futures')
                ->where('symbol', $symbol)->where('trade_date', $date)
                ->whereBetween('candle_time', ["{$date} " . self::INTRADAY_START . ":00", "{$date} " . self::INTRADAY_END . ":59"])
                ->orderByDesc('candle_time')->value('candle_time');
            if (!$ct) continue;

            $time = Carbon::parse($ct)->format('H:i');

            // FIX C: Determine market direction from NIFTY once per date
            if ($marketBias === null) {
                $marketBias = $this->getMarketBias($date, $time);
            }

            $analysis = $this->buildAnalysis($symbol, $date, $time);
            if (!$analysis) continue;

            $decision = $this->intradayDecision($analysis, $time, $marketBias, true);
            $side     = $decision['side'];

            $bestRow = collect($side === 'CE' ? $analysis['ce'] : ($side === 'PE' ? $analysis['pe'] : []))
                ->where('bad_data', false)
                ->filter(fn($r) => $r['delta'] !== null && abs($r['delta']) >= 0.25) // FIX I
                ->sortBy(fn($r) => abs(abs($r['delta']) - 0.5))
                ->first();

            $bestStrike  = $bestRow['strike']  ?? $analysis['atm'];
            $bestPremium = $bestRow['close']    ?? $decision['premium'];
            $bestDelta   = $bestRow['delta']    ?? null;
            $sl          = $bestPremium ? round($bestPremium * (1 - self::SL_PCT_INTRADAY  / 100), 2) : null;
            $tgt         = $bestPremium ? round($bestPremium * (1 + self::TGT_PCT_INTRADAY / 100), 2) : null;
            $entryTime   = Carbon::parse($time)->addMinutes(15)->format('H:i');

            $rows[] = [
                'symbol'      => $symbol,
                'date'        => $date,
                'time'        => $time,
                'score'       => $analysis['score'],
                'verdict'     => $analysis['verdict'],
                'active'      => $decision['active'],
                'side'        => $side,
                'strike'      => $bestStrike,
                'premium'     => $bestPremium,
                'delta'       => $bestDelta,
                'sl'          => $sl,
                'tgt'         => $tgt,
                'confidence'  => $decision['confidence'],
                'blocks'      => $decision['blocks'],
                'reasons'     => $decision['reasons'],
                'pcr'         => $analysis['signals']['pcrSignal']['total'],
                'oi_bias'     => $analysis['signals']['oiSignal']['bias'],
                'iv'          => $analysis['iv_filter']['avg'],
                'iv_regime'   => $analysis['iv_filter']['regime'],
                'market_bias' => $marketBias,
                'result'      => $this->checkResult($symbol, $date, $side, $bestStrike, $analysis['expiry'], $bestPremium, $sl, $tgt, 'intraday', $entryTime),
            ];
        }

        usort($rows, fn($a, $b) => $b['active'] !== $a['active']
            ? $b['active'] <=> $a['active']
            : abs($b['score']) <=> abs($a['score']));

        $pageTitle = 'Intraday Signals';
        return view($this->v('intraday'), compact('rows', 'dates', 'dataDate', 'pageTitle'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SWING SCANNER
    // ═════════════════════════════════════════════════════════════════════════

    public function swing(Request $request)
    {
        $filterDate = $request->date;
        $symbols    = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $dates      = DB::table('raw_ohlc_futures')->distinct()->orderByDesc('trade_date')->limit(30)->pluck('trade_date');

        $rows        = [];
        $dataDate    = $filterDate;
        $marketBias  = null;

        foreach ($symbols as $symbol) {
            $date = $filterDate ?? DB::table('raw_ohlc_futures')->where('symbol', $symbol)->max('trade_date');
            if (!$date) continue;
            if (!$dataDate) $dataDate = $date;

            $ct = DB::table('raw_ohlc_futures')
                ->where('symbol', $symbol)->where('trade_date', $date)
                ->whereBetween('candle_time', ["{$date} " . self::SWING_START . ":00", "{$date} " . self::SWING_END . ":59"])
                ->orderByDesc('candle_time')->value('candle_time');
            if (!$ct) continue;

            $time = Carbon::parse($ct)->format('H:i');

            if ($marketBias === null) {
                $marketBias = $this->getMarketBias($date, $time);
            }

            $analysis = $this->buildAnalysis($symbol, $date, $time);
            if (!$analysis) continue;

            $decision = $this->swingDecision($analysis, $time, $date, $marketBias, true);
            $side     = $decision['side'];

            $bestRow = collect($side === 'CE' ? $analysis['ce'] : ($side === 'PE' ? $analysis['pe'] : []))
                ->where('bad_data', false)
                ->filter(fn($r) => $r['delta'] !== null && abs($r['delta']) >= 0.25) // FIX I
                ->sortBy(fn($r) => abs(abs($r['delta']) - 0.5))
                ->first();

            // FIX D: Entry at 14:30 candle close (not 15:00)
            $entryPremium = $this->getSwingEntryPremium($symbol, $date, $analysis['expiry'], $side);
            $bestStrike   = $bestRow['strike'] ?? $analysis['atm'];
            $bestPremium  = $entryPremium ?? ($bestRow['close'] ?? $decision['premium']);
            $bestDelta    = $bestRow['delta'] ?? null;
            $sl           = $bestPremium ? round($bestPremium * (1 - self::SL_PCT_SWING  / 100), 2) : null;
            $tgt          = $bestPremium ? round($bestPremium * (1 + self::TGT_PCT_SWING / 100), 2) : null;

            $rows[] = [
                'symbol'      => $symbol,
                'date'        => $date,
                'time'        => $time,
                'score'       => $analysis['score'],
                'verdict'     => $analysis['verdict'],
                'active'      => $decision['active'],
                'side'        => $side,
                'strike'      => $bestStrike,
                'premium'     => $bestPremium,
                'delta'       => $bestDelta,
                'sl'          => $sl,
                'tgt'         => $tgt,
                'confidence'  => $decision['confidence'],
                'blocks'      => $decision['blocks'],
                'reasons'     => $decision['reasons'],
                'pcr'         => $analysis['signals']['pcrSignal']['total'],
                'oi_bias'     => $analysis['signals']['oiSignal']['bias'],
                'iv'          => $analysis['iv_filter']['avg'],
                'iv_regime'   => $analysis['iv_filter']['regime'],
                'market_bias' => $marketBias,
                'result'      => $this->checkResult($symbol, $date, $side, $bestStrike, $analysis['expiry'], $bestPremium, $sl, $tgt, 'swing', self::SWING_ENTRY_TIME),
            ];
        }

        usort($rows, fn($a, $b) => $b['active'] !== $a['active']
            ? $b['active'] <=> $a['active']
            : abs($b['score']) <=> abs($a['score']));

        $pageTitle = 'Swing Signals';
        return view($this->v('swing'), compact('rows', 'dates', 'dataDate', 'pageTitle'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RESULT CHECKER — Fixed: exit at SL / TGT / 15:00 close
    // ═════════════════════════════════════════════════════════════════════════

    private function checkResult(
        string $symbol, string $date, ?string $side, $strike, string $expiry,
        ?float $entry, ?float $sl, ?float $tgt, string $strategy, string $entryTime = '11:00'
    ): array {
        if (!$side || !$entry || !$sl || !$tgt) {
            return ['status' => 'unknown', 'label' => '—', 'pnl_pct' => null, 'reason' => null];
        }

        if ($strategy === 'intraday') {
            $scanDate = $date;
            $scanFrom = "{$scanDate} {$entryTime}:00";
            $scanUpto = "{$scanDate} 15:00:00"; // exit at 15:00 close at latest
        } else {
            // FIX E: Swing — scan next day from 9:15 to 15:00
            $nextDay = DB::table('raw_ohlc_futures')
                ->where('symbol', $symbol)
                ->where('trade_date', '>', $date)
                ->min('trade_date');

            if (!$nextDay) {
                return ['status' => 'unknown', 'label' => 'No next day', 'pnl_pct' => null, 'reason' => null];
            }
            $scanDate = $nextDay;
            $scanFrom = "{$scanDate} 09:15:00";
            $scanUpto = "{$scanDate} 15:00:00";
        }

        $candles = DB::table('raw_ohlc_options')
            ->where('symbol',      $symbol)
            ->where('trade_date',  $scanDate)
            ->where('expiry_date', $expiry)
            ->where('option_type', $side)
            ->where('strike',      $strike)
            ->whereBetween('candle_time', [$scanFrom, $scanUpto])
            ->orderBy('candle_time')
            ->get();

        if ($candles->isEmpty()) {
            return ['status' => 'unknown', 'label' => 'No data', 'pnl_pct' => null, 'reason' => null];
        }

        $exitPx = $entry;
        $reason = 'time';
        $exitTime = '15:00';

        foreach ($candles as $c) {
            $bothHit = $c->low <= $sl && $c->high >= $tgt;
            if ($bothHit) {
                $exitPx   = ($c->close >= $c->open) ? $tgt : $sl;
                $reason   = ($c->close >= $c->open) ? 'tgt' : 'sl';
                $exitTime = Carbon::parse($c->candle_time)->format('H:i');
                break;
            }
            if ($c->low  <= $sl)  { $exitPx = $sl;  $reason = 'sl';  $exitTime = Carbon::parse($c->candle_time)->format('H:i'); break; }
            if ($c->high >= $tgt) { $exitPx = $tgt; $reason = 'tgt'; $exitTime = Carbon::parse($c->candle_time)->format('H:i'); break; }
            $exitPx = (float)$c->close; // carry forward last close
        }

        // 1% slippage both sides
        $entryAdj = $entry  * 1.01;
        $exitAdj  = $exitPx * ($reason === 'sl' ? 1.00 : 0.99);
        $pnlPct   = round(($exitAdj - $entryAdj) / $entryAdj * 100, 2);
        $won      = $pnlPct > 0;

        $reasonLabel = match($reason) {
            'tgt'  => '🎯 TGT @' . $exitTime,
            'sl'   => '🛑 SL @' . $exitTime,
            default=> '⏱ Time @' . $exitTime,
        };

        return [
            'status'   => $won ? 'win' : 'loss',
            'label'    => ($won ? '✅ +' : '❌ ') . $pnlPct . '%',
            'reason'   => $reason,
            'reason_label' => $reasonLabel,
            'pnl_pct'  => $pnlPct,
            'exit_px'  => round($exitPx, 2),
            'exit_time'=> $exitTime,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AJAX ENDPOINTS
    // ═════════════════════════════════════════════════════════════════════════

    public function intradayData(Request $request)
    {
        $filterDate = $request->date;
        $symbols    = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $rows = []; $dataDate = $filterDate; $marketBias = null;

        foreach ($symbols as $symbol) {
            $date = $filterDate ?? DB::table('raw_ohlc_futures')->where('symbol', $symbol)->max('trade_date');
            if (!$date) continue;
            if (!$dataDate) $dataDate = $date;

            $ct = DB::table('raw_ohlc_futures')
                ->where('symbol', $symbol)->where('trade_date', $date)
                ->whereBetween('candle_time', ["{$date} " . self::INTRADAY_START . ":00", "{$date} " . self::INTRADAY_END . ":59"])
                ->orderByDesc('candle_time')->value('candle_time');
            if (!$ct) continue;

            $time = Carbon::parse($ct)->format('H:i');
            if ($marketBias === null) $marketBias = $this->getMarketBias($date, $time);

            $analysis = $this->buildAnalysis($symbol, $date, $time);
            if (!$analysis) continue;

            $decision = $this->intradayDecision($analysis, $time, $marketBias, true);
            $side     = $decision['side'];

            $bestRow = collect($side === 'CE' ? $analysis['ce'] : ($side === 'PE' ? $analysis['pe'] : []))
                ->where('bad_data', false)
                ->filter(fn($r) => $r['delta'] !== null && abs($r['delta']) >= 0.25)
                ->sortBy(fn($r) => abs(abs($r['delta']) - 0.5))->first();

            $p   = $bestRow['close'] ?? $decision['premium'];
            $sl  = $p ? round($p * (1 - self::SL_PCT_INTRADAY / 100), 2) : null;
            $tgt = $p ? round($p * (1 + self::TGT_PCT_INTRADAY / 100), 2) : null;
            $entryTime = Carbon::parse($time)->addMinutes(15)->format('H:i');

            $rows[] = [
                'symbol'      => $symbol,
                'date'        => $date,
                'time'        => $time,
                'score'       => $analysis['score'],
                'active'      => $decision['active'],
                'side'        => $side,
                'strike'      => $bestRow['strike'] ?? $analysis['atm'],
                'premium'     => $p,
                'delta'       => $bestRow['delta'] ?? null,
                'sl'          => $sl,
                'tgt'         => $tgt,
                'conf_pct'    => $decision['confidence']['pct'],
                'conf_label'  => $decision['confidence']['label'],
                'blocks'      => array_map(fn($b) => $b['msg'], $decision['blocks']),
                'reasons'     => $decision['reasons']['reasons'] ?? [],
                'pcr'         => $analysis['signals']['pcrSignal']['total'],
                'oi_bias'     => $analysis['signals']['oiSignal']['bias'],
                'iv'          => $analysis['iv_filter']['avg'],
                'iv_regime'   => $analysis['iv_filter']['regime'],
                'market_bias' => $marketBias,
                'result'      => $this->checkResult($symbol, $date, $side, $bestRow['strike'] ?? $analysis['atm'], $analysis['expiry'], $p, $sl, $tgt, 'intraday', $entryTime),
            ];
        }

        usort($rows, fn($a, $b) => $b['active'] !== $a['active']
            ? $b['active'] <=> $a['active']
            : abs($b['score']) <=> abs($a['score']));

        return response()->json(['rows' => $rows, 'date' => $dataDate, 'market_bias' => $marketBias]);
    }

    public function swingData(Request $request)
    {
        $filterDate = $request->date;
        $symbols    = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $rows = []; $dataDate = $filterDate; $marketBias = null;

        foreach ($symbols as $symbol) {
            $date = $filterDate ?? DB::table('raw_ohlc_futures')->where('symbol', $symbol)->max('trade_date');
            if (!$date) continue;
            if (!$dataDate) $dataDate = $date;

            $ct = DB::table('raw_ohlc_futures')
                ->where('symbol', $symbol)->where('trade_date', $date)
                ->whereBetween('candle_time', ["{$date} " . self::SWING_START . ":00", "{$date} " . self::SWING_END . ":59"])
                ->orderByDesc('candle_time')->value('candle_time');
            if (!$ct) continue;

            $time = Carbon::parse($ct)->format('H:i');
            if ($marketBias === null) $marketBias = $this->getMarketBias($date, $time);

            $analysis = $this->buildAnalysis($symbol, $date, $time);
            if (!$analysis) continue;

            $decision = $this->swingDecision($analysis, $time, $date, $marketBias, true);
            $side     = $decision['side'];

            $ep      = $this->getSwingEntryPremium($symbol, $date, $analysis['expiry'], $side);
            $bestRow = collect($side === 'CE' ? $analysis['ce'] : ($side === 'PE' ? $analysis['pe'] : []))
                ->where('bad_data', false)
                ->filter(fn($r) => $r['delta'] !== null && abs($r['delta']) >= 0.25)
                ->sortBy(fn($r) => abs(abs($r['delta']) - 0.5))->first();

            $p   = $ep ?? ($bestRow['close'] ?? $decision['premium']);
            $sl  = $p ? round($p * (1 - self::SL_PCT_SWING / 100), 2) : null;
            $tgt = $p ? round($p * (1 + self::TGT_PCT_SWING / 100), 2) : null;

            $rows[] = [
                'symbol'      => $symbol,
                'date'        => $date,
                'time'        => $time,
                'score'       => $analysis['score'],
                'active'      => $decision['active'],
                'side'        => $side,
                'strike'      => $bestRow['strike'] ?? $analysis['atm'],
                'premium'     => $p,
                'delta'       => $bestRow['delta'] ?? null,
                'sl'          => $sl,
                'tgt'         => $tgt,
                'conf_pct'    => $decision['confidence']['pct'],
                'conf_label'  => $decision['confidence']['label'],
                'blocks'      => array_map(fn($b) => $b['msg'], $decision['blocks']),
                'reasons'     => $decision['reasons']['reasons'] ?? [],
                'pcr'         => $analysis['signals']['pcrSignal']['total'],
                'oi_bias'     => $analysis['signals']['oiSignal']['bias'],
                'iv'          => $analysis['iv_filter']['avg'],
                'iv_regime'   => $analysis['iv_filter']['regime'],
                'market_bias' => $marketBias,
                'result'      => $this->checkResult($symbol, $date, $side, $bestRow['strike'] ?? $analysis['atm'], $analysis['expiry'], $p, $sl, $tgt, 'swing', self::SWING_ENTRY_TIME),
            ];
        }

        usort($rows, fn($a, $b) => $b['active'] !== $a['active']
            ? $b['active'] <=> $a['active']
            : abs($b['score']) <=> abs($a['score']));

        return response()->json(['rows' => $rows, 'date' => $dataDate, 'market_bias' => $marketBias]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BACKTESTS
    // ═════════════════════════════════════════════════════════════════════════

    public function intradayBacktest(Request $request)
    {
        $symbol   = strtoupper($request->symbol ?? 'NIFTY');
        $fromDate = $request->from ?? Carbon::today()->subDays(30)->toDateString();
        $toDate   = $request->to   ?? today()->toDateString();

        $symbols     = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $tradingDays = $this->tradingDaysWithData($symbol, $fromDate, $toDate);

        $trades = [];
        $equity = 100.0;
        $curve  = [['date' => $fromDate, 'equity' => $equity]];

        foreach ($tradingDays as $date) {
            $trade = $this->simulateIntraday($symbol, $date);
            if ($trade) {
                $trades[] = $trade;
                $equity   = round($equity * (1 + $trade['pnl_pct'] / 100), 2);
                $curve[]  = ['date' => $date, 'equity' => $equity];
            }
        }

        $metrics   = $this->calcMetrics($trades, $equity);
        $pageTitle = "Intraday Backtest — {$symbol}";
        return view($this->v('backtest'), compact(
            'symbols', 'symbol', 'fromDate', 'toDate', 'trades', 'metrics', 'curve', 'pageTitle'
        ))->with('strategy', 'intraday');
    }

    public function swingBacktest(Request $request)
    {
        $symbol   = strtoupper($request->symbol ?? 'NIFTY');
        $fromDate = $request->from ?? Carbon::today()->subDays(45)->toDateString();
        $toDate   = $request->to   ?? today()->toDateString();

        $symbols     = DB::table('raw_symbols')->where('status', 1)->pluck('symbol');
        $tradingDays = $this->tradingDaysWithData($symbol, $fromDate, $toDate);

        $trades = [];
        $equity = 100.0;
        $curve  = [['date' => $fromDate, 'equity' => $equity]];

        foreach ($tradingDays as $d => $date) {
            $trade = $this->simulateSwing($symbol, $date, $tradingDays, $d);
            if ($trade) {
                $trades[] = $trade;
                $equity   = round($equity * (1 + $trade['pnl_pct'] / 100), 2);
                $curve[]  = ['date' => $date, 'equity' => $equity];
            }
        }

        $metrics   = $this->calcMetrics($trades, $equity);
        $pageTitle = "Swing Backtest — {$symbol}";
        return view($this->v('backtest'), compact(
            'symbols', 'symbol', 'fromDate', 'toDate', 'trades', 'metrics', 'curve', 'pageTitle'
        ))->with('strategy', 'swing');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FIX C: Market Direction from NIFTY
    // ═════════════════════════════════════════════════════════════════════════

    private function getMarketBias(string $date, string $time): array
    {
        // Use NIFTY futures to determine overall market direction
        $niftyCandles = DB::table('raw_ohlc_futures')
            ->where('symbol', self::NIFTY_SYMBOL)
            ->where('trade_date', $date)
            ->orderBy('candle_time')
            ->get();

        if ($niftyCandles->isEmpty()) {
            return ['direction' => 'NEUTRAL', 'label' => 'NIFTY: No data', 'color' => '#8b949e'];
        }

        $first   = $niftyCandles->first();
        $current = $niftyCandles->where('candle_time', '<=', "{$date} {$time}:00")->last()
                   ?? $niftyCandles->last();

        $open    = (float)$first->open;
        $close   = (float)$current->close;
        $changePct = $open > 0 ? (($close - $open) / $open) * 100 : 0;

        // Also check if price is above/below VWAP-proxy (avg of day's candles so far)
        $dayCandles = $niftyCandles->where('candle_time', '<=', "{$date} {$time}:00");
        $avgClose   = $dayCandles->avg('close') ?? $close;

        // Direction: bullish if price up >0.2% AND above average, bearish if down >0.2%
        if ($changePct > 0.2 && $close >= $avgClose) {
            $direction = 'BULLISH';
            $label     = 'NIFTY ▲ +' . round($changePct, 2) . '%';
            $color     = '#3fb950';
        } elseif ($changePct < -0.2 && $close <= $avgClose) {
            $direction = 'BEARISH';
            $label     = 'NIFTY ▼ ' . round($changePct, 2) . '%';
            $color     = '#f85149';
        } else {
            $direction = 'NEUTRAL';
            $label     = 'NIFTY ↔ ' . round($changePct, 2) . '%';
            $color     = '#f59e0b';
        }

        return ['direction' => $direction, 'label' => $label, 'color' => $color, 'change_pct' => round($changePct, 2)];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CORE: Build complete analysis
    // ═════════════════════════════════════════════════════════════════════════

    public function buildAnalysis(string $symbol, string $date, string $time): ?array
    {
        $ct      = "{$date} {$time}:00";
        $isIndex = in_array($symbol, self::INDEX_SYMBOLS);

        $futRow = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', $ct)->orderBy('expiry_date')->first();
        if (!$futRow) return null;

        $S      = (float)$futRow->close;
        $expiry = $futRow->expiry_date;

        $opts = DB::table('raw_ohlc_options')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', $ct)->where('expiry_date', $expiry)
            ->orderBy('strike')->get();
        if ($opts->isEmpty()) return null;

        $interval = $this->strikeInterval($opts);
        $atm      = $interval > 0 ? round($S / $interval) * $interval : $S;

        [$ce, $pe] = $this->buildRows($opts, $S, $expiry, $ct, $isIndex);

        $oiSignal  = $this->oiZoneSignal($ce, $pe, $atm, $interval);
        $volSignal = $this->volumeSignal($ce, $pe, $oiSignal['bias']);
        $pcrSignal = $this->pcrSignal($opts, $symbol, $date, $ct, $expiry, $atm, $interval);
        $futSignal = $this->futuresSignal($futRow);
        $smSignal  = $this->smartMoneySignal($ce, $pe);

        $score    = $this->weightedScore($oiSignal, $volSignal, $pcrSignal, $futSignal, $smSignal);
        $ivFilter = $this->ivFilter($ce, $pe, $atm);
        $verdict  = $this->verdict($score);

        return [
            'symbol'     => $symbol,
            'date'       => $date,
            'time'       => $time,
            'underlying' => $S,
            'atm'        => $atm,
            'expiry'     => $expiry,
            'interval'   => $interval,
            'score'      => $score,
            'verdict'    => $verdict,
            'signals'    => compact('oiSignal', 'volSignal', 'pcrSignal', 'futSignal', 'smSignal'),
            'iv_filter'  => $ivFilter,
            'ce'         => $ce,
            'pe'         => $pe,
            'chain'      => $this->mergeChain($ce, $pe),
            'ce_vol'     => collect($ce)->sum('volume'),
            'pe_vol'     => collect($pe)->sum('volume'),
        ];
    }

    // ─── Per-strike rows ──────────────────────────────────────────────────────

    private function buildRows($opts, float $S, string $expiry, string $ct, bool $isIndex): array
    {
        $ce = $pe = [];
        foreach ($opts as $opt) {
            $K    = (float)$opt->strike;
            $type = $opt->option_type;
            $px   = (float)$opt->close;
            $vol  = (int)$opt->volume;
            $oi   = (int)$opt->open_interest;

            $isBadStrike = $vol < self::MIN_VOLUME || $oi < self::MIN_OI || $px < 0.5;
            $tte  = $this->calc->tte($ct, $expiry);
            $iv   = (!$isBadStrike && $tte !== null && $px > 0.5)
                    ? $this->calc->iv($type, $S, $K, $tte, $px, 0.065, $isIndex)
                    : null;
            $grk  = ($iv && $tte !== null)
                    ? $this->calc->greeks($type, $S, $K, $tte, $iv, $px, 0.065, $isIndex)
                    : null;

            $row = [
                'strike'    => $K,
                'type'      => $type,
                'open'      => (float)$opt->open,
                'close'     => $px,
                'volume'    => $vol,
                'oi'        => $oi,
                'oi_change' => (int)$opt->oi_change,
                'dist'      => (float)$opt->strike_distance,
                'iv'        => $iv ? round($iv * 100, 1) : null,
                'delta'     => $grk['delta'] ?? null,
                'theta'     => $grk['theta'] ?? null,
                'gamma'     => $grk['gamma'] ?? null,
                'fair'      => $grk['fair']  ?? null,
                'bad_data'  => $isBadStrike,
                'buildup'   => $this->buildup($px, (float)$opt->open, (int)$opt->oi_change),
            ];

            if ($type === 'CE') $ce[(string)$K] = $row;
            else                $pe[(string)$K] = $row;
        }
        return [$ce, $pe];
    }

    // ─── Signal 1: OI Zone ────────────────────────────────────────────────────

    private function oiZoneSignal(array $ce, array $pe, float $atm, float $interval): array
    {
        $zones = [];
        for ($i = -self::ZONE_DEPTH; $i <= self::ZONE_DEPTH; $i++) {
            $zones[] = round($atm + $i * $interval, 2);
        }

        $ceBull = $ceBear = $peBull = $peBear = 0;
        $ceBullOi = $ceBearOi = $peBullOi = $peBearOi = 0;

        foreach ($zones as $z) {
            $k = (string)$z;
            if (isset($ce[$k]) && !$ce[$k]['bad_data']) {
                $bt = $ce[$k]['buildup']['type'];
                if (in_array($bt, ['LONG_BUILDUP', 'SHORT_COVERING'])) {
                    $ceBull++; $ceBullOi += abs($ce[$k]['oi_change']);
                } else {
                    $ceBear++; $ceBearOi += abs($ce[$k]['oi_change']);
                }
            }
            if (isset($pe[$k]) && !$pe[$k]['bad_data']) {
                $bt = $pe[$k]['buildup']['type'];
                if (in_array($bt, ['SHORT_BUILDUP', 'LONG_UNWINDING'])) {
                    $peBull++; $peBullOi += abs($pe[$k]['oi_change']);
                } else {
                    $peBear++; $peBearOi += abs($pe[$k]['oi_change']);
                }
            }
        }

        $ceStrength = $ceBullOi - $ceBearOi;
        $peStrength = $peBullOi - $peBearOi;

        if ($peStrength > $ceStrength * 1.2)      $bias = 'BULLISH';
        elseif ($ceStrength > $peStrength * 1.2)  $bias = 'BEARISH';
        else                                       $bias = 'MIXED';

        $ceTot = max(1, $ceBull + $ceBear);
        $peTot = max(1, $peBull + $peBear);

        $netBias    = $peStrength - $ceStrength;
        $absMax     = max(1, abs($peStrength) + abs($ceStrength));
        $normalized = $netBias / $absMax;
        $raw        = max(-4, min(4, (int)round($normalized * 4)));

        return [
            'bias'        => $bias,
            'ce_bull'     => $ceBull, 'ce_bear' => $ceBear,
            'ce_pct'      => round($ceBull / $ceTot * 100),
            'pe_bull'     => $peBull, 'pe_bear' => $peBear,
            'pe_pct'      => round($peBull / $peTot * 100),
            'ce_strength' => $ceStrength,
            'pe_strength' => $peStrength,
            'raw'         => $raw,
            'label'       => $bias === 'BULLISH' ? 'Zone Bullish' : ($bias === 'BEARISH' ? 'Zone Bearish' : 'Zone Mixed'),
        ];
    }

    // ─── Signal 2: Volume trap ────────────────────────────────────────────────

    private function volumeSignal(array $ce, array $pe, string $oiBias): array
    {
        $ceVol   = collect($ce)->where('bad_data', false)->sum('volume');
        $peVol   = collect($pe)->where('bad_data', false)->sum('volume');
        $ceOiChg = collect($ce)->where('bad_data', false)->sum('oi_change');
        $peOiChg = collect($pe)->where('bad_data', false)->sum('oi_change');

        $ceValid = $ceVol > 0 && $ceOiChg > 0;
        $peValid = $peVol > 0 && $peOiChg > 0;

        $raw = 0;
        if ($oiBias === 'BULLISH') {
            if ($peValid && $peVol > $ceVol * 1.3) $raw = 2;
            elseif ($ceValid && $ceVol > $peVol)   $raw = 1;
            elseif (!$peValid && $peVol > $ceVol)  $raw = -1;
        } elseif ($oiBias === 'BEARISH') {
            if ($ceValid && $ceVol > $peVol * 1.3) $raw = -2;
            elseif ($peValid && $peVol > $ceVol)   $raw = -1;
            elseif (!$ceValid && $ceVol > $peVol)  $raw = 1;
        }
        $raw = max(-2, min(2, $raw));

        return [
            'ce_vol'   => $ceVol,
            'pe_vol'   => $peVol,
            'ce_valid' => $ceValid,
            'pe_valid' => $peValid,
            'dominant' => $ceVol > $peVol ? 'CE' : 'PE',
            'raw'      => $raw,
            'label'    => $raw > 0 ? 'Vol Confirms Bullish' : ($raw < 0 ? 'Vol Confirms Bearish' : 'Vol Neutral'),
        ];
    }

    // ─── Signal 3: PCR ───────────────────────────────────────────────────────

    private function pcrSignal($opts, string $symbol, string $date, string $ct, string $expiry, float $atm, float $interval): array
    {
        $ceOi = $peOi = $atmCeOi = $atmPeOi = 0;
        $range = $interval * 3;

        foreach ($opts as $opt) {
            $oi = (int)$opt->open_interest;
            if ($opt->option_type === 'CE') {
                $ceOi += $oi;
                if (abs((float)$opt->strike - $atm) <= $range) $atmCeOi += $oi;
            } else {
                $peOi += $oi;
                if (abs((float)$opt->strike - $atm) <= $range) $atmPeOi += $oi;
            }
        }

        $pcrTotal = $ceOi    > 0 ? round($peOi    / $ceOi,    3) : null;
        $pcrAtm   = $atmCeOi > 0 ? round($atmPeOi / $atmCeOi, 3) : null;

        $prevFut = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', '<', $ct)->orderByDesc('candle_time')->first();

        $pcrChange = 0.0;
        $pcrDir    = 'FLAT';

        if ($prevFut) {
            $prev    = DB::table('raw_ohlc_options')
                ->where('symbol', $symbol)->where('trade_date', $date)
                ->where('candle_time', $prevFut->candle_time)->where('expiry_date', $expiry)
                ->selectRaw("option_type, SUM(open_interest) as oi")
                ->groupBy('option_type')->pluck('oi', 'option_type');
            $prevCe  = (float)($prev['CE'] ?? 0);
            $prevPe  = (float)($prev['PE'] ?? 0);
            $prevPcr = $prevCe > 0 ? round($prevPe / $prevCe, 3) : null;

            if ($pcrTotal && $prevPcr) {
                $pcrChange = round($pcrTotal - $prevPcr, 3);
                $pcrDir    = $pcrChange > 0.02 ? 'RISING' : ($pcrChange < -0.02 ? 'FALLING' : 'FLAT');
            }
        }

        $raw = 0;
        if ($pcrTotal) {
            if ($pcrTotal > 1.3) $raw += 1;
            elseif ($pcrTotal < 0.7) $raw -= 1;
        }
        if ($pcrDir === 'RISING')  $raw += 1;
        if ($pcrDir === 'FALLING') $raw -= 1;
        $raw = max(-3, min(3, $raw));

        return [
            'total'     => $pcrTotal,
            'atm'       => $pcrAtm,
            'change'    => $pcrChange,
            'direction' => $pcrDir,
            'raw'       => $raw,
            'label'     => $raw > 0 ? 'PCR Bullish' : ($raw < 0 ? 'PCR Bearish' : 'PCR Neutral'),
        ];
    }

    // ─── Signal 4: Futures ───────────────────────────────────────────────────

    private function futuresSignal($futRow): array
    {
        $b   = $this->buildup($futRow->close, $futRow->open, (int)$futRow->oi_change);
        $raw = match ($b['type']) {
            'LONG_BUILDUP'   =>  2,
            'SHORT_COVERING' =>  1,
            'LONG_UNWINDING' => -1,
            'SHORT_BUILDUP'  => -2,
            default          =>  0,
        };
        return array_merge($b, ['raw' => $raw]);
    }

    // ─── Signal 5: Smart Money ────────────────────────────────────────────────

    private function smartMoneySignal(array $ce, array $pe): array
    {
        $ceChg = collect($ce)->where('bad_data', false)->sum(fn($r) => max(0, $r['oi_change']));
        $peChg = collect($pe)->where('bad_data', false)->sum(fn($r) => max(0, $r['oi_change']));
        $tot   = max(1, $ceChg + $peChg);

        $raw = 0;
        if ($peChg > $ceChg * 1.5)      $raw = 2;
        elseif ($ceChg > $peChg * 1.5)  $raw = -2;
        elseif ($peChg > $ceChg)         $raw = 1;
        elseif ($ceChg > $peChg)         $raw = -1;

        return [
            'ce_oi_chg' => $ceChg,
            'pe_oi_chg' => $peChg,
            'pe_pct'    => round($peChg / $tot * 100),
            'raw'       => $raw,
            'label'     => $raw > 0 ? 'PE OI dominant → Bullish' : ($raw < 0 ? 'CE OI dominant → Bearish' : 'Balanced'),
        ];
    }

    // ─── Weighted score ───────────────────────────────────────────────────────

    private function weightedScore(array $oi, array $vol, array $pcr, array $fut, array $sm): int
    {
        $raw = ($oi['raw']  * 2.0)
             + ($vol['raw'] * 1.5)
             + ($fut['raw'] * 1.5)
             + ($pcr['raw'] * 0.5)
             + ($sm['raw']  * 0.5);

        return max(-8, min(8, (int)round($raw * 8 / 16.5)));
    }

    // ─── IV Filter ───────────────────────────────────────────────────────────

    private function ivFilter(array $ce, array $pe, float $atm): array
    {
        $atmCe = collect($ce)->where('bad_data', false)->sortBy(fn($r) => abs($r['strike'] - $atm))->first();
        $atmPe = collect($pe)->where('bad_data', false)->sortBy(fn($r) => abs($r['strike'] - $atm))->first();

        $ivCe  = $atmCe['iv'] ?? null;
        $ivPe  = $atmPe['iv'] ?? null;
        $avgIv = ($ivCe && $ivPe) ? round(($ivCe + $ivPe) / 2, 1) : ($ivCe ?? $ivPe);

        $regime = 'NORMAL';
        if ($avgIv) {
            if ($avgIv > 30) $regime = 'HIGH';    // FIX H: raised from 25 to 30
            elseif ($avgIv < 10) $regime = 'LOW';
        }

        return [
            'iv_ce'     => $ivCe,
            'iv_pe'     => $ivPe,
            'avg'       => $avgIv,
            'regime'    => $regime,
            'avoid_buy' => $regime === 'HIGH',
            'good_buy'  => $regime === 'LOW',
            'skew'      => ($ivCe && $ivPe) ? round($ivPe - $ivCe, 1) : null,
            'delta_ce'  => $atmCe['delta'] ?? null,
            'delta_pe'  => $atmPe['delta'] ?? null,
            'theta_ce'  => $atmCe['theta'] ?? null,
        ];
    }

    // ─── Verdict ─────────────────────────────────────────────────────────────

    private function verdict(int $score): array
    {
        return match (true) {
            $score >= 6  => ['signal' => 'STRONG_BUY',  'label' => 'Strong Buy',  'side' => 'CE', 'class' => 'strong-bull', 'emoji' => '🟢🟢'],
            $score >= 4  => ['signal' => 'BUY',         'label' => 'Buy',         'side' => 'CE', 'class' => 'bull',        'emoji' => '🟢'],
            $score >= 2  => ['signal' => 'WEAK_BUY',    'label' => 'Weak Buy',    'side' => 'CE', 'class' => 'mild-bull',   'emoji' => '🟡'],
            $score <= -6 => ['signal' => 'STRONG_SELL', 'label' => 'Strong Sell', 'side' => 'PE', 'class' => 'strong-bear', 'emoji' => '🔴🔴'],
            $score <= -4 => ['signal' => 'SELL',        'label' => 'Sell',        'side' => 'PE', 'class' => 'bear',        'emoji' => '🔴'],
            $score <= -2 => ['signal' => 'WEAK_SELL',   'label' => 'Weak Sell',   'side' => 'PE', 'class' => 'mild-bear',   'emoji' => '🟠'],
            default      => ['signal' => 'NEUTRAL',     'label' => 'No Trade',    'side' => null, 'class' => 'neutral',     'emoji' => '⚪'],
        };
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DECISION LAYERS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * FIX A: Score threshold = 3 (was 5)
     * FIX B: OI MIXED = soft penalty, not hard block
     * FIX C: Market bias filter added
     * FIX H: IV HIGH = hard block only if score < 6
     * FIX I: Delta filter = 0.25 (was 0.3)
     */
    private function intradayDecision(array $a, string $time, ?array $marketBias = null, bool $preFiltered = false): array
    {
        $score   = $a['score'];
        $verdict = $a['verdict'];
        $side    = $verdict['side'];

        $t        = Carbon::parse($time);
        $inWindow = $preFiltered ? true : $t->between(Carbon::parse(self::INTRADAY_START), Carbon::parse(self::INTRADAY_END));

        $atmRows = $side ? ($side === 'CE' ? $a['ce'] : $a['pe']) : [];
        $atmRow  = collect($atmRows)->where('bad_data', false)->sortBy(fn($r) => abs($r['dist']))->first();
        $premium = $atmRow ? $atmRow['close'] : null;
        $delta   = $atmRow['delta'] ?? null;

        $blocks = [];
        $active = true;

        if (!$inWindow) {
            $blocks[] = ['type' => 'window', 'msg' => 'Outside intraday window (9:30–11:00)'];
            $active   = false;
        }

        // FIX A: Lowered threshold to 3
        if (abs($score) < self::SCORE_TRADE) {
            $blocks[] = ['type' => 'score', 'msg' => "Score {$score} too weak — need ≥±3"];
            $active   = false;
        }

        // FIX C: Market direction filter — CE only in bullish, PE only in bearish, neutral = allow both
        if ($marketBias && $side) {
            $mDir = $marketBias['direction'];
            if ($mDir === 'BEARISH' && $side === 'CE') {
                $blocks[] = ['type' => 'market', 'msg' => "Market BEARISH — avoid CE buys ({$marketBias['label']})"];
                $active   = false;
            }
            if ($mDir === 'BULLISH' && $side === 'PE') {
                $blocks[] = ['type' => 'market', 'msg' => "Market BULLISH — avoid PE buys ({$marketBias['label']})"];
                $active   = false;
            }
        }

        // FIX H: IV HIGH = hard block only if score < 6 (strong signals can override)
        if ($a['iv_filter']['avoid_buy'] && abs($score) < 6) {
            $blocks[] = ['type' => 'iv', 'msg' => "IV HIGH ({$a['iv_filter']['avg']}%) — avoid buying (score < 6)"];
            $active   = false;
        }

        // FIX B: OI MIXED = soft penalty (already reduced score), NOT hard block
        // Only block if MIXED + score is barely above threshold
        if ($a['signals']['oiSignal']['bias'] === 'MIXED' && abs($score) < 4) {
            $blocks[] = ['type' => 'oi', 'msg' => 'OI zone MIXED + weak score — no clear direction'];
            $active   = false;
        }

        // FIX I: Delta filter relaxed to 0.25
        if ($delta !== null) {
            if ($side === 'CE' && $delta < 0.25) {
                $blocks[] = ['type' => 'delta', 'msg' => "CE delta {$delta} too low — option too OTM"];
                $active   = false;
            }
            if ($side === 'PE' && $delta > -0.25) {
                $blocks[] = ['type' => 'delta', 'msg' => "PE delta {$delta} too low — option too OTM"];
                $active   = false;
            }
        }

        if ($premium !== null) {
            if ($premium < self::MIN_PREMIUM) {
                $blocks[] = ['type' => 'premium', 'msg' => "Premium ₹{$premium} too low — illiquid"];
                $active   = false;
            }
            if ($premium > self::MAX_PREMIUM) {
                $blocks[] = ['type' => 'premium', 'msg' => "Premium ₹{$premium} too high"];
                $active   = false;
            }
        }

        $sl  = $premium ? round($premium * (1 - self::SL_PCT_INTRADAY  / 100), 2) : null;
        $tgt = $premium ? round($premium * (1 + self::TGT_PCT_INTRADAY / 100), 2) : null;

        return [
            'active'       => $active,
            'side'         => $side,
            'strategy'     => 'intraday',
            'score'        => $score,
            'confidence'   => $this->confidence($score, $blocks, $a),
            'premium'      => $premium,
            'delta'        => $delta,
            'sl'           => $sl,
            'tgt'          => $tgt,
            'sl_pct'       => self::SL_PCT_INTRADAY,
            'tgt_pct'      => self::TGT_PCT_INTRADAY,
            'entry_candle' => $t->copy()->addMinutes(15)->format('H:i'),
            'force_exit'   => self::INTRADAY_FORCE_EXIT,
            'blocks'       => $blocks,
            'reasons'      => $this->buildReasons($a, $side),
            'in_window'    => $inWindow,
        ];
    }

    private function swingDecision(array $a, string $time, string $date, ?array $marketBias = null, bool $preFiltered = false): array
    {
        $score   = $a['score'];
        $verdict = $a['verdict'];
        $side    = $verdict['side'];

        $t        = Carbon::parse($time);
        $inWindow = $preFiltered ? true : $t->between(Carbon::parse(self::SWING_START), Carbon::parse(self::SWING_END));

        $atmRows = $side ? ($side === 'CE' ? $a['ce'] : $a['pe']) : [];
        $atmRow  = collect($atmRows)->where('bad_data', false)->sortBy(fn($r) => abs($r['dist']))->first();
        $premium = $atmRow ? $atmRow['close'] : null;
        $delta   = $atmRow['delta'] ?? null;

        $entryPremium = $this->getSwingEntryPremium($a['symbol'], $date, $a['expiry'], $side);

        $blocks = [];
        $active = true;

        if (!$inWindow) {
            $blocks[] = ['type' => 'window', 'msg' => 'Check signal 13:30–14:30 for swing'];
            $active   = false;
        }

        // FIX A: Same threshold
        if (abs($score) < self::SCORE_TRADE) {
            $blocks[] = ['type' => 'score', 'msg' => "Score {$score} too weak — need ≥±3 for swing"];
            $active   = false;
        }

        // FIX C: Market direction
        if ($marketBias && $side) {
            $mDir = $marketBias['direction'];
            if ($mDir === 'BEARISH' && $side === 'CE') {
                $blocks[] = ['type' => 'market', 'msg' => "Market BEARISH — avoid overnight CE ({$marketBias['label']})"];
                $active   = false;
            }
            if ($mDir === 'BULLISH' && $side === 'PE') {
                $blocks[] = ['type' => 'market', 'msg' => "Market BULLISH — avoid overnight PE ({$marketBias['label']})"];
                $active   = false;
            }
        }

        // FIX H: IV HIGH hard block only if weak score (overnight risk)
        if ($a['iv_filter']['avoid_buy'] && abs($score) < 6) {
            $blocks[] = ['type' => 'iv', 'msg' => "IV HIGH ({$a['iv_filter']['avg']}%) — overnight risk elevated"];
            $active   = false;
        }

        // FIX B: OI MIXED soft check
        if ($a['signals']['oiSignal']['bias'] === 'MIXED' && abs($score) < 4) {
            $blocks[] = ['type' => 'oi', 'msg' => 'OI zone MIXED + weak score — skip overnight'];
            $active   = false;
        }

        // FIX I: Delta
        if ($delta !== null && $side === 'CE' && $delta < 0.25) {
            $blocks[] = ['type' => 'delta', 'msg' => "CE delta {$delta} too low for swing"];
            $active   = false;
        }
        if ($delta !== null && $side === 'PE' && $delta > -0.25) {
            $blocks[] = ['type' => 'delta', 'msg' => "PE delta {$delta} too low for swing"];
            $active   = false;
        }

        $ep  = $entryPremium ?? $premium;
        $sl  = $ep ? round($ep * (1 - self::SL_PCT_SWING  / 100), 2) : null;
        $tgt = $ep ? round($ep * (1 + self::TGT_PCT_SWING / 100), 2) : null;

        return [
            'active'        => $active,
            'side'          => $side,
            'strategy'      => 'swing',
            'score'         => $score,
            'confidence'    => $this->confidence($score, $blocks, $a),
            'premium'       => $premium,
            'entry_premium' => $entryPremium,
            'delta'         => $delta,
            'sl'            => $sl,
            'tgt'           => $tgt,
            'sl_pct'        => self::SL_PCT_SWING,
            'tgt_pct'       => self::TGT_PCT_SWING,
            'entry_time'    => self::SWING_ENTRY_TIME,
            'exit_time'     => 'Next day 9:30–15:00',
            'blocks'        => $blocks,
            'reasons'       => $this->buildReasons($a, $side),
            'in_window'     => $inWindow,
        ];
    }

    // FIX D: Entry at 14:30 candle (not 15:00)
    private function getSwingEntryPremium(string $symbol, string $date, string $expiry, ?string $side): ?float
    {
        if (!$side) return null;
        $row = DB::table('raw_ohlc_options')
            ->where('symbol',      $symbol)
            ->where('trade_date',  $date)
            ->where('candle_time', "{$date} " . self::SWING_ENTRY_TIME . ":00")
            ->where('expiry_date', $expiry)
            ->where('option_type', $side)
            ->orderByRaw('ABS(strike_distance)')->first();
        return $row ? (float)$row->close : null;
    }

    private function buildReasons(array $a, ?string $side): array
    {
        $reasons = [];
        $avoid   = [];

        if ($a['signals']['oiSignal']['bias'] !== 'MIXED')
            $reasons[] = "OI zone: {$a['signals']['oiSignal']['label']}";
        else
            $reasons[] = "OI zone: Mixed (weak)";

        if ($a['signals']['pcrSignal']['raw'] !== 0)
            $reasons[] = "PCR {$a['signals']['pcrSignal']['direction']} ({$a['signals']['pcrSignal']['total']})";

        if (in_array($a['signals']['futSignal']['type'], ['LONG_BUILDUP', 'SHORT_BUILDUP']))
            $reasons[] = "FUT: {$a['signals']['futSignal']['label']}";

        if (abs($a['signals']['smSignal']['raw']) >= 1)
            $reasons[] = $a['signals']['smSignal']['label'];

        if ($a['signals']['volSignal']['raw'] !== 0)
            $reasons[] = $a['signals']['volSignal']['label'];

        if ($a['iv_filter']['avoid_buy'])
            $avoid[] = "IV too high ({$a['iv_filter']['avg']}%)";

        if ($a['signals']['oiSignal']['bias'] === 'MIXED')
            $avoid[] = "OI zone mixed";

        return ['reasons' => $reasons, 'avoid' => $avoid];
    }

    private function confidence(int $score, array $blocks, array $a): array
    {
        if (!empty($blocks)) return ['level' => 'AVOID', 'label' => 'Avoid', 'class' => 'neutral', 'pct' => 0];

        $abs = abs($score);
        $isClean = $a['signals']['oiSignal']['bias'] !== 'MIXED' && !$a['iv_filter']['avoid_buy'];

        if ($abs >= 6 && $isClean)
            return ['level' => 'HIGH',   'label' => 'High Confidence',   'class' => 'strong-bull', 'pct' => 90];
        if ($abs >= 5)
            return ['level' => 'HIGH',   'label' => 'High Confidence',   'class' => 'strong-bull', 'pct' => 80];
        if ($abs >= 4)
            return ['level' => 'MEDIUM', 'label' => 'Medium Confidence', 'class' => 'bull',        'pct' => 65];
        if ($abs >= 3)
            return ['level' => 'LOW',    'label' => 'Low Confidence',    'class' => 'mild-bull',   'pct' => 45];
        return   ['level' => 'VERY_LOW','label' => 'Very Low',          'class' => 'neutral',     'pct' => 20];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // BACKTEST SIMULATIONS
    // ═════════════════════════════════════════════════════════════════════════

    private function simulateIntraday(string $symbol, string $date): ?array
    {
        $marketBias = $this->getMarketBias($date, self::INTRADAY_END);

        $candles = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->whereBetween('candle_time', [
                "{$date} " . self::INTRADAY_START . ":00",
                "{$date} " . self::INTRADAY_END   . ":00",
            ])->orderBy('candle_time')->get();

        $bestScore    = 0;
        $bestCandle   = null;
        $bestAnalysis = null;

        foreach ($candles as $c) {
            $t        = Carbon::parse($c->candle_time)->format('H:i');
            $a        = $this->buildAnalysis($symbol, $date, $t);
            if (!$a) continue;
            $decision = $this->intradayDecision($a, $t, $marketBias);
            if ($decision['active'] && abs($a['score']) > abs($bestScore)) {
                $bestScore    = $a['score'];
                $bestCandle   = $c;
                $bestAnalysis = $a;
            }
        }

        if (!$bestAnalysis) return null;

        $side        = $bestScore > 0 ? 'CE' : 'PE';
        $entryCandle = Carbon::parse($bestCandle->candle_time)->addMinutes(15)->toDateTimeString();

        $confirmFut = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', $entryCandle)->first();

        if (!$confirmFut) return null;
        if ($bestScore > 0 && $confirmFut->high <= $bestCandle->high) return null;
        if ($bestScore < 0 && $confirmFut->low  >= $bestCandle->low)  return null;

        $entryOpt = DB::table('raw_ohlc_options')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', $entryCandle)
            ->where('expiry_date', $bestAnalysis['expiry'])
            ->where('option_type', $side)
            ->orderByRaw('ABS(strike_distance)')->first();

        if (!$entryOpt || (float)$entryOpt->open <= 0) return null;

        $entryPx = (float)$entryOpt->open * 1.01;
        $slPx    = $entryPx * (1 - self::SL_PCT_INTRADAY  / 100);
        $tgtPx   = $entryPx * (1 + self::TGT_PCT_INTRADAY / 100);

        $exitOpts = DB::table('raw_ohlc_options')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', '>', $entryCandle)
            ->where('expiry_date', $bestAnalysis['expiry'])
            ->where('option_type', $side)
            ->where('strike', $entryOpt->strike)
            ->where('candle_time', '<=', "{$date} 15:00:00") // FIX: exit at 15:00
            ->orderBy('candle_time')->get();

        $exitPx     = $entryPx;
        $exitTime   = '15:00';
        $exitReason = 'time';

        foreach ($exitOpts as $eo) {
            if ($eo->low  <= $slPx)  { $exitPx = $slPx;  $exitTime = Carbon::parse($eo->candle_time)->format('H:i'); $exitReason = 'sl';  break; }
            if ($eo->high >= $tgtPx) { $exitPx = $tgtPx; $exitTime = Carbon::parse($eo->candle_time)->format('H:i'); $exitReason = 'tgt'; break; }
            $exitPx = (float)$eo->close;
        }

        $exitPx *= ($exitReason === 'sl' ? 1.0 : 0.99);
        $pnlPct  = round(($exitPx - $entryPx) / $entryPx * 100, 2);

        return [
            'date'        => $date,
            'strategy'    => 'intraday',
            'signal_time' => Carbon::parse($bestCandle->candle_time)->format('H:i'),
            'score'       => $bestScore,
            'verdict'     => $bestAnalysis['verdict']['label'],
            'side'        => $side,
            'strike'      => $entryOpt->strike,
            'entry_px'    => round($entryPx, 2),
            'exit_px'     => round($exitPx, 2),
            'exit_time'   => $exitTime,
            'exit_reason' => $exitReason,
            'pnl_pct'     => $pnlPct,
            'win'         => $pnlPct > 0,
        ];
    }

    private function simulateSwing(string $symbol, string $date, array $tradingDays, int $idx): ?array
    {
        $marketBias = $this->getMarketBias($date, self::SWING_END);

        $candles = DB::table('raw_ohlc_futures')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->whereBetween('candle_time', [
                "{$date} " . self::SWING_START . ":00",
                "{$date} " . self::SWING_END   . ":00",
            ])->orderBy('candle_time')->get();

        $bestScore    = 0;
        $bestAnalysis = null;
        $prevScore    = 0;

        foreach ($candles as $c) {
            $t        = Carbon::parse($c->candle_time)->format('H:i');
            $a        = $this->buildAnalysis($symbol, $date, $t);
            if (!$a) continue;
            $decision = $this->swingDecision($a, $t, $date, $marketBias);
            if ($decision['active'] && abs($a['score']) >= abs($prevScore)) {
                $bestScore    = $a['score'];
                $bestAnalysis = $a;
            }
            $prevScore = $a['score'];
        }

        if (!$bestAnalysis) return null;

        $side = $bestScore > 0 ? 'CE' : 'PE';

        // FIX D: entry at 14:30
        $entryOpt = DB::table('raw_ohlc_options')
            ->where('symbol', $symbol)->where('trade_date', $date)
            ->where('candle_time', "{$date} " . self::SWING_ENTRY_TIME . ":00")
            ->where('expiry_date', $bestAnalysis['expiry'])
            ->where('option_type', $side)
            ->orderByRaw('ABS(strike_distance)')->first();

        if (!$entryOpt || (float)$entryOpt->close <= 0) return null;

        $entryPx = (float)$entryOpt->close * 1.01;
        $slPx    = $entryPx * (1 - self::SL_PCT_SWING  / 100);
        $tgtPx   = $entryPx * (1 + self::TGT_PCT_SWING / 100);

        $nextDay = $tradingDays[$idx + 1] ?? null;
        if (!$nextDay) return null;

        // FIX E: Exit 9:15 to 15:00 next day
        $exitOpts = DB::table('raw_ohlc_options')
            ->where('symbol', $symbol)->where('trade_date', $nextDay)
            ->whereBetween('candle_time', ["{$nextDay} 09:15:00", "{$nextDay} 15:00:00"])
            ->where('expiry_date', $bestAnalysis['expiry'])
            ->where('option_type', $side)
            ->where('strike', $entryOpt->strike)
            ->orderBy('candle_time')->get();

        $exitPx     = $entryPx;
        $exitTime   = '15:00';
        $exitReason = 'time';

        foreach ($exitOpts as $eo) {
            if ($eo->low  <= $slPx)  { $exitPx = $slPx;  $exitTime = Carbon::parse($eo->candle_time)->format('H:i'); $exitReason = 'sl';  break; }
            if ($eo->high >= $tgtPx) { $exitPx = $tgtPx; $exitTime = Carbon::parse($eo->candle_time)->format('H:i'); $exitReason = 'tgt'; break; }
            $exitPx = (float)$eo->close;
        }

        $exitPx *= ($exitReason === 'sl' ? 1.0 : 0.99);
        $pnlPct  = round(($exitPx - $entryPx) / $entryPx * 100, 2);

        return [
            'date'        => $date,
            'strategy'    => 'swing',
            'signal_time' => self::SWING_START . '–' . self::SWING_END,
            'score'       => $bestScore,
            'verdict'     => $bestAnalysis['verdict']['label'],
            'side'        => $side,
            'strike'      => $entryOpt->strike,
            'entry_px'    => round($entryPx, 2),
            'exit_date'   => $nextDay,
            'exit_px'     => round($exitPx, 2),
            'exit_time'   => $exitTime,
            'exit_reason' => $exitReason,
            'pnl_pct'     => $pnlPct,
            'win'         => $pnlPct > 0,
        ];
    }

    private function calcMetrics(array $trades, float $finalEquity): array
    {
        if (empty($trades)) return [
            'total' => 0, 'wins' => 0, 'losses' => 0, 'win_rate' => 0,
            'avg_win' => 0, 'avg_loss' => 0, 'net_pnl' => 0, 'max_dd' => 0, 'profit_factor' => 0,
        ];

        $wins    = array_filter($trades, fn($t) => $t['win']);
        $losses  = array_filter($trades, fn($t) => !$t['win']);
        $winPnl  = array_sum(array_column(array_filter($trades, fn($t) => $t['pnl_pct'] > 0), 'pnl_pct'));
        $lossPnl = abs(array_sum(array_column(array_filter($trades, fn($t) => $t['pnl_pct'] < 0), 'pnl_pct')));

        $peak = 100.0; $dd = 0.0; $eq = 100.0;
        foreach ($trades as $t) {
            $eq   = $eq * (1 + $t['pnl_pct'] / 100);
            $peak = max($peak, $eq);
            $dd   = max($dd, ($peak - $eq) / $peak * 100);
        }

        return [
            'total'         => count($trades),
            'wins'          => count($wins),
            'losses'        => count($losses),
            'win_rate'      => round(count($wins) / count($trades) * 100, 1),
            'avg_win'       => count($wins)   > 0 ? round($winPnl  / count($wins),   1) : 0,
            'avg_loss'      => count($losses) > 0 ? round($lossPnl / count($losses), 1) : 0,
            'net_pnl'       => round($finalEquity - 100, 2),
            'max_dd'        => round($dd, 1),
            'profit_factor' => $lossPnl > 0 ? round($winPnl / $lossPnl, 2) : ($winPnl > 0 ? 99 : 0),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════════════════════════════════

    private function buildup(float $close, float $open, int $oiChange): array
    {
        $up   = $close >= $open;
        $oiUp = $oiChange > 0;
        return match (true) {
            $up  && $oiUp  => ['type' => 'LONG_BUILDUP',   'label' => 'Long Buildup',   'class' => 'bull'],
            !$up && $oiUp  => ['type' => 'SHORT_BUILDUP',  'label' => 'Short Buildup',  'class' => 'bear'],
            $up  && !$oiUp => ['type' => 'SHORT_COVERING', 'label' => 'Short Covering', 'class' => 'mild-bull'],
            default        => ['type' => 'LONG_UNWINDING', 'label' => 'Long Unwinding', 'class' => 'mild-bear'],
        };
    }

    private function strikeInterval($opts): float
    {
        $s = $opts->where('option_type', 'CE')->pluck('strike')
                  ->map(fn($x) => (float)$x)->unique()->sort()->values();
        if ($s->count() < 2) return 50.0;
        $gaps = range(1, $s->count() - 1);
        return (float)min(array_map(fn($i) => $s[$i] - $s[$i - 1], $gaps));
    }

    private function mergeChain(array $ce, array $pe): array
    {
        $strikes = array_unique(array_merge(array_keys($ce), array_keys($pe)));
        sort($strikes, SORT_NUMERIC);
        return array_map(fn($s) => ['strike' => $s, 'ce' => $ce[(string)$s] ?? null, 'pe' => $pe[(string)$s] ?? null], $strikes);
    }

    private function tradingDaysWithData(string $symbol, string $from, string $to): array
    {
        return DB::table('raw_ohlc_futures')->where('symbol', $symbol)
            ->whereBetween('trade_date', [$from, $to])
            ->distinct()->orderBy('trade_date')->pluck('trade_date')->toArray();
    }

    private function v(string $name): string
    {
        return 'templates.basic.user.options-new.' . $name;
    }
}