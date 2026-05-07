<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBrokerApi;
use App\Models\BrokerTimeframeAssignment;
use App\Models\TimeframeSymbol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrokerTimeframeController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // ASSIGNMENTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Main management page — assignments + symbols in one view.
     */
    public function index()
    {
        $pageTitle   = 'Broker Timeframe Management';
        $timeframes  = BrokerTimeframeAssignment::TIMEFRAMES;

        // All active Zerodha brokers with valid tokens (for dropdowns)
        $brokers = AdminBrokerApi::zerodha()
            ->active()
            ->orderBy('client_name')
            ->get();

        // All assignments, eager-load broker
        $assignments = BrokerTimeframeAssignment::with('broker')
            ->orderBy('timeframe')
            ->paginate(20, ['*'], 'a_page');

        // Symbol list grouped by timeframe for the symbol management section
        $symbolsByTimeframe = TimeframeSymbol::orderBy('timeframe')
            ->orderBy('symbol')
            ->get()
            ->groupBy('timeframe');

        return view('admin.broker-timeframe.index', compact(
            'pageTitle', 'timeframes', 'brokers', 'assignments', 'symbolsByTimeframe'
        ));
    }

    /**
     * Store a new assignment.
     */
    public function storeAssignment(Request $request)
    {
        $request->validate([
            'admin_broker_api_id' => 'required|exists:admin_broker_apis,id',
            'timeframe'           => 'required|in:' . implode(',', array_keys(BrokerTimeframeAssignment::TIMEFRAMES)),
            'label'               => 'nullable|string|max:255',
            'notes'               => 'nullable|string|max:500',
        ]);

        try {
            // If setting this as active, deactivate any existing active assignment for the same timeframe
            if ($request->boolean('is_active', true)) {
                BrokerTimeframeAssignment::where('timeframe', $request->timeframe)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $broker = AdminBrokerApi::findOrFail($request->admin_broker_api_id);

            BrokerTimeframeAssignment::create([
                'admin_broker_api_id' => $request->admin_broker_api_id,
                'timeframe'           => $request->timeframe,
                'label'               => $request->label ?: "{$broker->client_name} → {$request->timeframe}",
                'is_active'           => $request->boolean('is_active', true),
                'notes'               => $request->notes,
            ]);

            $notify[] = ['success', "Broker assigned to {$request->timeframe} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('BrokerTimeframe storeAssignment: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating assignment: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Toggle assignment active/inactive.
     */
    public function toggleAssignment($id)
    {
        try {
            $assignment = BrokerTimeframeAssignment::findOrFail($id);

            // Activating? Deactivate siblings for the same timeframe first
            if (!$assignment->is_active) {
                BrokerTimeframeAssignment::where('timeframe', $assignment->timeframe)
                    ->where('id', '!=', $id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $assignment->update(['is_active' => !$assignment->is_active]);

            $status = $assignment->is_active ? 'activated' : 'deactivated';
            $notify[] = ['success', "Assignment {$status}!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('BrokerTimeframe toggleAssignment: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete an assignment.
     */
    public function destroyAssignment($id)
    {
        try {
            BrokerTimeframeAssignment::findOrFail($id)->delete();
            $notify[] = ['success', 'Assignment deleted successfully!'];
            return back()->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('BrokerTimeframe destroyAssignment: ' . $e->getMessage());
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SYMBOLS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Add a symbol to a timeframe.
     */
    public function storeSymbol(Request $request)
    {
        $request->validate([
            'symbol'    => 'required|string|max:50|regex:/^[A-Z0-9]+$/',
            'timeframe' => 'required|in:' . implode(',', array_keys(BrokerTimeframeAssignment::TIMEFRAMES)),
            'exchange'  => 'required|in:NSE,NFO,BFO,BSE',
        ]);

        try {
            $symbol = strtoupper(trim($request->symbol));

            $exists = TimeframeSymbol::where('symbol', $symbol)
                ->where('timeframe', $request->timeframe)
                ->exists();

            if ($exists) {
                $notify[] = ['error', "{$symbol} already exists for {$request->timeframe}."];
                return back()->withNotify($notify);
            }

            TimeframeSymbol::create([
                'symbol'    => $symbol,
                'timeframe' => $request->timeframe,
                'exchange'  => $request->exchange,
                'is_active' => true,
                'notes'     => $request->notes,
            ]);

            $notify[] = ['success', "{$symbol} added to {$request->timeframe} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('BrokerTimeframe storeSymbol: ' . $e->getMessage());
            $notify[] = ['error', 'Error adding symbol: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Toggle a symbol active/inactive.
     */
    public function toggleSymbol($id)
    {
        try {
            $sym = TimeframeSymbol::findOrFail($id);
            $sym->update(['is_active' => !$sym->is_active]);

            $status  = $sym->is_active ? 'activated' : 'deactivated';
            $notify[] = ['success', "{$sym->symbol} ({$sym->timeframe}) {$status}!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete a symbol from a timeframe.
     */
    public function destroySymbol($id)
    {
        try {
            $sym = TimeframeSymbol::findOrFail($id);
            $label = "{$sym->symbol} ({$sym->timeframe})";
            $sym->delete();

            $notify[] = ['success', "{$label} removed successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Bulk-add symbols to a timeframe (comma-separated input).
     */
    public function bulkAddSymbols(Request $request)
    {
        $request->validate([
            'symbols'   => 'required|string',
            'timeframe' => 'required|in:' . implode(',', array_keys(BrokerTimeframeAssignment::TIMEFRAMES)),
            'exchange'  => 'required|in:NSE,NFO,BFO,BSE',
        ]);

        $symbols = array_filter(
            array_map(fn($s) => strtoupper(trim($s)), explode(',', $request->symbols)),
            fn($s) => preg_match('/^[A-Z0-9]+$/', $s)
        );

        if (empty($symbols)) {
            $notify[] = ['error', 'No valid symbols found in input.'];
            return back()->withNotify($notify);
        }

        $added   = 0;
        $skipped = 0;

        foreach ($symbols as $symbol) {
            $exists = TimeframeSymbol::where('symbol', $symbol)
                ->where('timeframe', $request->timeframe)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            TimeframeSymbol::create([
                'symbol'    => $symbol,
                'timeframe' => $request->timeframe,
                'exchange'  => $request->exchange,
                'is_active' => true,
            ]);
            $added++;
        }

        $notify[] = ['success', "{$added} symbol(s) added, {$skipped} skipped (already exist)."];
        return back()->withNotify($notify);
    }
}