<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BrokerApi;
use App\Models\SymbolMonitored;
use App\Models\ZerodhaInstrument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrokerSymbolController extends Controller
{
    /**
     * Display broker-symbol assignments
     */
    public function index()
    {
        $pageTitle = 'Broker Symbol Management';
        
        $brokers = BrokerApi::where('user_id', auth()->id())
            ->where('client_type', 'Zerodha')
            ->where('is_token_valid', true)
            ->get();

        $assignments = SymbolMonitored::with('broker')
            ->whereHas('broker', function($q) {
                $q->where('user_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Get available symbols from temp table (from seeder)
        $availableSymbols = $this->getAvailableSymbols();

        return view($this->activeTemplate . 'user.broker-symbol.index', compact(
            'pageTitle', 
            'brokers', 
            'assignments', 
            'availableSymbols'
        ));
    }

    /**
     * Store broker-symbol assignment
     */
    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbols' => 'required|array|min:1',
            'symbols.*' => 'required|string',
            'exchange' => 'required|in:NSE,BSE'
        ]);

        try {
            $broker = BrokerApi::where('id', $request->broker_api_id)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            $added = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->symbols as $symbol) {
                // Check if already exists
                $exists = SymbolMonitored::where('broker_api_id', $broker->id)
                    ->where('symbol', $symbol)
                    ->where('exchange', $request->exchange)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Get symbol details from Zerodha instruments
                $instrument = ZerodhaInstrument::where('name', $symbol)
                    ->where('exchange', $request->exchange)
                    ->where('instrument_type', 'EQ')
                    ->first();

                if (!$instrument) {
                    $errors[] = "Instrument not found for {$symbol}";
                    $skipped++;
                    continue;
                }

                // Create assignment
                SymbolMonitored::create([
                    'broker_api_id' => $broker->id,
                    'symbol' => $symbol,
                    'underlying_name' => $this->getUnderlyingName($symbol),
                    'exchange' => $request->exchange,
                    'instrument_type' => 'EQ',
                    'trading_symbol' => $instrument->trading_symbol,
                    'instrument_token' => $instrument->instrument_token,
                    'is_active' => true,
                    'last_synced_at' => now()
                ]);

                $added++;
            }

            $message = "Added: {$added}, Skipped: {$skipped}";
            if (!empty($errors)) {
                $message .= '. Errors: ' . implode(', ', array_slice($errors, 0, 3));
            }

            $notify[] = ['success', $message];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Broker Symbol Assignment Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete symbol assignment
     */
    public function destroy($id)
    {
        try {
            $assignment = SymbolMonitored::whereHas('broker', function($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($id);

            $assignment->delete();

            $notify[] = ['success', 'Symbol assignment deleted successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Symbol Assignment Delete Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting assignment: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Toggle symbol active status
     */
    public function toggleStatus($id)
    {
        try {
            $assignment = SymbolMonitored::whereHas('broker', function($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($id);

            $assignment->update([
                'is_active' => !$assignment->is_active
            ]);

            $status = $assignment->is_active ? 'activated' : 'deactivated';
            $notify[] = ['success', "Symbol {$status} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Toggle Status Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error toggling status: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Sync instrument details from Zerodha
     */
    public function syncInstruments($id)
    {
        try {
            $assignment = SymbolMonitored::whereHas('broker', function($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($id);

            $synced = $assignment->syncInstrumentDetails();

            if ($synced) {
                $notify[] = ['success', 'Instrument details synced successfully!'];
            } else {
                $notify[] = ['warning', 'Instrument not found in Zerodha database'];
            }

            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Sync Instruments Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error syncing: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Bulk sync all instruments for a broker
     */
    public function bulkSync($brokerId)
    {
        try {
            $broker = BrokerApi::where('id', $brokerId)
                ->where('user_id', auth()->id())
                ->where('client_type', 'Zerodha')
                ->firstOrFail();

            $assignments = SymbolMonitored::where('broker_api_id', $brokerId)->get();
            
            $synced = 0;
            $failed = 0;

            foreach ($assignments as $assignment) {
                if ($assignment->syncInstrumentDetails()) {
                    $synced++;
                } else {
                    $failed++;
                }
            }

            $notify[] = ['success', "Synced: {$synced}, Failed: {$failed}"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Bulk Sync Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Get available symbols (from seeder)
     */
    private function getAvailableSymbols()
    {
        // Try to get from temp table first
        try {
            $symbols = DB::table('temp_stock_symbols')
                ->select('symbol', 'underlying')
                ->orderBy('symbol')
                ->get()
                ->pluck('underlying', 'symbol')
                ->toArray();

            if (!empty($symbols)) {
                return $symbols;
            }
        } catch (\Exception $e) {
            // Table doesn't exist, use fallback
        }

        // Fallback: Get from Zerodha instruments
        return ZerodhaInstrument::where('exchange', 'NSE')
            ->where('instrument_type', 'EQ')
            ->whereNotNull('name')
            ->select('name as symbol', 'trading_symbol as underlying')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->pluck('underlying', 'symbol')
            ->toArray();
    }

    /**
     * Get underlying name for symbol
     */
    private function getUnderlyingName($symbol)
    {
        try {
            $result = DB::table('temp_stock_symbols')
                ->where('symbol', $symbol)
                ->value('underlying');

            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        // Fallback to Zerodha instruments
        $instrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NSE')
            ->where('instrument_type', 'EQ')
            ->first();

        return $instrument ? $instrument->trading_symbol : $symbol;
    }
}