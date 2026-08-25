<?php

namespace App\Services\Cp;

use App\Models\CpAnalysis;
use App\Http\Controllers\User\GapReversalController;
use App\Http\Controllers\User\OIFlowSentimentController;
use App\Http\Controllers\User\OIIVAutoController;
// use App\Http\Controllers\User\PivotSignalController;
// use App\Http\Controllers\User\MmTrapController; // whatever your 5th one is

class CpAnalysisSignalResolver
{
    /**
     * @return array<int, array{symbol:string, action:'BUY_CE'|'BUY_PE', meta:array}>
     */
    public function resolve(CpAnalysis $analysis, string $date): array
    {
        return match ($analysis->route_name) {
            'gap-reversal'      => $this->fromGapReversal($date),
            'oi-flow-sentiment' => $this->fromOiFlowSentiment($date),
            'oiiv-pece'         => $this->fromOiivPece($date),
            // 'pivot-signal'   => $this->fromPivotSignal($date),
            // 'mm-trap'        => $this->fromMmTrap($date),
            default             => [],
        };
    }

    private function fromGapReversal(string $date): array
    {
        /** @var GapReversalController $ctrl */
        $ctrl = app(GapReversalController::class);
        $rows = $ctrl->getSignalsForDate($date); // ← extract this from analyze()

        $out = [];
        foreach ($rows as $r) {
            if ($r['setup'] === 'BUY')  $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_CE', 'meta' => $r];
            if ($r['setup'] === 'SELL') $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_PE', 'meta' => $r];
        }
        return $out;
    }

    private function fromOiFlowSentiment(string $date): array
    {
        $rows = app(\App\Services\Cp\OIFlowSentimentService::class)->getSignalsForDate($date);

        $out = [];
        foreach ($rows as $r) {
            if ($r['trade_action'] === 'BUY CE') $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_CE', 'meta' => $r];
            if ($r['trade_action'] === 'BUY PE') $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_PE', 'meta' => $r];
        }
        return $out;
    }

    private function fromOiivPece(string $date): array
    {
        /** @var OIIVAutoController $ctrl */
        $ctrl = app(OIIVAutoController::class);
        $rows = $ctrl->getSignalsForDate($date, $date); // from/to = same day

        $out = [];
        foreach ($rows as $r) {
            if ($r['trade_action'] === 'BUY CE') $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_CE', 'meta' => $r];
            if ($r['trade_action'] === 'BUY PE') $out[] = ['symbol' => $r['symbol'], 'action' => 'BUY_PE', 'meta' => $r];
        }
        return $out;
    }
}