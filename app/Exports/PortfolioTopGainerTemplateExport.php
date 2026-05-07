<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class PortfolioTopGainerTemplateExport implements FromCollection, WithTitle
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
                'Avg Price',
                'CMP',
                'Change%',
            ]
        ]);
    }

    public function title(): string
    {
        return 'Portfolio Top Gainer Excel Template';
    }
}
