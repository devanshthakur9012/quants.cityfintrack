<?php

namespace App\Imports;

use App\Models\ThematicPortfolio;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ThematicPortfolioDataImport implements ToCollection, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection $rows)
    {
        // Check if the collection is empty or has no data rows
        if ($rows->isEmpty()) {
            // Handle the case when there is no data in the Excel file
            throw new \Exception('The Excel file does not contain any data.');
        }

        try {
            // Filter out rows with at least one non-empty cell
            $filteredRows = $rows->filter(function ($row) {
                return collect($row)->filter(function ($cell) {
                    return !empty($cell);
                })->count() > 0;
            });

            // Process each row of data
            foreach ($filteredRows as $row) {
                DB::beginTransaction();
                // Create and save the Stock model instance with the data
                $thematicPortfolio = new ThematicPortfolio();
                $thematicPortfolio->stock_name = $row['stock_name'] ?: null;
                $thematicPortfolio->reco_date = $row['reco_date'] ?: null;
                $thematicPortfolio->buy_price = $row['buy_price'] ?: 0;
                $thematicPortfolio->cmp = $row['cmp'] ?: 0;
                $thematicPortfolio->pnl = $row['pnl'] ?: 0;
                $thematicPortfolio->sector = $row['sector'] ?: null;
                $thematicPortfolio->save();
                DB::commit();
            }

        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $th;
        }
    }
}
