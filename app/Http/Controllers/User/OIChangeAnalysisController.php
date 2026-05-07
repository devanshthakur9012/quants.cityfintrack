<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionStrike;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OIChangeAnalysisController extends Controller
{
    /**
     * Display CE/PE OI Change Analysis page
     */
    public function index()
    {
        $pageTitle = 'CE/PE OI Change Analysis';
        
        return view($this->activeTemplate . 'user.oi-change-analysis.index', compact('pageTitle'));
    }

    /**
     * Analyze CE/PE OI Change Signals
     * Logic:
     * - CE↑ + PE↓ → BEARISH (Call buildup + Put unwinding)
     * - CE↓ + PE↑ → BULLISH (Call unwinding + Put buildup)
     * - Both↑ → NEUTRAL (Range formation)
     * - Both↓ → NEUTRAL (Unwinding)
     */
    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterSignal = $request->get('filter_signal');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            Log::info('=== CE/PE OI CHANGE ANALYSIS START ===', [
                'from' => $fromDate,
                'to' => $toDate,
                'symbols' => $selectedSymbols,
                'filter' => $filterSignal
            ]);

            // Get all FUT records first
            $query = OptionStrike::where('strike_position', 'FUT')
                ->whereBetween('trading_date', [$fromDate, $toDate]);

            // Filter by symbols if selected
            if (!empty($selectedSymbols)) {
                $query->whereIn('underlying_symbol', $selectedSymbols);
            }

            $futRecords = $query->orderBy('trading_date', 'desc')
                ->orderBy('underlying_symbol', 'asc')
                ->get();

            Log::info("Found {$futRecords->count()} symbols");

            $results = [];
            foreach ($futRecords as $futRecord) {
                $analysisRow = $this->formatAnalysisData($futRecord);
                
                // Apply signal filter if selected
                if (!empty($filterSignal) && $analysisRow['signal'] !== $filterSignal) {
                    continue;
                }
                
                $results[] = $analysisRow;
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'total_records' => count($results),
                'message' => count($results) . ' records found'
            ]);

        } catch (\Exception $e) {
            Log::error('CE/PE OI Change Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Format analysis data with CE/PE OI change logic
     */
    private function formatAnalysisData($futRecord)
    {
        // Get CE and PE details
        $ceData = OptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'CE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        $peData = OptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'PE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        // Get OI change percentages
        $ceOiChangePct = $ceData ? ($ceData->daily_oi_change_pct ?? 0) : 0;
        $peOiChangePct = $peData ? ($peData->daily_oi_change_pct ?? 0) : 0;

        // Determine signal based on OI changes
        $signalData = $this->getOISignal($ceOiChangePct, $peOiChangePct);

        return [
            // Basic Info
            'date' => Carbon::parse($futRecord->trading_date)->format('Y-m-d'),
            'symbol' => $futRecord->underlying_symbol,
            'fut_symbol' => $futRecord->trading_symbol,
            'spot_price' => round($futRecord->spot_price ?? 0, 2),
            
            // CE Data
            'ce_oi' => $ceData ? $ceData->daily_oi : 0,
            'ce_oi_prev' => $ceData ? $ceData->daily_oi_prev : 0,
            'ce_oi_change_pct' => round($ceOiChangePct, 2),
            'ce_direction' => $ceOiChangePct > 0 ? 'UP' : ($ceOiChangePct < 0 ? 'DOWN' : 'FLAT'),
            
            // PE Data
            'pe_oi' => $peData ? $peData->daily_oi : 0,
            'pe_oi_prev' => $peData ? $peData->daily_oi_prev : 0,
            'pe_oi_change_pct' => round($peOiChangePct, 2),
            'pe_direction' => $peOiChangePct > 0 ? 'UP' : ($peOiChangePct < 0 ? 'DOWN' : 'FLAT'),
            
            // Signal Data
            'signal' => $signalData['signal'],
            'reason' => $signalData['reason'],
            'condition' => $signalData['condition'],
            
            // FUT Data (optional)
            'fut_oi' => $futRecord->daily_oi ?? 0,
            'fut_oi_change_pct' => round($futRecord->daily_oi_change_pct ?? 0, 2),
        ];
    }

    /**
     * Get OI Signal based on CE and PE changes
     * 
     * Logic:
     * - CE↑ + PE↓ → BEARISH (Call buildup + Put unwinding)
     * - CE↓ + PE↑ → BULLISH (Call unwinding + Put buildup)
     * - Both↑ → Compare: If CE% > PE% → BEARISH, else BULLISH
     * - Both↓ → Compare: If CE% < PE% (more negative) → BEARISH, else BULLISH
     */
    private function getOISignal($ceChangePct, $peChangePct)
    {
        // Determine direction
        $ceUp = $ceChangePct > 0;
        $ceDown = $ceChangePct < 0;
        $peUp = $peChangePct > 0;
        $peDown = $peChangePct < 0;

        // CE up + PE down → Bearish
        if ($ceUp && $peDown) {
            return [
                'signal' => 'BEARISH',
                'reason' => 'Call buildup + Put unwinding',
                'condition' => 'CE ↑ + PE ↓'
            ];
        }

        // CE down + PE up → Bullish
        if ($ceDown && $peUp) {
            return [
                'signal' => 'BULLISH',
                'reason' => 'Call unwinding + Put buildup',
                'condition' => 'CE ↓ + PE ↑'
            ];
        }

        // Both up → Compare which is stronger
        if ($ceUp && $peUp) {
            if ($ceChangePct > $peChangePct) {
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both buildup but CE stronger (CE: +{$ceChangePct}% > PE: +{$peChangePct}%)",
                    'condition' => 'Both ↑ (CE > PE)'
                ];
            } else {
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both buildup but PE stronger (PE: +{$peChangePct}% > CE: +{$ceChangePct}%)",
                    'condition' => 'Both ↑ (PE > CE)'
                ];
            }
        }

        // Both down → Compare which is more negative (stronger unwinding)
        if ($ceDown && $peDown) {
            if ($ceChangePct < $peChangePct) {
                // CE is more negative, means more CE unwinding → Bullish
                return [
                    'signal' => 'BULLISH',
                    'reason' => "Both unwinding but CE stronger (CE: {$ceChangePct}% < PE: {$peChangePct}%)",
                    'condition' => 'Both ↓ (CE < PE)'
                ];
            } else {
                // PE is more negative, means more PE unwinding → Bearish
                return [
                    'signal' => 'BEARISH',
                    'reason' => "Both unwinding but PE stronger (PE: {$peChangePct}% < CE: {$ceChangePct}%)",
                    'condition' => 'Both ↓ (PE < CE)'
                ];
            }
        }

        // Default case (flat)
        return [
            'signal' => 'NEUTRAL',
            'reason' => 'No clear OI direction',
            'condition' => 'Flat'
        ];
    }

    /**
     * Get unique symbols for filter
     */
    public function getSymbols()
    {
        $symbols = OptionStrike::where('strike_position', 'FUT')
            ->distinct()
            ->pluck('underlying_symbol')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'symbols' => $symbols
        ]);
    }
}