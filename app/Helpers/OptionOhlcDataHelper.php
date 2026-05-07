<?php

namespace App\Helpers;

use App\Models\OptionOhlcData;
use Carbon\Carbon;

/**
 * Helper for querying Option OHLC Data
 */
class OptionOhlcDataHelper
{
    /**
     * Get complete snapshot for an interval
     * Returns: 1 FUT + 3 CE + 3 PE
     */
    public static function getSnapshot($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        $data = OptionOhlcData::getForInterval($brokerId, $baseSymbol, $tradeDate, $intervalTime);

        return [
            'future' => $data->where('instrument_type', 'FUT')->first(),
            'ce_atm' => $data->where('instrument_type', 'CE')->where('strike_position', 'ATM')->first(),
            'ce_atm_plus1' => $data->where('instrument_type', 'CE')->where('strike_position', 'ATM+1')->first(),
            'ce_atm_minus1' => $data->where('instrument_type', 'CE')->where('strike_position', 'ATM-1')->first(),
            'pe_atm' => $data->where('instrument_type', 'PE')->where('strike_position', 'ATM')->first(),
            'pe_atm_plus1' => $data->where('instrument_type', 'PE')->where('strike_position', 'ATM+1')->first(),
            'pe_atm_minus1' => $data->where('instrument_type', 'PE')->where('strike_position', 'ATM-1')->first(),
        ];
    }

    /**
     * Get all intervals for a trading day
     */
    public static function getDayData($brokerId, $baseSymbol, $tradeDate)
    {
        return OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->orderBy('interval_time', 'asc')
            ->get()
            ->groupBy('interval_time');
    }

    /**
     * Get best CE to BUY (underpriced)
     */
    public static function getBestCEToBuy($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('instrument_type', 'CE')
            ->where('valuation', 'UNDERPRICED')
            ->orderBy('fair_price', 'desc')
            ->first();
    }

    /**
     * Get best CE to SELL (overpriced)
     */
    public static function getBestCEToSell($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('instrument_type', 'CE')
            ->where('valuation', 'OVERPRICED')
            ->orderBy('fair_price', 'asc')
            ->first();
    }

    /**
     * Get best PE to BUY (underpriced)
     */
    public static function getBestPEToBuy($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('instrument_type', 'PE')
            ->where('valuation', 'UNDERPRICED')
            ->orderBy('fair_price', 'desc')
            ->first();
    }

    /**
     * Get best PE to SELL (overpriced)
     */
    public static function getBestPEToSell($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        return OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->where('instrument_type', 'PE')
            ->where('valuation', 'OVERPRICED')
            ->orderBy('fair_price', 'asc')
            ->first();
    }

    /**
     * Get OI analysis for interval
     */
    public static function getOIAnalysis($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        $data = OptionOhlcData::where('broker_api_id', $brokerId)
            ->where('base_symbol', $baseSymbol)
            ->where('trade_date', $tradeDate)
            ->where('interval_time', $intervalTime)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->get();

        $ceOI = $data->where('instrument_type', 'CE')->sum('oi');
        $peOI = $data->where('instrument_type', 'PE')->sum('oi');
        
        $putCallRatio = $peOI > 0 ? round($peOI / ($ceOI ?: 1), 2) : 0;

        return [
            'ce_total_oi' => $ceOI,
            'pe_total_oi' => $peOI,
            'put_call_ratio' => $putCallRatio,
            'ce_max_oi' => $data->where('instrument_type', 'CE')->max('oi'),
            'pe_max_oi' => $data->where('instrument_type', 'PE')->max('oi'),
            'ce_max_oi_strike' => $data->where('instrument_type', 'CE')
                ->sortByDesc('oi')
                ->first()
                ->strike ?? null,
            'pe_max_oi_strike' => $data->where('instrument_type', 'PE')
                ->sortByDesc('oi')
                ->first()
                ->strike ?? null,
        ];
    }

    /**
     * Get summary statistics
     */
    public static function getSummary($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        $snapshot = self::getSnapshot($brokerId, $baseSymbol, $tradeDate, $intervalTime);
        $oiAnalysis = self::getOIAnalysis($brokerId, $baseSymbol, $tradeDate, $intervalTime);

        return [
            'interval' => $intervalTime,
            'future_price' => $snapshot['future']->close ?? null,
            'atm_strike' => $snapshot['future']->atm_strike ?? null,
            'ce_atm_premium' => $snapshot['ce_atm']->close ?? null,
            'pe_atm_premium' => $snapshot['pe_atm']->close ?? null,
            'ce_atm_fair_price' => $snapshot['ce_atm']->fair_price ?? null,
            'pe_atm_fair_price' => $snapshot['pe_atm']->fair_price ?? null,
            'ce_atm_valuation' => $snapshot['ce_atm']->valuation ?? 'N/A',
            'pe_atm_valuation' => $snapshot['pe_atm']->valuation ?? 'N/A',
            'oi_analysis' => $oiAnalysis,
        ];
    }

    /**
     * Format data for display
     */
    public static function formatForDisplay($brokerId, $baseSymbol, $tradeDate, $intervalTime)
    {
        $snapshot = self::getSnapshot($brokerId, $baseSymbol, $tradeDate, $intervalTime);
        
        $output = [];
        $output[] = "📊 {$baseSymbol} - " . Carbon::parse($intervalTime)->format('Y-m-d H:i');
        $output[] = str_repeat('━', 60);
        
        if ($snapshot['future']) {
            $fut = $snapshot['future'];
            $output[] = "🔷 FUTURE: {$fut->trading_symbol}";
            $output[] = "   O: {$fut->open} | H: {$fut->high} | L: {$fut->low} | C: {$fut->close}";
            $output[] = "   OI: " . number_format($fut->oi) . " | Vol: " . number_format($fut->volume);
            $output[] = "";
        }

        $output[] = "📗 CALL OPTIONS (CE):";
        foreach (['ce_atm_minus1', 'ce_atm', 'ce_atm_plus1'] as $key) {
            if ($snapshot[$key]) {
                $ce = $snapshot[$key];
                $output[] = "   {$ce->strike_position}: {$ce->trading_symbol}";
                $output[] = "   O: {$ce->open} | H: {$ce->high} | L: {$ce->low} | C: {$ce->close}";
                $output[] = "   Fair: {$ce->fair_price} | Val: {$ce->valuation} | Rec: {$ce->recommendation}";
                $output[] = "   OI: " . number_format($ce->oi) . " | Vol: " . number_format($ce->volume);
                $output[] = "";
            }
        }

        $output[] = "📕 PUT OPTIONS (PE):";
        foreach (['pe_atm_minus1', 'pe_atm', 'pe_atm_plus1'] as $key) {
            if ($snapshot[$key]) {
                $pe = $snapshot[$key];
                $output[] = "   {$pe->strike_position}: {$pe->trading_symbol}";
                $output[] = "   O: {$pe->open} | H: {$pe->high} | L: {$pe->low} | C: {$pe->close}";
                $output[] = "   Fair: {$pe->fair_price} | Val: {$pe->valuation} | Rec: {$pe->recommendation}";
                $output[] = "   OI: " . number_format($pe->oi) . " | Vol: " . number_format($pe->volume);
                $output[] = "";
            }
        }

        return implode("\n", $output);
    }
}