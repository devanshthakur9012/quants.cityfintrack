<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\FutOhlAutoConfig;
use App\Models\FutOhlAutoOrder;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * FutOhlAutoTradingHelper
 *
 * Signal: 9:15 FUT candle Open=High → BUY PE | Open=Low → BUY CE
 * Source: option_ohlc_data (same table as FutOpenHighLowController)
 * Config: fut_ohl_auto_configs
 * Orders: fut_ohl_auto_orders
 *
 * Runs at 9:20 AM IST after the 9:15 candle is collected.
 * Can also be triggered manually from the config page button.
 */
class FutOhlAutoTradingHelper
{
    // NFO strike intervals (same as PECEAutoTradingHelper)
    const STRIKE_INTERVALS = [
        'NIFTY'       => 100, 'BANKNIFTY'  => 100, 'FINNIFTY'   => 50,  'MIDCPNIFTY' => 25,
        'AXISBANK'    => 10,  'ICICIBANK'  => 10,  'INDUSINDBK' => 10,  'BHARTIARTL' => 20,
        'SHRIRAMFIN'  => 10,  'LTF'        => 5,   'PAYTM'      => 20,  'POLICYBZR'  => 20,
        'BAJAJFINSV'  => 20,  'INFY'       => 20,  'TATAELXSI'  => 50,  'TATATECH'   => 10,
        'HAVELLS'     => 20,  'TITAN'      => 20,  'ASIANPAINT' => 20,  'TATACONSUM' => 10,
        'VOLTAS'      => 20,  'AUROPHARMA' => 10,  'LAURUSLABS' => 10,  'SRF'        => 20,
        'JSWSTEEL'    => 10,  'LT'         => 20,  'BHEL'       => 5,   'ADANIPORTS' => 20,
        'HAL'         => 50,  'BDL'        => 20,  'MCX'        => 20,  'BSE'        => 50,
        'CDSL'        => 20,  'LICHSGFIN'  => 5,   'DELHIVERY'  => 10,  'BHARATFORG' => 20,
        'PGEL'        => 10,  'TMPV'       => 5,   'HINDALCO'   => 10,  'VEDL'       => 10,
        'DRREDDY'     => 50,  'HEROMOTOCO' => 20,  'AMBUJACEM'  => 5,   'FORTIS'     => 5,
        'UPL'         => 10,  'M&M'        => 20,  'NATIONALUM' => 5,   'BPCL'       => 10,
        'ETERNAL'     => 10,  'SBIN'       => 10,  'VBL'        => 20,  'BAJFINANCE' => 50,
        'TCS'         => 50,  'COFORGE'    => 50,  'EICHERMOT'  => 50,  'ABCCAPITAL' => 10,
    ];

    const FREEZE_LIMITS = [
        'NIFTY' => 18, 'BANKNIFTY' => 20, 'FINNIFTY' => 24, 'MIDCPNIFTY' => 24,
    ];

