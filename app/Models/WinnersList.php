<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WinnersList extends Model
{
    use HasFactory;

    protected $fillable = [
        'banca_id',
        'fake_winners',
        'fake_premio',
        'sort_date',
        'json'
    ];

    /**
     * Get the partner that owns the winners list.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'banca_id');
    }
}
