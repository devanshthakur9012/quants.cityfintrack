<?php
// FILE: app/Http/Controllers/User/CpDataExportController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AnalysisConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ZipArchive;

class CpDataExportController extends Controller
{
    // Edit this if your getStrikePosition() labels differ (e.g. ITM1/OTM1)
    private const STRIKE_POSITIONS = ['ATM-1', 'ATM', 'ATM+1'];
    private const TIMEFRAMES       = ['15min', '30min', '1hr'];
    private const SUB_COLS         = ['Strike', 'Open', 'High', 'Low', 'Close', 'Volume', 'OI'];

    public function index()
    {
        $pageTitle = 'Data Export';

        $config = AnalysisConfig::where('is_active', true)
            ->with('symbols:id,symbol')
            ->first();

        $symbols = $config
            ? $config->symbols->pluck('symbol')->unique()->values()->all()
            : [];

        return view(activeTemplate() . 'user.cp.data-export.index', compact('pageTitle', 'symbols'));
    }

    public function download(Request $request)
    {
        $request->validate([
            'timeframe' => 'required|in:15min,30min,1hr',
            'symbol'    => 'required|string',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $timeframe = $request->timeframe;
        $symbol    = strtoupper($request->symbol);
        $dateFrom  = Carbon::parse($request->date_from)->toDateString();
        $dateTo    = Carbon::parse($request->date_to)->toDateString();

        $stockTable  = "cp_stock_ohlc_{$timeframe}";
        $futTable    = "cp_fut_ohlc_{$timeframe}";
        $optionTable = "cp_option_ohlc_{$timeframe}";

        $workDir = storage_path('app/tmp/cp-export-' . uniqid());
        if (!is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        // Clean up everything in workDir once the response has been sent
        register_shutdown_function(function () use ($workDir) {
            if (is_dir($workDir)) {
                foreach (glob("$workDir/*") as $f) {
                    @unlink($f);
                }
                @rmdir($workDir);
            }
        });

        try {
            $xlsxName = "{$symbol}_{$timeframe}_{$dateFrom}_to_{$dateTo}.xlsx";
            $xlsxPath = $workDir . '/' . $xlsxName;

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);

            $this->buildStockSheet($spreadsheet, $stockTable, $symbol, $dateFrom, $dateTo);
            $this->buildFutSheet($spreadsheet, $futTable, $symbol, $dateFrom, $dateTo);
            $this->buildOptionSheet($spreadsheet, $optionTable, $symbol, $dateFrom, $dateTo, 'CE');
            $this->buildOptionSheet($spreadsheet, $optionTable, $symbol, $dateFrom, $dateTo, 'PE');

            $spreadsheet->setActiveSheetIndex(0);

            (new Xlsx($spreadsheet))->save($xlsxPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $zipName = "{$symbol}_{$timeframe}_export.zip";
            $zipPath = $workDir . '/' . $zipName;

            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($xlsxPath, $xlsxName);
            $zip->close();

            // shutdown function above deletes the whole workDir after the file is streamed
            return response()->download($zipPath, $zipName)->deleteFileAfterSend(false);
        } catch (\Throwable $e) {
            Log::error('CpDataExport: ' . $e->getMessage());
            $notify[] = ['error', 'Export failed: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Sheet builders
    // ─────────────────────────────────────────────────────────────────────

    private function buildStockSheet(Spreadsheet $ss, string $table, string $symbol, string $from, string $to): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Stock');

        $headers = ['Date', 'Time', 'Open', 'High', 'Low', 'Close', 'Volume'];
        $this->writeSimpleHeader($sheet, $headers);

        $rows = DB::table($table)
            ->where('symbol', $symbol)
            ->whereDate('trade_date', '>=', $from)
            ->whereDate('trade_date', '<=', $to)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['trade_date', 'interval_time', 'open', 'high', 'low', 'close', 'volume']);

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                Carbon::parse($row->trade_date)->toDateString(),
                Carbon::parse($row->interval_time)->format('H:i'),
                (float) $row->open,
                (float) $row->high,
                (float) $row->low,
                (float) $row->close,
                (int) $row->volume,
            ], null, "A{$r}");
            $r++;
        }

        $this->autoSize($sheet, count($headers));
    }

    private function buildFutSheet(Spreadsheet $ss, string $table, string $symbol, string $from, string $to): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Futures');

        $headers = ['Date', 'Time', 'Expiry', 'ATM Strike', 'Open', 'High', 'Low', 'Close', 'Volume', 'OI'];
        $this->writeSimpleHeader($sheet, $headers);

        $rows = DB::table($table)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', '>=', $from)
            ->whereDate('trade_date', '<=', $to)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['trade_date', 'interval_time', 'expiry_date', 'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi']);

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                Carbon::parse($row->trade_date)->toDateString(),
                Carbon::parse($row->interval_time)->format('H:i'),
                $row->expiry_date,
                (float) $row->atm_strike,
                (float) $row->open,
                (float) $row->high,
                (float) $row->low,
                (float) $row->close,
                (int) $row->volume,
                (int) $row->oi,
            ], null, "A{$r}");
            $r++;
        }

        $this->autoSize($sheet, count($headers));
    }

    private function buildOptionSheet(Spreadsheet $ss, string $table, string $symbol, string $from, string $to, string $type): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Option ' . $type);

        $rows = DB::table($table)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', '>=', $from)
            ->whereDate('trade_date', '<=', $to)
            ->whereIn('strike_position', self::STRIKE_POSITIONS)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['trade_date', 'interval_time', 'expiry_date', 'strike_position', 'strike', 'open', 'high', 'low', 'close', 'volume', 'oi']);

        // Pivot into one row per date+time, columns grouped by strike position
        $pivot = [];
        foreach ($rows as $row) {
            $key = $row->trade_date . '|' . $row->interval_time;
            $pivot[$key]['date']   = $row->trade_date;
            $pivot[$key]['time']   = $row->interval_time;
            $pivot[$key]['expiry'] = $row->expiry_date;
            $pivot[$key]['pos'][$row->strike_position] = $row;
        }
        ksort($pivot);

        $positions    = self::STRIKE_POSITIONS;
        $colsPerGroup = count(self::SUB_COLS);

        // Header row 1: Date/Time/Expiry (merged 2 rows) + merged group labels
        $sheet->setCellValue('A1', 'Date');
        $sheet->setCellValue('B1', 'Time');
        $sheet->setCellValue('C1', 'Expiry');
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');

        $col = 4; // D
        foreach ($positions as $pos) {
            $startLetter = Coordinate::stringFromColumnIndex($col);
            $endLetter   = Coordinate::stringFromColumnIndex($col + $colsPerGroup - 1);
            $sheet->setCellValue("{$startLetter}1", $pos);
            $sheet->mergeCells("{$startLetter}1:{$endLetter}1");

            foreach (self::SUB_COLS as $i => $label) {
                $letter = Coordinate::stringFromColumnIndex($col + $i);
                $sheet->setCellValue("{$letter}2", $label);
            }
            $col += $colsPerGroup;
        }

        $totalCols = $col - 1;
        $this->styleHeader($sheet, $totalCols, 1, 2);

        // Data rows
        $r = 3;
        foreach ($pivot as $data) {
            $sheet->setCellValue("A{$r}", Carbon::parse($data['date'])->toDateString());
            $sheet->setCellValue("B{$r}", Carbon::parse($data['time'])->format('H:i'));
            $sheet->setCellValue("C{$r}", $data['expiry']);

            $col = 4;
            foreach ($positions as $pos) {
                $opt  = $data['pos'][$pos] ?? null;
                $vals = $opt
                    ? [(float) $opt->strike, (float) $opt->open, (float) $opt->high, (float) $opt->low, (float) $opt->close, (int) $opt->volume, (int) $opt->oi]
                    : ['—', '—', '—', '—', '—', '—', '—'];

                foreach ($vals as $i => $v) {
                    $letter = Coordinate::stringFromColumnIndex($col + $i);
                    $sheet->setCellValue("{$letter}{$r}", $v);
                }
                $col += $colsPerGroup;
            }
            $r++;
        }

        $sheet->freezePane('D3');
        $this->autoSize($sheet, $totalCols);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Style helpers
    // ─────────────────────────────────────────────────────────────────────

    private function writeSimpleHeader($sheet, array $headers): void
    {
        foreach ($headers as $i => $h) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$letter}1", $h);
        }
        $this->styleHeader($sheet, count($headers), 1, 1);
        $sheet->freezePane('A2');
    }

    private function styleHeader($sheet, int $totalCols, int $fromRow, int $toRow): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);
        $sheet->getStyle("A{$fromRow}:{$lastCol}{$toRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667EEA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
    }

    private function autoSize($sheet, int $totalCols): void
    {
        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }
}