<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreezingQuantity extends Model
{
    protected $fillable = [
        'symbol',
        'freezing_quantity',
        'is_active',
    ];

    protected $casts = [
        'freezing_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Extract base symbol from trading symbol
     * Examples:
     * NIFTY2531922500CE -> NIFTY
     * RELIANCE26FEB3000CE -> RELIANCE
     * TATAMOTORS26FEBFUT -> TATAMOTORS
     */
    public static function extractBaseSymbol($tradingSymbol)
    {
        // Remove date patterns (DDMMMYY or DDMMMYYYY) and strike/type suffixes
        $pattern = '/(\d{2}[A-Z]{3}\d{2,4})(FUT|CE|PE|\d+CE|\d+PE)$/i';
        $baseSymbol = preg_replace($pattern, '', $tradingSymbol);
        
        // Also handle pure numeric patterns at end (like strike prices)
        $baseSymbol = preg_replace('/\d+$/', '', $baseSymbol);
        
        return strtoupper(trim($baseSymbol));
    }

    /**
     * Get lot size for a trading symbol from Zerodha instruments
     */
    public static function getLotSize($tradingSymbol)
    {
        try {
            // First try exact match
            $instrument = \App\Models\ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->first();
            
            if ($instrument && $instrument->lot_size > 0) {
                return $instrument->lot_size;
            }

            // Fallback: extract base symbol and search with LIKE
            $baseSymbol = static::extractBaseSymbol($tradingSymbol);
            $instrument = \App\Models\ZerodhaInstrument::where('trading_symbol', 'LIKE', $baseSymbol . '%')
                ->where('lot_size', '>', 0)
                ->orderBy('lot_size', 'asc')
                ->first();

            if ($instrument && $instrument->lot_size > 0) {
                \Log::info("getLotSize: exact match not found for {$tradingSymbol}, using base symbol {$baseSymbol} → lot_size={$instrument->lot_size}");
                return $instrument->lot_size;
            }

            \Log::warning("getLotSize: no lot size found for {$tradingSymbol}, defaulting to 1");
            return 1;

        } catch (\Exception $e) {
            \Log::warning("Error getting lot size for {$tradingSymbol}: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get freezing quantity (in LOTS) for a trading symbol
     */
    public static function getFreezingQtyInLots($tradingSymbol)
    {
        $baseSymbol = static::extractBaseSymbol($tradingSymbol);
        
        $result = static::where('symbol', $baseSymbol)
            ->where('is_active', true)
            ->first();

        // Return the freezing quantity in lots, or default to 1800 if not found
        return $result ? $result->freezing_quantity : 1800;
    }

    /**
     * Get freezing quantity in actual quantity (lots × lot_size)
     */
    public static function getFreezingQtyInQuantity($tradingSymbol)
    {
        $freezingLots = static::getFreezingQtyInLots($tradingSymbol);
        $lotSize = static::getLotSize($tradingSymbol);
        
        return $freezingLots * $lotSize;
    }

    /**
     * Calculate number of lots from quantity
     */
    public static function calculateLots($tradingSymbol, $quantity)
    {
        $lotSize = static::getLotSize($tradingSymbol);
        return ceil($quantity / $lotSize);
    }

    /**
     * Get chunk sizes for quantity splitting
     * Returns array of quantities to place (each will be multiple of lot size)
     */
    public static function getChunkSizes($tradingSymbol, $totalQuantity)
    {
        $lotSize      = static::getLotSize($tradingSymbol);
        $freezingLots = static::getFreezingQtyInLots($tradingSymbol);
        $freezingQty  = $freezingLots * $lotSize; // max qty per single order

        // Normalize totalQuantity to valid lot size multiple
        $totalLots = (int) ceil($totalQuantity / $lotSize);

        // No split needed
        if ($totalLots <= $freezingLots) {
            return [$totalLots * $lotSize];
        }

        // Split into chunks, each ≤ freezingQty
        $chunks        = [];
        $remainingLots = $totalLots;

        while ($remainingLots > 0) {
            $lotsInThisChunk = min($freezingLots, $remainingLots);
            $chunks[]         = $lotsInThisChunk * $lotSize;
            $remainingLots   -= $lotsInThisChunk;
        }

        return $chunks;
    }

    /**
     * Check if quantity needs splitting
     */
    public static function needsSplitting($tradingSymbol, $quantity)
    {
        $totalLots = static::calculateLots($tradingSymbol, $quantity);
        $freezingLots = static::getFreezingQtyInLots($tradingSymbol);
        
        return $totalLots > $freezingLots;
    }

    /**
     * Validate if quantity is valid (multiple of lot size)
     */
    public static function isValidQuantity($tradingSymbol, $quantity)
    {
        $lotSize = static::getLotSize($tradingSymbol);
        return ($quantity % $lotSize) === 0;
    }

    /**
     * Round quantity to nearest valid lot size multiple
     */
    public static function roundToLotSize($tradingSymbol, $quantity)
    {
        $lotSize = static::getLotSize($tradingSymbol);
        return ceil($quantity / $lotSize) * $lotSize;
    }
}