    private array $kiteInstances = [];

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    /**
     * Main entry: detect 9:15 signals and create order records.
     * Called automatically at 9:20 AM by cron, or manually via config page button.
     *
     * @param string|null $testDate  Override today (Y-m-d) for backtesting
     */
    public function processSignals(?string $testDate = null): array
    {
        $summary = ['detected' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            Log::info('=== FUT OHL: Starting Open=High/Low Signal Detection ===');

            $processingDate = $testDate
                ? Carbon::parse($testDate . ' 09:20:00', 'Asia/Kolkata')
                : Carbon::now('Asia/Kolkata');

            $currentDate = $processingDate->format('Y-m-d');
            $mode        = $testDate ? 'TEST' : 'LIVE';

            Log::info("FUT OHL {$mode} | Time: " . $processingDate->format('Y-m-d H:i:s'));

            $configs = FutOhlAutoConfig::where('status', true)->get();

            if ($configs->isEmpty()) {
                Log::info('FUT OHL: No active configs');
                return $summary;
            }

            // Resolve instrument series (nearest NFO expiry)
            $instrumentSeries = $this->resolveInstrumentSeries($currentDate);
            if (!$instrumentSeries) {
                Log::warning("FUT OHL: No instrument series found for {$currentDate}");
                return $summary;
            }

            Log::info("FUT OHL: {$configs->count()} config(s) | Series: {$instrumentSeries} | Date: {$currentDate}");

            // Fetch 9:15 FUT candles for today
            $signals = $this->detect915Signals($currentDate, $instrumentSeries);

            if (empty($signals)) {
                Log::warning("FUT OHL: No qualifying 9:15 signals for {$currentDate}");
                return $summary;
            }

            $summary['detected'] = count($signals);
            Log::info("FUT OHL: {$summary['detected']} qualifying signal(s) found");

            foreach ($configs as $config) {
                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("FUT OHL Config {$config->id}: invalid broker token — skip");
                    continue;
                }
                $this->ensureKiteInstance($broker);

                foreach ($signals as $signal) {
                    $result = $this->createOrderRecord(
                        $config, $signal, $broker, $currentDate, $instrumentSeries
                    );
                    if ($result === 'created')  $summary['created']++;
                    elseif ($result === 'skip')  $summary['skipped']++;
                    else                         $summary['errors']++;
                }
            }

            Log::info("FUT OHL: Done — Detected:{$summary['detected']} Created:{$summary['created']} Skipped:{$summary['skipped']} Errors:{$summary['errors']}");

        } catch (\Throwable $e) {
            Log::error('FUT OHL processSignals: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $summary['errors']++;
        }

        return $summary;
    }

    /**
     * Place all pending (is_order_placed=false) orders via KiteConnect.
     */
    public function placeOrders(?string $testDate = null): array
    {
        $summary = ['placed' => 0, 'failed' => 0];

        try {
            Log::info('=== FUT OHL: Starting Order Placement ===');

            $pendingOrders = FutOhlAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', fn($q) => $q->where('status', true))
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('FUT OHL: No pending orders');
                return $summary;
            }

            Log::info("FUT OHL: {$pendingOrders->count()} pending orders");

            foreach ($pendingOrders->groupBy('broker_api_id') as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("FUT OHL Broker {$brokerId}: invalid token — skip");
                    continue;
                }
                $this->ensureKiteInstance($broker);

                foreach ($orders as $order) {
                    $ok = $this->placeOrder($order);
                    $ok ? $summary['placed']++ : $summary['failed']++;
                }
            }

