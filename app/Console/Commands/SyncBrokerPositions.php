<?php

namespace App\Console\Commands;

use App\Models\BrokerApi;
use App\Models\PortfolioPosition;
use App\Models\PositionHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * SyncBrokerPositions
 *
 * Run every 1-2 minutes via cron.
 *
 * What this does:
 *  1. Fetch live positions from Zerodha
 *  2. Create new DB records for new positions (with purchase_date)
 *  3. Update existing open positions (LTP, PnL, qty)
 *  4. Detect positions that disappeared (user sold from Zerodha directly)
 *  5. Mark them CLOSED and move to position_history with entry/exit price + PnL
 *
 * Schedule in Kernel.php:
 *   $schedule->command('positions:sync')->everyTwoMinutes();
 *
 * Manual run:
 *   php artisan positions:sync
 *   php artisan positions:sync --broker_id=1
 */
class SyncBrokerPositions extends Command
{
    protected $signature = 'positions:sync
                            {--broker_id= : Sync only this broker ID}
                            {--verbose-log : Extra logging for debugging}';

    protected $description = 'Sync live positions from Zerodha. Detects new, updated, and closed positions.';

    // Stats for this run
    private array $stats = [
        'brokers_processed' => 0,
        'brokers_failed'    => 0,
        'positions_new'     => 0,
        'positions_updated' => 0,
        'positions_closed'  => 0,
    ];

