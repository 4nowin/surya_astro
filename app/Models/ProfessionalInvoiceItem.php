<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessionalInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'professional_invoice_items';

    protected $fillable = [
        'invoice_id',
        'service_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * Parent invoice.
     */
    public function invoice()
    {
        return $this->belongsTo(ProfessionalInvoice::class, 'invoice_id');
    }

    /**
     * Optional linked catalogue service.
     */
    public function service()
    {
        return $this->belongsTo(ProfessionalService::class, 'service_id');
    }

    /**
     * Automatically recalculate line total before save.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $item) {
            $item->line_total = round((float) $item->quantity * (float) $item->unit_price, 2);
        });
    }
}
