<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class GlobalStockPortfolioTemplateExport implements FromCollection, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Create your template data here
        return new Collection([
            [
                'Broker Name',
                'Stock Name',
                'Qty',
                'Buy Date',
                'Buy Price',
                'CMP',
                'Current Value',
                'Profit/Loss',
                'Sector',
                'Pooling Broker Name',
                'Pooling Broker Code',
                'Client Code'
            ]
        ]);
    }

    public function title(): string
    {
        return 'Global Stock Portfolio Template Export';
    }
}
