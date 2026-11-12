<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProfessionalDocumentSetting extends Model
{
    use HasFactory;

    protected $table = 'professional_document_settings';

    protected $fillable = [
        'admin_id',
        'business_name',
        'designation',
        'business_address',
        'phone',
        'email',
        'website',
        'logo_url',
        'gstin',
        'pan',
        'tax_enabled',
        'tax_name',
        'default_tax_rate',
        'is_tax_inclusive',
        'place_of_supply',
        'invoice_prefix',
        'receipt_prefix',
        'invoice_notes',
        'invoice_terms',
    ];

    protected $casts = [
        'tax_enabled' => 'boolean',
        'is_tax_inclusive' => 'boolean',
        'default_tax_rate' => 'decimal:2',
    ];

    /**
     * Professional owner.
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Retrieve or instantiate default document settings for a practitioner.
     */
    public static function getOrCreateForAdmin(Admin $admin): self
    {
        return self::firstOrCreate(
            ['admin_id' => $admin->id],
            [
                'business_name' => $admin->name,
                'designation' => $admin->role ?? 'Spiritual Consultant',
                'phone' => $admin->phone,
                'email' => $admin->email,
                'invoice_prefix' => 'INV',
                'receipt_prefix' => 'REC',
                'tax_enabled' => false,
                'tax_name' => 'GST',
                'default_tax_rate' => 18.00,
                'is_tax_inclusive' => false,
            ]
        );
    }

    /**
     * Compute current Indian Financial Year (e.g., "2026-27").
     */
    public static function getFinancialYear(?Carbon $date = null): string
    {
        $d = $date ?? now();
        $year = (int) $d->format('Y');
        $month = (int) $d->format('n');

        if ($month >= 4) {
            $start = $year;
            $end = ($year + 1) % 100;
        } else {
            $start = $year - 1;
            $end = $year % 100;
        }

        return sprintf('%04d-%02d', $start, $end);
    }

    /**
     * Atomically generate the next sequential invoice number for a practitioner.
     * Format: {PREFIX}/{FY}/{0001} (e.g. INV/2026-27/0001)
     */
    public static function generateNextInvoiceNumber(Admin $admin, ?Carbon $date = null): string
    {
        return DB::transaction(function () use ($admin, $date) {
            $settings = self::getOrCreateForAdmin($admin);
            $prefix = strtoupper(trim($settings->invoice_prefix ?: 'INV'));
            $fy = self::getFinancialYear($date);
            $searchPattern = "{$prefix}/{$fy}/%";

            // Lock invoices table for this practitioner to prevent concurrent sequence collisions
            $latest = ProfessionalInvoice::where('admin_id', $admin->id)
                ->where('invoice_number', 'LIKE', $searchPattern)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('invoice_number');

            $nextSeq = 1;
            if ($latest) {
                $parts = explode('/', $latest);
                $lastNum = (int) end($parts);
                if ($lastNum > 0) {
                    $nextSeq = $lastNum + 1;
                }
            }

            return sprintf('%s/%s/%04d', $prefix, $fy, $nextSeq);
        });
    }

    /**
     * Atomically generate the next sequential receipt number for a practitioner.
     * Format: {PREFIX}/{FY}/{0001} (e.g. REC/2026-27/0001)
     */
    public static function generateNextReceiptNumber(Admin $admin, ?Carbon $date = null): string
    {
        return DB::transaction(function () use ($admin, $date) {
            $settings = self::getOrCreateForAdmin($admin);
            $prefix = strtoupper(trim($settings->receipt_prefix ?: 'REC'));
            $fy = self::getFinancialYear($date);
            $searchPattern = "{$prefix}/{$fy}/%";

            $latest = ProfessionalTransaction::where('admin_id', $admin->id)
                ->where('receipt_number', 'LIKE', $searchPattern)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('receipt_number');

            $nextSeq = 1;
            if ($latest) {
                $parts = explode('/', $latest);
                $lastNum = (int) end($parts);
                if ($lastNum > 0) {
                    $nextSeq = $lastNum + 1;
                }
            }

            return sprintf('%s/%s/%04d', $prefix, $fy, $nextSeq);
        });
    }
}
