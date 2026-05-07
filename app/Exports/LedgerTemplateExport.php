<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class LedgerTemplateExport implements FromCollection, WithTitle
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
                'Bought Date',
                'Buy Price',
                'Qty',
                'Sold Date',
                'Sell Price',
                'Profit/Loss(%)',
                'Pooling Broker Name',
                'Pooling Broker Code',
                'Client Code'
            ]
        ]);
    }

    public function title(): string
    {
        return 'Leger Excel Template';
    }
}

