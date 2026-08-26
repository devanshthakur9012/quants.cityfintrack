<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Cp\OIFlowMultiTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OI Flow Sentiment — Multi Snapshot. ALL logic lives in
 * OIFlowMultiTimeService now — this controller only reads the request
 * and calls the service. CpMultiTimeOrderPlacementService (the cron)
 * calls the SAME service's getSignalsForDate() — one source of truth
 * for the OI comparison math, shared by both paths.
 */
class OIFlowMultiTimeController extends Controller
{
    public function __construct(private OIFlowMultiTimeService $service) {}

    public function index()
    {
        $pageTitle = 'OI Flow Sentiment — Multi Snapshot';
        return view(activeTemplate() . 'user.oi-flow-multi-time.index', compact('pageTitle'));
    }

    public function lastDate(Request $request): JsonResponse
    {
        return response()->json($this->service->lastDate());
    }

    public function getSymbols(Request $request): JsonResponse
    {
        return response()->json($this->service->symbols());
    }

    public function analyze(Request $request): JsonResponse
    {
        $date     = $request->get('date');
        $fromDate = $date ?? $request->get('from_date');
        $toDate   = $date ?? $request->get('to_date');

        if (!$fromDate || !$toDate) {
            return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
        }

        $symbolReq    = array_filter((array) $request->get('symbols', []));
        $actionFilter = (string) $request->get('filter_action', '');

        return response()->json($this->service->analyze($fromDate, $toDate, $symbolReq, $actionFilter));
    }
}