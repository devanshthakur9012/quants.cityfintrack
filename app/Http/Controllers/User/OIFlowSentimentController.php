<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\Cp\OIFlowSentimentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OI Flow Sentiment Analyzer — thin HTTP adapter.
 * ALL logic lives in OIFlowSentimentService now. This class only reads
 * the request, calls the service, returns JSON — nothing here computes
 * anything, so it can never drift from what the auto-order cron sees.
 */
class OIFlowSentimentController extends Controller
{
    public function __construct(private OIFlowSentimentService $service) {}

    public function index()
    {
        $pageTitle = 'OI Flow Sentiment Analyzer';
        return view(activeTemplate() . 'user.oi-flow-sentiment.index', compact('pageTitle'));
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

        $result = $this->service->analyze($fromDate, $toDate, $symbolReq, $actionFilter);

        return response()->json($result, isset($result['success']) && !$result['success'] && !isset($result['no_config']) && !isset($result['message']) ? 500 : 200);
    }
}