<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SymbolList extends Model
{
    use HasFactory;
    
    protected $table = 'symbol_lists';

    protected $fillable = [
        'underlying',
        'symbol',
    ];
 
    public function analysisConfigs()
    {
        return $this->belongsToMany(AnalysisConfig::class, 'analysis_config_symbols', 'symbol_list_id', 'analysis_config_id');
    }
}
