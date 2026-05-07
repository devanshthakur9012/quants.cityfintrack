<?php

namespace App\Imports;

use App\Models\GlobalStockPortfolio;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\PoolingAccountPortfolio;
use App\Models\User;
use Carbon\Carbon;

class GlobalStockPortfolioDataImport implements ToCollection, WithHeadingRow
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

        // Filter out rows with at least one non-empty cell
        $filteredRows = $rows->filter(function ($row) {
            return collect($row)->filter(function ($cell) {
                return !empty($cell);
            })->count() > 0;
        });

        try {
            // Process each row of data
            foreach ($filteredRows as $row) {
                DB::beginTransaction();

                if (empty($row['client_code'])) {
                    DB::commit();
                    continue;
                }

                $user = User::select('id', 'user_code')->where('user_code', $row['client_code'])->first();

                if (empty($user)) {
                    continue;
                }

                if (empty($row['pooling_broker_name']) && empty($row['pooling_broker_code'])) {
                    DB::commit();
                    continue;
                }

                if (!empty($row['pooling_broker_code'])) {
                    $poolingBrokerPortfolio = PoolingAccountPortfolio::where('broker_code', $row['pooling_broker_code'])->first();

                    if (empty($poolingBrokerPortfolio) && !empty($row['pooling_broker_name']) && !empty($row['pooling_broker_code'])) {
                        $poolingBrokerPortfolio = new PoolingAccountPortfolio();
                        $poolingBrokerPortfolio->broker_name = $row['pooling_broker_name'];
                        $poolingBrokerPortfolio->broker_code = $row['pooling_broker_code'];
                        $poolingBrokerPortfolio->user_id = $user->id;
                        $poolingBrokerPortfolio->save();
                    }
                } else {
                    $poolingBrokerPortfolio = new PoolingAccountPortfolio();
                    $poolingBrokerPortfolio->broker_name = $row['pooling_broker_name'];
                    $poolingBrokerPortfolio->broker_code = $this->uniquePoolingBrokerCode();
                    $poolingBrokerPortfolio->user_id = $user->id;
                    $poolingBrokerPortfolio->save();
                }

                if (empty($poolingBrokerPortfolio)) {
                    continue;
                }

                $ddtTime = \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['buy_date']));
                // Create and save the Stock model instance with the data
                $globalStockPortfolio = new GlobalStockPortfolio();
                $globalStockPortfolio->broker_name = $row['broker_name'] ?: null;
                $globalStockPortfolio->stock_name = $row['stock_name'] ?: null;
                $globalStockPortfolio->quantity = $row['qty'] ?: 0;
                $globalStockPortfolio->buy_date = $row['buy_date'] ? date("Y-m-d",strtotime($ddtTime)) : null;
                $globalStockPortfolio->buy_price = $row['buy_price'] ?: 0;
                $globalStockPortfolio->cmp = $row['cmp'] ?: 0;
                $globalStockPortfolio->current_value = $row['current_value'] ?: 0;
                $globalStockPortfolio->profit_loss = $row['profitloss'] ?: null;
                $globalStockPortfolio->sector = $row['sector'];
                $globalStockPortfolio->pooling_account_id = $poolingBrokerPortfolio->id;
                $globalStockPortfolio->user_id = $user->id;
                $globalStockPortfolio->save();

                DB::commit();
            }
        } catch (\Throwable $th) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $th;
        }
    }

    // create method to create a unique pooling broker code with timestamp string with seconds.
    public function uniquePoolingBrokerCode()
    {
        $poolingBrokerCode = 'PB' . Carbon::now()->format('YmdHisv');
        $poolingBrokerPortfolio = PoolingAccountPortfolio::where('broker_code', $poolingBrokerCode)->first();
        if ($poolingBrokerPortfolio) {
            $this->uniquePoolingBrokerCode();
        } else {
            return $poolingBrokerCode;
        }
    }
}