    public function handle(): int
    {
        $startTime = microtime(true);
        $this->info('🔄 [' . now()->format('H:i:s') . '] Starting position sync...');

        $brokers = $this->getBrokers();

        if ($brokers->isEmpty()) {
            $this->warn('⚠️  No active brokers with valid tokens found.');
            return Command::FAILURE;
        }

        foreach ($brokers as $broker) {
            try {
                $this->syncBroker($broker);
                $this->stats['brokers_processed']++;
            } catch (\Exception $e) {
                $this->stats['brokers_failed']++;
                $this->error("❌ Broker [{$broker->id}] {$broker->client_name}: " . $e->getMessage());
                Log::error("SyncBrokerPositions - Broker {$broker->id} failed: " . $e->getMessage());
            }

            // Small delay between brokers to avoid rate limits
            if ($brokers->count() > 1) {
                sleep(1);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->printSummary($elapsed);

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────
    // BROKER PROCESSING
    // ─────────────────────────────────────────────────────────────

    private function syncBroker(BrokerApi $broker): void
    {
        $this->line("\n📊 Broker: {$broker->client_name} ({$broker->account_user_name})");

        // Initialize Kite
        $kite = new KiteConnect($broker->api_key);
        $kite->setAccessToken($broker->access_token);

        // ── STEP 1: Fetch positions from Zerodha ──────────────────
        $zerodhaPositions = $this->fetchFromZerodha($kite, $broker);
        $this->line("  📥 Fetched " . count($zerodhaPositions) . " live positions from Zerodha");

        // Build a lookup map: "SYMBOL|EXCHANGE|PRODUCT" → position data
        $zerodhaMap = [];
        foreach ($zerodhaPositions as $pos) {
            $key = $pos['symbol'] . '|' . $pos['exchange'] . '|' . $pos['product'];
            $zerodhaMap[$key] = $pos;
        }

        // ── STEP 2: Get all OPEN positions in our DB for this broker ──
        $dbOpenPositions = PortfolioPosition::where('broker_api_id', $broker->id)
            ->where('user_id', $broker->user_id)
            ->where('position_status', 'open')
            ->get();

        // ── STEP 3: Handle positions that are OPEN in DB ──────────
        foreach ($dbOpenPositions as $dbPos) {
            $key = $dbPos->tradingsymbol . '|' . $dbPos->exchange . '|' . $dbPos->product;

            if (isset($zerodhaMap[$key])) {
                // Position still exists in Zerodha → update it
                $this->updateExistingPosition($dbPos, $zerodhaMap[$key]);
                unset($zerodhaMap[$key]); // Remove from map so we don't create duplicate
            } else {
                // Position NOT found in Zerodha → it was CLOSED (manually or otherwise)
                $this->closePosition($dbPos, $kite);
            }
        }

        // ── STEP 4: Remaining items in zerodhaMap = brand new positions ──
        foreach ($zerodhaMap as $key => $posData) {
            $this->createNewPosition($broker, $posData);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // FETCH FROM ZERODHA
    // ─────────────────────────────────────────────────────────────

    private function fetchFromZerodha(KiteConnect $kite, BrokerApi $broker): array
    {
        try {
            $positions = $kite->getPositions();
            $netPositions = $positions->net ?? [];

            $result = [];

            foreach ($netPositions as $pos) {
                // Skip fully squared off intraday positions (qty=0)
                // For NRML/CNC - we want to track even if same-day close
                if ($pos->quantity == 0 && $pos->product == 'MIS') {
                    continue;
                }

                // For NRML/CNC, if qty = 0 it means intraday round-trip — still capture for history
                $result[] = [
                    'symbol'             => $pos->tradingsymbol,
                    'exchange'           => $pos->exchange,
                    'product'            => $pos->product,
                    'instrument_token'   => $pos->instrument_token ?? null,
                    'net_qty'            => (int) $pos->quantity,
                    'avg_price'          => round($pos->average_price, 2),
                    'last_price'         => round($pos->last_price, 2),
                    'pnl'                => round($pos->pnl, 2),
                    'buy_qty'            => (int) ($pos->buy_quantity ?? 0),
                    'sell_qty'           => (int) ($pos->sell_quantity ?? 0),
                    'buy_value'          => round($pos->buy_value ?? 0, 2),
                    'sell_value'         => round($pos->sell_value ?? 0, 2),
                    'unrealised'         => round($pos->unrealised ?? 0, 2),
                    'realised'           => round($pos->realised ?? 0, 2),
                    'overnight_quantity' => (int) ($pos->overnight_quantity ?? 0),
                    'position_type'      => $pos->quantity >= 0 ? 'LONG' : 'SHORT',
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("SyncBrokerPositions - fetchFromZerodha failed for broker {$broker->id}: " . $e->getMessage());
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CREATE NEW POSITION
    // ─────────────────────────────────────────────────────────────

    private function createNewPosition(BrokerApi $broker, array $posData): void
    {
        try {
            // Only create if qty is non-zero
            if ($posData['net_qty'] == 0) return;

            PortfolioPosition::create([
                'user_id'            => $broker->user_id,
                'broker_api_id'      => $broker->id,
                'tradingsymbol'      => $posData['symbol'],
                'exchange'           => $posData['exchange'],
                'product'            => $posData['product'],
                'instrument_token'   => $posData['instrument_token'],
                'purchase_date'      => now(),              // When first seen = when purchased
                'purchase_price'     => $posData['avg_price'],
                'quantity'           => $posData['net_qty'],
                'overnight_quantity' => $posData['overnight_quantity'],
                'average_price'      => $posData['avg_price'],
                'last_price'         => $posData['last_price'],
                'pnl'                => $posData['pnl'],
                'value'              => round($posData['avg_price'] * abs($posData['net_qty']), 2),
                'buy_sell'           => $posData['position_type'],
                'position_status'    => 'open',
                'fetched_at'         => now(),
            ]);

            $this->stats['positions_new']++;
            $this->line("  ✨ NEW: {$posData['symbol']} | Qty: {$posData['net_qty']} | Avg: ₹{$posData['avg_price']}");

        } catch (\Exception $e) {
            Log::error("SyncBrokerPositions - createNewPosition failed for {$posData['symbol']}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // UPDATE EXISTING POSITION
    // ─────────────────────────────────────────────────────────────

    private function updateExistingPosition(PortfolioPosition $dbPos, array $posData): void
    {
        try {
            $dbPos->update([
                'quantity'           => $posData['net_qty'],
                'overnight_quantity' => $posData['overnight_quantity'],
                'average_price'      => $posData['avg_price'],
                'last_price'         => $posData['last_price'],
                'pnl'                => $posData['pnl'],
                'value'              => round($posData['avg_price'] * abs($posData['net_qty']), 2),
                'buy_sell'           => $posData['position_type'],
                'fetched_at'         => now(),
            ]);

            $this->stats['positions_updated']++;

            if ($this->option('verbose-log')) {
                $this->line("  🔄 UPDATED: {$dbPos->tradingsymbol} | LTP: ₹{$posData['last_price']} | PnL: ₹{$posData['pnl']}");
            }

        } catch (\Exception $e) {
            Log::error("SyncBrokerPositions - updateExistingPosition failed for {$dbPos->tradingsymbol}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // CLOSE POSITION — Core logic that saves history
    // ─────────────────────────────────────────────────────────────

    private function closePosition(PortfolioPosition $dbPos, KiteConnect $kite): void
    {
        DB::beginTransaction();
        try {
            // ── Try to find exit price from order history ─────────
            $exitDetails = $this->detectExitFromOrders($kite, $dbPos);

            $exitPrice   = $exitDetails['exit_price'] ?? $dbPos->last_price;
            $exitSource  = $exitDetails['source'] ?? 'MANUAL_ZERODHA';

            // ── Calculate realized PnL ────────────────────────────
            // For LONG: (exit_price - entry_price) * qty
            // For SHORT: (entry_price - exit_price) * qty
            $entryPrice = $dbPos->purchase_price ?: $dbPos->average_price;
            $qty        = abs($dbPos->quantity);

            if ($dbPos->buy_sell === 'LONG') {
                $realizedPnl = ($exitPrice - $entryPrice) * $qty;
            } else {
                $realizedPnl = ($entryPrice - $exitPrice) * $qty;
            }

            // ── Calculate holding days ────────────────────────────
            $holdingDays = Carbon::parse($dbPos->purchase_date)->diffInDays(now());

            // ── Save to position_history (permanent closed record) ──
            PositionHistory::create([
                'user_id'               => $dbPos->user_id,
                'broker_api_id'         => $dbPos->broker_api_id,
                'symbol'                => $dbPos->tradingsymbol,
                'exchange'              => $dbPos->exchange,
                'product'               => $dbPos->product,
                'instrument_token'      => $dbPos->instrument_token,
                'position_type'         => $dbPos->buy_sell,
                'qty'                   => $qty,
                'buy_qty'               => $dbPos->quantity > 0 ? $qty : 0,
                'sell_qty'              => $dbPos->quantity < 0 ? $qty : 0,
                'entry_price'           => $entryPrice,
                'exit_price'            => $exitPrice,
                'buy_value'             => round($entryPrice * $qty, 2),
                'sell_value'            => round($exitPrice * $qty, 2),
                'realized_pnl'          => round($realizedPnl, 2),
                'entry_date'            => Carbon::parse($dbPos->purchase_date)->toDateString(),
                'exit_date'             => now()->toDateString(),
                'holding_days'          => $holdingDays,
                'exit_source'           => $exitSource,
                'portfolio_position_id' => $dbPos->id,
            ]);

            // ── Mark original position as CLOSED ──────────────────
            $dbPos->update([
                'position_status' => 'closed',
                'exit_price'      => $exitPrice,
                'exit_time'       => now(),
                'realized_pnl'    => round($realizedPnl, 2),
                'holding_days'    => $holdingDays,
                'exit_source'     => $exitSource,
            ]);

            DB::commit();

            $this->stats['positions_closed']++;
            $pnlStr = ($realizedPnl >= 0 ? '+' : '') . '₹' . number_format(abs($realizedPnl), 2);
            $this->line("  🔒 CLOSED: {$dbPos->tradingsymbol} | Entry: ₹{$entryPrice} | Exit: ₹{$exitPrice} | PnL: {$pnlStr} | Days: {$holdingDays}");
            Log::info("SyncBrokerPositions - Closed: {$dbPos->tradingsymbol} | PnL: {$pnlStr}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SyncBrokerPositions - closePosition failed for {$dbPos->tradingsymbol}: " . $e->getMessage());
            $this->error("  ❌ Could not close {$dbPos->tradingsymbol}: " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DETECT EXIT PRICE FROM ORDER HISTORY
    // ─────────────────────────────────────────────────────────────

    /**
     * When Zerodha says a position is gone, we try to find the exit order
     * by scanning today's order history and matching by symbol + SELL transaction.
     */
    private function detectExitFromOrders(KiteConnect $kite, PortfolioPosition $dbPos): array
    {
        try {
            $orders = $kite->getOrders();

            $symbol       = $dbPos->tradingsymbol;
            $targetQty    = abs($dbPos->quantity);
            $sellType     = $dbPos->buy_sell === 'LONG' ? 'SELL' : 'BUY'; // opposite to close

            $matchingOrders = array_filter((array) $orders, function ($order) use ($symbol, $sellType) {
                $o = (array) $order;
                return
                    ($o['tradingsymbol'] ?? '') === $symbol &&
                    ($o['transaction_type'] ?? '') === $sellType &&
                    in_array($o['status'] ?? '', ['COMPLETE']);
            });

            if (empty($matchingOrders)) {
                return ['exit_price' => $dbPos->last_price, 'source' => 'MANUAL_ZERODHA'];
            }

            // Calculate weighted average exit price across matching orders
            $totalValue = 0;
            $totalQty   = 0;

            foreach ($matchingOrders as $order) {
                $o = (array) $order;
                $filledQty   = (float) ($o['filled_quantity'] ?? 0);
                $averagePrice = (float) ($o['average_price'] ?? 0);
                $totalValue  += $filledQty * $averagePrice;
                $totalQty    += $filledQty;
            }

            $exitPrice = $totalQty > 0 ? round($totalValue / $totalQty, 2) : $dbPos->last_price;

            return [
                'exit_price' => $exitPrice,
                'source'     => 'MANUAL_ZERODHA',
            ];

        } catch (\Exception $e) {
            Log::warning("SyncBrokerPositions - detectExitFromOrders failed for {$dbPos->tradingsymbol}: " . $e->getMessage());
            return ['exit_price' => $dbPos->last_price, 'source' => 'MANUAL_ZERODHA'];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function getBrokers()
    {
        $query = BrokerApi::where('client_type', 'Zerodha')
            ->where('is_token_valid', true);

        if ($this->option('broker_id')) {
            $query->where('id', $this->option('broker_id'));
        }

        return $query->get();
    }

    private function printSummary(float $elapsed): void
    {
        $this->info("\n" . str_repeat('─', 50));
        $this->info("✅ Sync Complete in {$elapsed}s");
        $this->info("  Brokers: {$this->stats['brokers_processed']} ok / {$this->stats['brokers_failed']} failed");
        $this->info("  Positions: ✨ {$this->stats['positions_new']} new | 🔄 {$this->stats['positions_updated']} updated | 🔒 {$this->stats['positions_closed']} closed");
        $this->info(str_repeat('─', 50));
    }
}