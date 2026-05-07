<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InstrumentHistoricalDataNew extends Model
{
    use HasFactory;

    protected $table = 'instrument_historical_data_news';

    protected $fillable = [
        'instrument_chain_id',
        'date',
        'underlying',
        'symbol',
        'type',
        'strike_price',
        'open',
        'high',
        'low',
        'close',
        'ltp',
        'volume',
        'oi',
        'price_change',
        'price_change_percent',
        'oi_change',
        'oi_change_percent',
        'iv',
        'delta',
        'gamma',
        'theta',
        'vega',
        'data_quality_score',
        'missing_fields'
    ];

    protected $casts = [
        'date' => 'date',
        'strike_price' => 'decimal:2',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'ltp' => 'decimal:2',
        'volume' => 'integer',
        'oi' => 'integer',
        'price_change' => 'decimal:2',
        'price_change_percent' => 'decimal:4',
        'oi_change' => 'integer',
        'oi_change_percent' => 'decimal:4',
        'iv' => 'decimal:4',
        'delta' => 'decimal:4',
        'gamma' => 'decimal:6',
        'theta' => 'decimal:4',
        'vega' => 'decimal:4',
        'data_quality_score' => 'decimal:2',
        'missing_fields' => 'array'
    ];

    /**
     * Relationship with InstrumentChain
     */
    public function instrumentChain()
    {
        return $this->belongsTo(InstrumentChain::class, 'instrument_chain_id');
    }

    /**
     * Scope: Get data for specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope: Get data for specific underlying
     */
    public function scopeForUnderlying($query, $underlying)
    {
        return $query->where('underlying', $underlying);
    }

    /**
     * Scope: Get data for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope: Get only quality data
     */
    public function scopeQualityData($query, $minScore = 80)
    {
        return $query->where('data_quality_score', '>=', $minScore);
    }

    /**
     * Get missing dates for an underlying
     */
    public static function getMissingDates($underlying, $startDate, $endDate)
    {
        $existingDates = self::where('underlying', $underlying)
            ->whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $allDates = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current <= $end) {
            if (!$current->isWeekend()) {
                $dateStr = $current->format('Y-m-d');
                
                $isHoliday = DB::table('market_holidays')
                    ->where('market_name', 'NSE')
                    ->where('holiday_date', $dateStr)
                    ->exists();
                
                if (!$isHoliday && !in_array($dateStr, $existingDates)) {
                    $allDates[] = $dateStr;
                }
            }
            $current->addDay();
        }

        return $allDates;
    }

    /**
     * Get data quality report
     */
    public static function getDataQualityReport($startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return [
            'total_records' => $query->count(),
            'high_quality' => (clone $query)->where('data_quality_score', '>=', 90)->count(),
            'medium_quality' => (clone $query)->whereBetween('data_quality_score', [70, 89.99])->count(),
            'low_quality' => (clone $query)->where('data_quality_score', '<', 70)->count(),
            'average_score' => round($query->avg('data_quality_score'), 2),
            'by_underlying' => (clone $query)
                ->select('underlying', DB::raw('AVG(data_quality_score) as avg_score'), DB::raw('COUNT(*) as count'))
                ->groupBy('underlying')
                ->get()
        ];
    }

    /**
     * Get option chain for specific date and underlying
     */
    public static function getOptionChain($underlying, $date, $strikeCount = 7)
    {
        $future = self::where('underlying', $underlying)
            ->where('date', $date)
            ->where('type', 'FUT')
            ->first();

        if (!$future) {
            return null;
        }

        // Determine step value based on underlying
        $stepValue = in_array($underlying, ['NIFTY', 'BANKNIFTY', 'FINNIFTY']) ? 50 : 100;
        $atm = round($future->close / $stepValue) * $stepValue;

        $chain = self::where('underlying', $underlying)
            ->where('date', $date)
            ->whereIn('type', ['CE', 'PE'])
            ->whereBetween('strike_price', [
                $atm - (($strikeCount - 1) / 2 * $stepValue),
                $atm + (($strikeCount - 1) / 2 * $stepValue)
            ])
            ->orderBy('strike_price')
            ->orderBy('type')
            ->get()
            ->groupBy('strike_price');

        return [
            'future' => $future,
            'atm' => $atm,
            'chain' => $chain
        ];
    }

    /**
     * Calculate Greeks (placeholder - requires options pricing library)
     */
    public function calculateGreeks($spotPrice, $riskFreeRate = 0.10, $timeToExpiry = null)
    {
        // This is a placeholder
        // Implement actual Greeks calculation using Black-Scholes or similar
        // You'll need to install a library like `php-option-pricing`
        
        if ($this->type === 'FUT') {
            return null; // Futures don't have Greeks
        }

        // TODO: Implement actual Greeks calculation
        return [
            'iv' => null,
            'delta' => null,
            'gamma' => null,
            'theta' => null,
            'vega' => null
        ];
    }

    /**
     * Get summary statistics for a date
     */
    public static function getDailySummary($date, $underlying = null)
    {
        $query = self::where('date', $date);
        
        if ($underlying) {
            $query->where('underlying', $underlying);
        }

        return [
            'date' => $date,
            'total_instruments' => $query->count(),
            'total_volume' => $query->sum('volume'),
            'total_oi' => $query->sum('oi'),
            'avg_quality_score' => round($query->avg('data_quality_score'), 2),
            'instruments_with_data' => $query->where('volume', '>', 0)->count(),
            'by_type' => $query->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get()
        ];
    }
}