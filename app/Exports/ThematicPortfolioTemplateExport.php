<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class ThematicPortfolioTemplateExport implements FromCollection, WithTitle
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
                'Reco Date',
                'Buy Price',
                'CMP',
                'PNL',
                'Sector'
            ]
        ]);
    }

    public function title(): string
    {
        return 'Thematic Portfolio Excel Template';
    }
}
