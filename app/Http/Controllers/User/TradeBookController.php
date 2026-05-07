<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TradeBook;
use App\Models\BrokerApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TradeBookController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  UPLOAD PAGE
    // ─────────────────────────────────────────────────────────────
    public function upload()
    {
        $pageTitle = 'Trade Book — Upload';
        $userId    = Auth::id();

        $brokers = BrokerApi::where('user_id', $userId)
            ->select('id', 'client_name', 'broker_name', 'client_type')
            ->orderBy('broker_name')
            ->get();

        $uploadHistory = TradeBook::where('trade_book.user_id', $userId)
            ->join('broker_apis', 'broker_apis.id', '=', 'trade_book.broker_api_id')
            ->select(
                'trade_book.broker_api_id',
                'trade_book.broker_name',
                'trade_book.upload_month',
                'broker_apis.client_name',
                DB::raw('COUNT(*) as total_rows'),
                DB::raw('SUM(CASE WHEN trade_book.trade_type = "buy"  THEN 1 ELSE 0 END) as buy_rows'),
                DB::raw('SUM(CASE WHEN trade_book.trade_type = "sell" THEN 1 ELSE 0 END) as sell_rows'),
                DB::raw('MIN(trade_book.trade_date) as from_date'),
                DB::raw('MAX(trade_book.trade_date) as to_date')
            )
            ->groupBy('trade_book.broker_api_id', 'trade_book.broker_name', 'trade_book.upload_month', 'broker_apis.client_name')
            ->orderByDesc('trade_book.upload_month')
            ->get();

        return view($this->activeTemplate . 'user.trade-book.upload', compact(
            'pageTitle', 'brokers', 'uploadHistory'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  PROCESS UPLOAD
    // ─────────────────────────────────────────────────────────────
    public function processUpload(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|integer|exists:broker_apis,id',
            'upload_month'  => 'required|string|regex:/^\d{4}-\d{2}$/',
            'trade_file'    => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $userId      = Auth::id();
        $brokerApiId = (int) $request->broker_api_id;
        $uploadMonth = $request->upload_month;

        $brokerApi = BrokerApi::where('id', $brokerApiId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $brokerName = $brokerApi->broker_name;

        $file     = $request->file('trade_file');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();

        try {
            [$headers, $rows] = $ext === 'csv'
                ? $this->parseCsv($filePath)
                : $this->parseXlsx($filePath);
        } catch (\Exception $e) {
            return back()->withErrors(['trade_file' => 'Failed to parse file: ' . $e->getMessage()]);
        }

        if (empty($rows)) {
            return back()->withErrors(['trade_file' => 'No data rows found in the uploaded file.']);
        }

        $colMap = $this->buildColumnMap($headers, $brokerName);

        TradeBook::where('user_id', $userId)
            ->where('broker_api_id', $brokerApiId)
            ->where('upload_month', $uploadMonth)
            ->delete();

        $inserted = 0;
        $skipped  = 0;
        $batch    = [];

        foreach ($rows as $row) {
            $data = $this->mapRow($row, $colMap, $userId, $brokerApiId, $brokerName, $uploadMonth);
            if (!$data) { $skipped++; continue; }
            $batch[] = $data;

            if (count($batch) >= 500) {
                TradeBook::insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            TradeBook::insert($batch);
            $inserted += count($batch);
        }

        $msg = "✅ Imported {$inserted} trades for {$brokerName} — {$uploadMonth}.";
        if ($skipped) $msg .= " ({$skipped} empty/invalid rows skipped)";

        return redirect()->route('trade-book.report', [
            'broker_api_id' => $brokerApiId,
            'upload_month'  => $uploadMonth,
        ])->with('success', $msg);
    }

    // ─────────────────────────────────────────────────────────────
    //  REPORT PAGE  ← now loads instantly, NO heavy computation
    // ─────────────────────────────────────────────────────────────
    public function report(Request $request)
    {
        $pageTitle = 'Trade Book — P&L Report';
        $userId    = Auth::id();

        // Only fetch broker/month options — very cheap query
        $available = TradeBook::where('trade_book.user_id', $userId)
            ->join('broker_apis', 'broker_apis.id', '=', 'trade_book.broker_api_id')
            ->select(
                'trade_book.broker_api_id',
                'trade_book.broker_name',
                'trade_book.upload_month',
                'broker_apis.client_name'
            )
            ->distinct()
            ->orderByDesc('trade_book.upload_month')
            ->get();

        $brokerOptions = [];
        foreach ($available as $a) {
            $label = $a->broker_name . ($a->client_name ? ' — ' . $a->client_name : '');
            if (!isset($brokerOptions[$a->broker_api_id])) {
                $brokerOptions[$a->broker_api_id] = ['label' => $label, 'months' => []];
            }
            $brokerOptions[$a->broker_api_id]['months'][] = $a->upload_month;
        }

        $selectedBrokerApiId = (int) $request->get('broker_api_id', $available->first()?->broker_api_id ?? 0);
        $selectedMonth       = $request->get('upload_month',  $available->first()?->upload_month  ?? '');
        $selectedBrokerName  = $brokerOptions[$selectedBrokerApiId]['label'] ?? '';

        // No heavy computation here anymore — AJAX handles it
        return view($this->activeTemplate . 'user.trade-book.report', compact(
            'pageTitle', 'brokerOptions', 'selectedBrokerApiId',
            'selectedMonth', 'selectedBrokerName'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    //  AJAX — P&L DATA  ← all heavy work happens here
    // ─────────────────────────────────────────────────────────────
    public function ajaxPnlData(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|integer',
            'upload_month'  => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $userId      = Auth::id();
        $brokerApiId = (int) $request->broker_api_id;
        $uploadMonth = $request->upload_month;

        // Verify ownership
        $owns = TradeBook::where('user_id', $userId)
            ->where('broker_api_id', $brokerApiId)
            ->where('upload_month', $uploadMonth)
            ->exists();

        if (!$owns) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Fetch only required columns — less memory, faster serialization
        $trades = TradeBook::where('user_id', $userId)
            ->where('broker_api_id', $brokerApiId)
            ->where('upload_month', $uploadMonth)
            ->select([
                'symbol', 'trade_date', 'trade_time', 'trade_type',
                'quantity', 'price', 'exchange', 'segment',
                'trade_id', 'order_id', 'expiry_date',
            ])
            ->orderBy('symbol')
            ->orderBy('trade_date')
            ->orderBy('trade_time')
            ->get();

        [$pairedTrades, $dayWisePnl, $summary] = $this->buildPnlReport($trades);

        return response()->json([
            'paired_trades' => $pairedTrades,
            'day_wise_pnl'  => $dayWisePnl,
            'summary'       => $summary,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  DELETE UPLOAD
    // ─────────────────────────────────────────────────────────────
    public function deleteUpload(Request $request)
    {
        $request->validate([
            'broker_api_id' => 'required|integer',
            'upload_month'  => 'required|string',
        ]);

        TradeBook::where('user_id', Auth::id())
            ->where('broker_api_id', $request->broker_api_id)
            ->where('upload_month', $request->upload_month)
            ->delete();

        return back()->with('success', 'Upload deleted successfully.');
    }

    // ═════════════════════════════════════════════════════════════
    //  PRIVATE — FILE PARSING
    // ═════════════════════════════════════════════════════════════

    private function parseXlsx(string $path): array
    {
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $ws          = $spreadsheet->getActiveSheet();
            $allRows     = [];
            foreach ($ws->toArray(null, true, true, false) as $row) {
                $allRows[] = array_values($row);
            }
        } else {
            $allRows = $this->parseXlsxSimple($path);
        }

        $cleaned = [];
        foreach ($allRows as $row) {
            if (!empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                $cleaned[] = $row;
            }
        }

        if (empty($cleaned)) return [[], []];
        $headers = array_shift($cleaned);
        return [$headers, $cleaned];
    }

    private function parseXlsxSimple(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) throw new \Exception('Cannot open xlsx file');

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $text = '';
                if (isset($si->t)) { $text = (string) $si->t; }
                elseif (isset($si->r)) { foreach ($si->r as $r) $text .= (string) $r->t; }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheetXml) throw new \Exception('Cannot read worksheet');

        $ws = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($ws->sheetData->row as $row) {
            $rowData = [];
            $prevCol = -1;
            foreach ($row->c as $cell) {
                $ref    = (string) $cell['r'];
                $colStr = preg_replace('/[0-9]/', '', $ref);
                $colIdx = $this->colLetterToIndex($colStr);

                while ($prevCol < $colIdx - 1) { $rowData[] = null; $prevCol++; }

                $type  = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : null;

                if ($value !== null) {
                    if ($type === 's')       $value = $sharedStrings[(int) $value] ?? '';
                    elseif ($type === 'b')   $value = $value === '1';
                    elseif (is_numeric($value)) $value = (float) $value;
                }

                $rowData[] = $value;
                $prevCol   = $colIdx;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function colLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index   = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    private function parseCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');
        while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
        fclose($handle);

        if (empty($rows)) return [[], []];
        $headers = array_shift($rows);
        return [$headers, $rows];
    }

    // ═════════════════════════════════════════════════════════════
    //  PRIVATE — COLUMN MAPPING
    // ═════════════════════════════════════════════════════════════

    private function buildColumnMap(array $headers, string $brokerName): array
    {
        $lookup = [
            'symbol'               => ['symbol', 'scrip', 'instrument', 'scrip name', 'script name'],
            'trade_date'           => ['trade date', 'date', 'txn date', 'transaction date'],
            'exchange'             => ['exchange', 'exch'],
            'segment'              => ['segment'],
            'trade_type'           => ['trade type', 'transaction type', 'buy/sell', 'b/s', 'type'],
            'quantity'             => ['quantity', 'qty'],
            'price'                => ['price', 'trade price', 'rate', 'net rate'],
            'trade_id'             => ['trade id', 'trade no', 'trade number'],
            'order_id'             => ['order id', 'order no', 'order number'],
            'order_execution_time' => ['order execution time', 'execution time', 'time'],
        ];

        $map           = [];
        $normalHeaders = array_map(fn($h) => strtolower(trim((string) $h)), $headers);

        foreach ($lookup as $field => $aliases) {
            foreach ($normalHeaders as $idx => $h) {
                if (in_array($h, $aliases)) { $map[$field] = $idx; break; }
            }
        }

        if (!isset($map['expiry_date'])) {
            $map['expiry_date'] = count($headers) - 1;
        }

        return $map;
    }

    private function mapRow(array $row, array $colMap, int $userId, int $brokerApiId, string $brokerName, string $uploadMonth): ?array
    {
        $get = fn(string $field) => isset($colMap[$field]) && array_key_exists($colMap[$field], $row)
            ? $row[$colMap[$field]] : null;

        $symbol    = trim((string) ($get('symbol') ?? ''));
        $tradeType = strtolower(trim((string) ($get('trade_type') ?? '')));
        $quantity  = (float) ($get('quantity') ?? 0);
        $price     = (float) ($get('price') ?? 0);
        $rawDate   = $get('trade_date');

        if (!$symbol || !in_array($tradeType, ['buy', 'sell']) || $quantity <= 0 || $price <= 0) return null;

        $tradeDate = $this->parseDate($rawDate);
        if (!$tradeDate) return null;

        $execRaw   = (string) ($get('order_execution_time') ?? '');
        $tradeTime = null;
        if ($execRaw) {
            if (preg_match('/T(\d{2}:\d{2}:\d{2})/', $execRaw, $m))       $tradeTime = $m[1];
            elseif (preg_match('/(\d{2}:\d{2}(?::\d{2})?)/', $execRaw, $m)) $tradeTime = $m[1];
        }

        $expiryDate = $this->parseDate($get('expiry_date'));
        $now        = now()->toDateTimeString();

        return [
            'user_id'              => $userId,
            'broker_api_id'        => $brokerApiId,
            'broker_name'          => $brokerName,
            'upload_month'         => $uploadMonth,
            'symbol'               => $symbol,
            'trade_date'           => $tradeDate,
            'trade_time'           => $tradeTime,
            'trade_type'           => $tradeType,
            'quantity'             => $quantity,
            'price'                => $price,
            'exchange'             => trim((string) ($get('exchange') ?? '')),
            'segment'              => trim((string) ($get('segment') ?? '')),
            'trade_id'             => trim((string) ($get('trade_id') ?? '')),
            'order_id'             => trim((string) ($get('order_id') ?? '')),
            'order_execution_time' => $execRaw ?: null,
            'expiry_date'          => $expiryDate,
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->format('Y-m-d');

        $s = trim((string) $value);
        if (!$s) return null;

        // Excel serial number
        if (is_numeric($s) && (int) $s > 1000 && (int) $s < 200000) {
            try { return Carbon::createFromTimestamp(((int) $s - 25569) * 86400)->format('Y-m-d'); }
            catch (\Exception $e) {}
        }

        $formats = ['Y-m-d', 'd-m-Y', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd M Y', 'M d, Y', 'd-M-Y'];
        foreach ($formats as $fmt) {
            try { return Carbon::createFromFormat($fmt, $s)->format('Y-m-d'); }
            catch (\Exception $e) {}
        }

        try { return Carbon::parse($s)->format('Y-m-d'); }
        catch (\Exception $e) {}

        return null;
    }

    /**
     * Safely format any date value (Carbon, string, DateTime) to a display string.
     * Prevents "Illegal offset type" when Eloquent returns Carbon objects from $casts.
     */
    private function toDateStr($value, string $format): string
    {
        if (!$value) return '';
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->format($format);
        return Carbon::parse((string) $value)->format($format);
    }

    /**
     * Normalize any date value to a plain Y-m-d string (or null).
     * Handles: Carbon object, DateTime, "Y-m-d", "Y-m-d H:i:s", etc.
     */
    private function normDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        $s = trim((string) $value);
        // Already Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        try { return Carbon::parse($s)->format('Y-m-d'); } catch (\Exception $e) {}
        return null;
    }

    // ═════════════════════════════════════════════════════════════
    //  PRIVATE — P&L ENGINE (optimized FIFO)
    // ═════════════════════════════════════════════════════════════

    private function buildPnlReport($trades): array
    {
        // ── Step 1: Extract ALL data from Eloquent into plain PHP arrays ──
        // This is the ONLY place we touch Eloquent model properties.
        // normDate() converts Carbon objects / any date format → "Y-m-d" string.
        // After this, zero Eloquent objects exist in the pipeline — no Carbon
        // object can sneak in as an array key anywhere downstream.
        $rawLegs = [];
        foreach ($trades as $t) {
            $rawLegs[] = [
                'symbol'      => (string) $t->symbol,
                'trade_type'  => (string) $t->trade_type,
                'trade_date'  => $this->normDate($t->trade_date)  ?? '',
                'trade_time'  => (string) ($t->trade_time ?? ''),
                'price'       => (float)  $t->price,
                'quantity'    => (float)  $t->quantity,
                'exchange'    => (string) ($t->exchange    ?? ''),
                'segment'     => (string) ($t->segment     ?? ''),
                'expiry_date' => $this->normDate($t->expiry_date),   // string|null
                'order_id'    => (string) ($t->order_id   ?? ''),
            ];
        }

        // ── Step 2: Club ALL fills of same symbol on same day into 1 leg ──
        // Group key = symbol | trade_type | date
        // Different prices / times on the same day → weighted-average price,
        // earliest time shown, total quantity summed.
        $grouped = [];
        foreach ($rawLegs as $leg) {
            $key = $leg['symbol'] . '|' . $leg['trade_type'] . '|' . $leg['trade_date'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'symbol'        => $leg['symbol'],
                    'trade_type'    => $leg['trade_type'],
                    'trade_date'    => $leg['trade_date'],
                    'trade_time'    => $leg['trade_time'],   // earliest time (first seen)
                    'quantity'      => 0.0,
                    'total_value'   => 0.0,                  // sum(qty*price) for wavg
                    'exchange'      => $leg['exchange'],
                    'segment'       => $leg['segment'],
                    'expiry_date'   => $leg['expiry_date'],
                    'order_id'      => $leg['order_id'],
                ];
            }

            $grouped[$key]['quantity']    += $leg['quantity'];
            $grouped[$key]['total_value'] += $leg['quantity'] * $leg['price'];

            // Keep earliest time across all fills
            if (
                $leg['trade_time'] &&
                (!$grouped[$key]['trade_time'] || $leg['trade_time'] < $grouped[$key]['trade_time'])
            ) {
                $grouped[$key]['trade_time'] = $leg['trade_time'];
            }
        }

        // Compute weighted-average price for each grouped leg
        foreach ($grouped as &$g) {
            $g['price'] = $g['quantity'] > 0
                ? round($g['total_value'] / $g['quantity'], 4)
                : 0.0;
            unset($g['total_value']);
        }
        unset($g);

        // ── Step 3: Re-bucket grouped legs by symbol ──────────────────
        $bySymbol = [];
        foreach ($grouped as $leg) {
            $bySymbol[$leg['symbol']][] = $leg;
        }

        // Sort each symbol's legs by date then time (FIFO order)
        foreach ($bySymbol as &$legs) {
            usort($legs, fn($a, $b) =>
                strcmp($a['trade_date'] . $a['trade_time'], $b['trade_date'] . $b['trade_time'])
            );
        }
        unset($legs);

        $pairedTrades = [];
        $dayPnl       = [];

        foreach ($bySymbol as $symbol => $symLegs) {
            // Split into buy/sell arrays of grouped legs
            $buys  = array_values(array_filter($symLegs, fn($l) => $l['trade_type'] === 'buy'));
            $sells = array_values(array_filter($symLegs, fn($l) => $l['trade_type'] === 'sell'));

            $bi            = 0;
            $si            = 0;
            $bCount        = count($buys);
            $sCount        = count($sells);
            $remainBuyQty  = $bCount > 0 ? $buys[0]['quantity']  : 0.0;
            $remainSellQty = $sCount > 0 ? $sells[0]['quantity'] : 0.0;

            // ── FIFO match ───────────────────────────────────────
            while ($bi < $bCount && $si < $sCount) {
                $buy  = $buys[$bi];
                $sell = $sells[$si];

                $matchQty  = min($remainBuyQty, $remainSellQty);
                $buyPrice  = $buy['price'];
                $sellPrice = $sell['price'];
                $buyValue  = round($matchQty * $buyPrice,  2);
                $sellValue = round($matchQty * $sellPrice, 2);
                $pnl       = round($sellValue - $buyValue, 2);
                $pnlPct    = $buyValue > 0 ? round(($pnl / $buyValue) * 100, 2) : 0;

                $buyDateStr  = $buy['trade_date'];
                $sellDateStr = $sell['trade_date'];
                $isIntraday  = $buyDateStr === $sellDateStr;
                $holdingDays = $isIntraday ? 0 : (int) round(
                    (strtotime($sellDateStr) - strtotime($buyDateStr)) / 86400
                );
                $typeLabel = $isIntraday ? 'Intraday' : 'Positional';

                $pairedTrades[] = [
                    'symbol'           => $symbol,
                    'exchange'         => $buy['exchange'],
                    'segment'          => $buy['segment'],
                    'expiry_date'      => $buy['expiry_date']
                        ? $this->toDateStr($buy['expiry_date'], 'd M Y') : null,
                    'buy_date_raw'     => $buyDateStr,
                    'buy_date'         => $this->toDateStr($buyDateStr, 'd M Y'),
                    'buy_time'         => $buy['trade_time'],
                    'buy_qty'          => $matchQty,
                    'buy_price'        => $buyPrice,
                    'buy_value'        => $buyValue,
                    'buy_order_id'     => $buy['order_id'],
                    'sell_date'        => $this->toDateStr($sellDateStr, 'd M Y'),
                    'sell_time'        => $sell['trade_time'],
                    'sell_qty'         => $matchQty,
                    'sell_price'       => $sellPrice,
                    'sell_value'       => $sellValue,
                    'sell_order_id'    => $sell['order_id'],
                    'pnl'              => $pnl,
                    'pnl_pct'          => $pnlPct,
                    'holding_days'     => $holdingDays,
                    'trade_type_label' => $typeLabel,
                    'is_profit'        => $pnl >= 0,
                ];

                if (!isset($dayPnl[$sellDateStr])) {
                    $dayPnl[$sellDateStr] = ['realized' => 0.0, 'trades' => 0, 'winners' => 0, 'losers' => 0];
                }
                $dayPnl[$sellDateStr]['realized'] += $pnl;
                $dayPnl[$sellDateStr]['trades']++;
                $pnl >= 0 ? $dayPnl[$sellDateStr]['winners']++ : $dayPnl[$sellDateStr]['losers']++;

                $remainBuyQty  -= $matchQty;
                $remainSellQty -= $matchQty;

                if ($remainBuyQty <= 0.001) {
                    $bi++;
                    $remainBuyQty = $bi < $bCount ? $buys[$bi]['quantity'] : 0.0;
                }
                if ($remainSellQty <= 0.001) {
                    $si++;
                    $remainSellQty = $si < $sCount ? $sells[$si]['quantity'] : 0.0;
                }
            }

            // ── Unmatched buys = open positions ─────────────────
            while ($bi < $bCount) {
                $buy     = $buys[$bi];
                $openQty = $remainBuyQty > 0.001 ? $remainBuyQty : $buy['quantity'];

                if ($openQty > 0.001) {
                    $pairedTrades[] = [
                        'symbol'           => $symbol,
                        'exchange'         => $buy['exchange'],
                        'segment'          => $buy['segment'],
                        'expiry_date'      => $buy['expiry_date']
                            ? $this->toDateStr($buy['expiry_date'], 'd M Y') : null,
                        'buy_date_raw'     => $buy['trade_date'],
                        'buy_date'         => $this->toDateStr($buy['trade_date'], 'd M Y'),
                        'buy_time'         => $buy['trade_time'],
                        'buy_qty'          => $openQty,
                        'buy_price'        => $buy['price'],
                        'buy_value'        => round($openQty * $buy['price'], 2),
                        'buy_order_id'     => $buy['order_id'],
                        'sell_date'        => null,
                        'sell_time'        => null,
                        'sell_qty'         => null,
                        'sell_price'       => null,
                        'sell_value'       => null,
                        'sell_order_id'    => null,
                        'pnl'              => null,
                        'pnl_pct'          => null,
                        'holding_days'     => null,
                        'trade_type_label' => 'Open',
                        'is_profit'        => null,
                    ];
                }

                $bi++;
                $remainBuyQty = $bi < $bCount ? $buys[$bi]['quantity'] : 0.0;
            }
        }

        // ── Sort final trade list by buy_date ascending ─────────────
        usort($pairedTrades, fn($a, $b) => strcmp($a['buy_date_raw'] ?? '', $b['buy_date_raw'] ?? ''));

        // ── Day-wise sorted ascending ────────────────────────────
        ksort($dayPnl);
        $dayWisePnl = [];
        foreach ($dayPnl as $date => $data) {
            $dayWisePnl[] = [
                'date'     => Carbon::parse($date)->format('d M Y (D)'),
                'date_raw' => $date,
                'realized' => round($data['realized'], 2),
                'trades'   => $data['trades'],
                'winners'  => $data['winners'],
                'losers'   => $data['losers'],
            ];
        }

        // ── Summary ──────────────────────────────────────────────
        $closed     = array_filter($pairedTrades, fn($t) => !is_null($t['pnl']));
        $pnlValues  = array_column($closed, 'pnl');

        $totalPnl   = round(array_sum($pnlValues), 2);
        $winners    = array_filter($pnlValues, fn($p) => $p >= 0);
        $losers     = array_filter($pnlValues, fn($p) => $p < 0);
        $winCount   = count($winners);
        $lossCount  = count($losers);
        $avgWin     = $winCount  > 0 ? round(array_sum($winners) / $winCount,  2) : 0;
        $avgLoss    = $lossCount > 0 ? round(array_sum($losers)  / $lossCount, 2) : 0;
        $bestTrade  = !empty($pnlValues) ? max($pnlValues) : 0;
        $worstTrade = !empty($pnlValues) ? min($pnlValues) : 0;
        $openCount  = count(array_filter($pairedTrades, fn($t) => is_null($t['pnl'])));
        $winRate    = ($winCount + $lossCount) > 0
            ? round(($winCount / ($winCount + $lossCount)) * 100, 1) : 0;
        $rr = ($avgLoss != 0) ? round(abs($avgWin / $avgLoss), 2) : null;

        $typeLabels  = array_column($pairedTrades, 'trade_type_label');
        $intradayCount   = count(array_filter($typeLabels, fn($l) => $l === 'Intraday'));
        $positionalCount = count(array_filter($typeLabels, fn($l) => $l === 'Positional'));

        $summary = [
            'total_trades'   => count($closed),
            'total_pnl'      => $totalPnl,
            'winners'        => $winCount,
            'losers'         => $lossCount,
            'win_rate'       => $winRate,
            'avg_win'        => $avgWin,
            'avg_loss'       => $avgLoss,
            'best_trade'     => $bestTrade,
            'worst_trade'    => $worstTrade,
            'open_positions' => $openCount,
            'reward_risk'    => $rr,
            'intraday'       => $intradayCount,
            'positional'     => $positionalCount,
        ];

        return [$pairedTrades, $dayWisePnl, $summary];
    }
}