            Log::info("FUT OHL Orders: Placed:{$summary['placed']} Failed:{$summary['failed']}");

        } catch (\Throwable $e) {
            Log::error('FUT OHL placeOrders: ' . $e->getMessage());
        }

        return $summary;
    }

    // =========================================================
    //  SIGNAL DETECTION — 9:15 CANDLE
    // =========================================================

    /**
     * Fetch all FUT 9:15 candles for $currentDate and return qualifying
     * Open=High / Open=Low signals.
     * tolerance is evaluated PER config, so we return raw candle data here
     * and filter per config in createOrderRecord().
     *
     * Returns: array of ['symbol', 'trading_symbol', 'open', 'high', 'low', 'close', 'token']
     */
    private function detect915Signals(string $currentDate, string $instrumentSeries): array
    {
        $candles = OptionOhlcData::whereDate('trade_date', $currentDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '09:15:00'")
            ->where('is_missing', 0)
            ->where(function ($q) use ($instrumentSeries) {
                $q->whereDate('expiry_date', $instrumentSeries)
                  ->orWhereNull('expiry_date');
            })
            ->select(['base_symbol', 'trading_symbol', 'instrument_token', 'open', 'high', 'low', 'close'])
            ->get();

        if ($candles->isEmpty()) {
            Log::warning("FUT OHL: No 9:15 FUT candles for {$currentDate} series {$instrumentSeries}");
            return [];
        }

        $result = [];
        foreach ($candles as $c) {
            $result[] = [
                'symbol'         => $c->base_symbol,
                'trading_symbol' => $c->trading_symbol,
                'token'          => $c->instrument_token,
                'open'           => (float)$c->open,
                'high'           => (float)$c->high,
                'low'            => (float)$c->low,
                'close'          => (float)$c->close,
            ];
        }

        Log::info("FUT OHL: Fetched " . count($result) . " FUT 9:15 candle(s) for {$currentDate}");
        return $result;
    }

    // =========================================================
    //  CREATE ORDER RECORD PER CONFIG + SIGNAL
    // =========================================================

    private function createOrderRecord(
        FutOhlAutoConfig $config,
        array $candle,
        BrokerApi $broker,
        string $currentDate,
        string $instrumentSeries
    ): string {
        try {
            $symbol    = $candle['symbol'];
            $open      = $candle['open'];
            $high      = $candle['high'];
            $low       = $candle['low'];
            $tolerance = (float)$config->tolerance;

            $diffHigh = abs($open - $high);
            $diffLow  = abs($open - $low);

            $isOpenHigh = $diffHigh <= $tolerance;
            $isOpenLow  = $diffLow  <= $tolerance;

            if (!$isOpenHigh && !$isOpenLow) {
                Log::debug("FUT OHL {$symbol}: open={$open} high={$high} low={$low} diff_h={$diffHigh} diff_l={$diffLow} — no signal within tol={$tolerance}");
                return 'skip';
            }

            // Determine signal type — if both trigger (rare edge), Open=High takes priority
            $signalType = $isOpenHigh ? 'OPEN=HIGH' : 'OPEN=LOW';
            $optionType = $config->resolveOptionType($signalType);
            $tradeAction = "BUY {$optionType}";
            $quantity   = $config->getQuantityForType($optionType);

            if ($quantity <= 0) {
                Log::info("FUT OHL {$symbol}: {$signalType} qty=0 for {$optionType} — skip");
                return 'skip';
            }

            // Duplicate check: one order per symbol per date per config
            $exists = FutOhlAutoOrder::where('config_id', $config->id)
                ->where('symbol', $symbol)
                ->where('signal_date', $currentDate)
                ->where('status', true)
                ->exists();

            if ($exists) {
                Log::debug("FUT OHL {$symbol}: duplicate for config {$config->id} — skip");
                return 'skip';
            }

            // ATM option lookup
            $optionDetails = $this->getATMOption(
                $broker, $symbol, $optionType, $candle['close'], $config, $instrumentSeries
            );

            if (!$optionDetails) {
                Log::error("FUT OHL {$symbol}: no ATM option found type={$optionType} series={$instrumentSeries}");
                return 'error';
            }

            if ($optionDetails['ltp'] <= 0) {
                Log::error("FUT OHL {$symbol}: LTP=0 for {$optionDetails['symbol']} — skip");
                return 'error';
            }

            $modeLabel = $config->shouldReverseSignal() ? 'OPPOSITE' : 'ALIGN';
            $reason = sprintf(
                "FUT OHL | %s | %s | Mode:%s | BUY %s | Open:%.2f | High:%.2f | Low:%.2f | DiffH:%.2f | DiffL:%.2f | Tol:%.2f | Series:%s | Qty:%d",
                $signalType, $symbol, $modeLabel, $optionType,
                $open, $high, $low, $diffHigh, $diffLow, $tolerance,
                $instrumentSeries, $quantity
            );

            $order = FutOhlAutoOrder::create([
                'user_id'            => $config->user_id,
                'config_id'          => $config->id,
                'broker_api_id'      => $broker->id,
                'symbol'             => $symbol,
                'trading_symbol'     => $candle['trading_symbol'],
                'series_expiry'      => $instrumentSeries,
                'signal_type'        => $signalType,
                'trade_action'       => $tradeAction,
                'tolerance_used'     => $tolerance,
                'open_price'         => $open,
                'high_915'           => $high,
                'low_915'            => $low,
                'spot_price'         => $candle['close'],
                'signal_detected_at' => Carbon::parse($currentDate . ' 09:15:00', 'Asia/Kolkata'),
                'signal_date'        => $currentDate,
                'option_symbol'      => $optionDetails['symbol'],
                'option_token'       => $optionDetails['token'],
                'option_type'        => $optionType,
                'strike_price'       => $optionDetails['strike'],
                'order_type'         => $config->order_type,
                'product'            => $config->product,
                'quantity'           => $quantity,
                'entry_price'        => $optionDetails['ltp'],
                'current_price'      => $optionDetails['ltp'],
                'is_order_placed'    => false,
                'status'             => true,
            ]);

            Log::info("FUT OHL Order #{$order->id} created | {$symbol} | {$signalType} | {$tradeAction} | {$optionDetails['symbol']} | Strike:{$optionDetails['strike']} | LTP:{$optionDetails['ltp']} | Qty:{$quantity}");
            return 'created';

        } catch (\Throwable $e) {
            Log::error("FUT OHL createOrderRecord {$candle['symbol']}: " . $e->getMessage());
            return 'error';
        }
    }

    // =========================================================
    //  ATM OPTION LOOKUP (NFO)
    //  CE → ATM+1 | PE → ATM-1
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $baseSymbol,
        string $optionType,
        float $futurePrice,
        FutOhlAutoConfig $config,
        string $instrumentSeries
    ): ?array {
        try {
            $interval  = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
            $atmStrike = round($futurePrice / $interval) * $interval;

            // CE → ATM+1 (OTM call) | PE → ATM-1 (OTM put)
            $atmStrike = $optionType === 'CE'
                ? $atmStrike + $interval
                : $atmStrike - $interval;

            $allExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', '>=', $instrumentSeries)
                ->distinct()
                ->orderBy('expiry', 'ASC')
                ->pluck('expiry')
                ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            if ($allExpiries->isEmpty()) {
                Log::warning("FUT OHL ATM {$baseSymbol}: no NFO expiries >= {$instrumentSeries}");
                return null;
            }

            $targetExpiry = $config->useNextSeries()
                ? ($allExpiries->get(1) ?? $allExpiries->get(0))
                : $allExpiries->get(0);

            // Exact strike
            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $targetExpiry)
                ->first();

            // Nearest strike fallback
            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', $targetExpiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if (!$option) {
                Log::warning("FUT OHL ATM {$baseSymbol}: no option strike~{$atmStrike} expiry={$targetExpiry}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("FUT OHL ATM {$baseSymbol}: {$option->trading_symbol} | {$optionType} | strike={$option->strike} | expiry={$targetExpiry} | LTP={$ltp}");

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
            ];

        } catch (\Throwable $e) {
            Log::error("FUT OHL ATM {$baseSymbol}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    //  ORDER PLACEMENT
    // =========================================================

    private function placeOrder(FutOhlAutoOrder $order): bool
    {
        try {
            Log::info("FUT OHL PLACE: {$order->option_symbol}");

            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, 'Invalid broker token');
                return false;
            }

            $this->ensureKiteInstance($broker);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            if (!$instrument) {
                $instrument = ZerodhaInstrument::where('trading_symbol', $order->option_symbol)
                    ->where('exchange', 'NFO')
                    ->first();
            }

            if (!$instrument) {
                $this->saveFailedOrder($order, "Instrument not found: {$order->option_symbol}");
                return false;
            }

            $this->placeKiteOrder($order, $order->quantity, $instrument, $this->kiteInstances[$broker->id]);

            $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);
            Log::info("FUT OHL ORDER Done: #{$order->id} | {$order->option_symbol}");
            return true;

        } catch (\Throwable $e) {
            Log::error("FUT OHL PLACE {$order->option_symbol}: " . $e->getMessage());
            $this->saveFailedOrder($order, $e->getMessage());
            return false;
        }
    }

    private function placeKiteOrder(FutOhlAutoOrder $order, int $quantity, object $instrument, object $kite): void
    {
        $price = null;
        if ($order->order_type === 'LIMIT') {
            $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
            $raw      = $order->entry_price - $discount;
            $tick     = $instrument->tick_size ?? 0.05;
            $price    = number_format(round($raw / $tick) * $tick, 2, '.', '');
        }

        $freezeLimit = self::FREEZE_LIMITS[$order->symbol] ?? null;

        if ($freezeLimit && $quantity > $freezeLimit) {
            $remaining = $quantity;
            while ($remaining > 0) {
                $lots = min($freezeLimit, $remaining);
                $this->executeSingleOrder($order, $lots, $price, $instrument, $kite);
                $remaining -= $lots;
                if ($remaining > 0) sleep(2);
            }
        } else {
            $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite);
        }
    }

    private function executeSingleOrder(FutOhlAutoOrder $order, int $quantity, ?string $price, object $instrument, object $kite): void
    {
        $lotSize = (int)($instrument->lot_size ?? 1);

        $params = [
            'exchange'         => 'NFO',
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => 'BUY',
            'quantity'         => $quantity * $lotSize,
            'product'          => $order->product,
            'order_type'       => $order->order_type === 'MARKET' ? 'MARKET' : 'LIMIT',
            'validity'         => 'DAY',
        ];

        if ($order->order_type !== 'MARKET') {
            $params['price'] = $price;
        }

        $result  = $kite->placeOrder('regular', $params);
        $orderId = is_object($result) ? $result->order_id : ($result['order_id'] ?? null);

        Log::info("FUT OHL Kite Order Placed! ID:{$orderId} | {$order->option_symbol} | Qty:" . ($quantity * $lotSize));
        $this->saveToOrderBook($order, $orderId, $quantity, $price);
    }

    // =========================================================
    //  LTP FETCH
    // =========================================================

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, string $tradingSymbol): float
    {
        try {
            $this->ensureKiteInstance($broker);
            $kite     = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:{$tradingSymbol}";
            $quotes   = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) return (float)$quotes->$quoteKey->last_price;
            $arr = json_decode(json_encode($quotes), true);
            if (isset($arr[$quoteKey]['last_price'])) return (float)$arr[$quoteKey]['last_price'];

        } catch (\Throwable $e) {
            Log::error("FUT OHL LTP {$tradingSymbol}: " . $e->getMessage());
        }

        return 0.0;
    }

    // =========================================================
    //  INSTRUMENT SERIES
    // =========================================================

    private function resolveInstrumentSeries(string $currentDate): ?string
    {
        $isTodayExpiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $currentDate)
            ->exists();

        $comparator = $isTodayExpiry ? '>' : '>=';

        $expiry = ZerodhaInstrument::where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', $comparator, $currentDate)
            ->orderBy('expiry', 'ASC')
            ->value('expiry');

        if (!$expiry) {
            $expiry = ZerodhaInstrument::where('exchange', 'NFO')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('expiry', '>', $currentDate)
                ->orderBy('expiry', 'ASC')
                ->value('expiry');
        }

        return $expiry
            ? (is_string($expiry) ? substr($expiry, 0, 10) : Carbon::parse($expiry)->toDateString())
            : null;
    }

    // =========================================================
    //  ORDER BOOK HELPERS
    // =========================================================

    private function saveToOrderBook(FutOhlAutoOrder $order, $orderId, int $quantity, ?string $price): void
    {
        try {
            sleep(2);
            $kite         = $this->kiteInstances[$order->broker_api_id] ?? null;
            $orderHistory = $kite ? $kite->getOrderHistory($orderId) : [];
            $last         = !empty($orderHistory) ? end($orderHistory) : null;

            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'N/A',
                'order_id'           => $orderId ?? '-',
                'status'             => $last->status ?? 'PENDING',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => $price ?? '-',
                'quantity'           => $quantity,
                'status_message'     => $last->status_message ?? 'FUT OHL order placed',
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("FUT OHL ORDER_BOOK: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(FutOhlAutoOrder $order, string $error): void
    {
        try {
            $order->update(['failure_reason' => substr($error, 0, 500)]);

            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'N/A',
                'order_id'           => '-',
                'status'             => 'FAILED',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => '-',
                'quantity'           => $order->quantity ?? 0,
                'status_message'     => substr($error, 0, 500),
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("FUT OHL ORDER_BOOK Failed: " . $e->getMessage());
        }
    }

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $kite;
        }
    }
}