<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class TransactionTemplateExport implements FromCollection, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Create your template data here
        return new Collection([
            [
                'Stock Name',
                'Txn Date',
                'Trade Type',
                'Qty',
                'Trx',
                'Amount',
                'Pooling Broker Name',
                'Pooling Broker Code',
                'Client Code'
            ]
        ]);
    }

    public function title(): string
    {
        return 'Transaction Excel Template';
    }
}
