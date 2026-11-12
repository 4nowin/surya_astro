<?php

namespace App\Services;

use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalTransaction;
use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentPdfService
{
    /**
     * Render and return PDF binary string for an invoice.
     */
    public function generateInvoicePdf(ProfessionalInvoice $invoice): string
    {
        $html = $this->buildInvoiceHtml($invoice);
        return $this->renderHtmlToPdf($html);
    }

    /**
     * Render and return PDF binary string for a payment receipt.
     */
    public function generateReceiptPdf(ProfessionalTransaction $transaction): string
    {
        $html = $this->buildReceiptHtml($transaction);
        return $this->renderHtmlToPdf($html);
    }

    /**
     * Common PDF renderer using Dompdf.
     */
    protected function renderHtmlToPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Build responsive, print-ready HTML for invoice.
     */
    public function buildInvoiceHtml(ProfessionalInvoice $invoice): string
    {
        $prof = $invoice->professional_details_snapshot ?: [];
        $jajman = $invoice->jajman_details_snapshot ?: [];

        $businessName = htmlspecialchars($prof['business_name'] ?? $invoice->admin?->name ?? 'Practitioner');
        $designation = htmlspecialchars($prof['designation'] ?? 'Spiritual Consultant');
        $profPhone = htmlspecialchars($prof['phone'] ?? $invoice->admin?->phone ?? '');
        $profEmail = htmlspecialchars($prof['email'] ?? $invoice->admin?->email ?? '');
        $profAddress = htmlspecialchars($prof['address'] ?? '');
        $gstin = htmlspecialchars($prof['gstin'] ?? '');
        $pan = htmlspecialchars($prof['pan'] ?? '');

        $clientName = htmlspecialchars($jajman['name'] ?? $invoice->jajman?->name ?? 'Valued Client');
        $clientPhone = htmlspecialchars($jajman['phone'] ?? $invoice->jajman?->phone ?? '');
        $clientEmail = htmlspecialchars($jajman['email'] ?? $invoice->jajman?->email ?? '');
        $clientCity = htmlspecialchars($jajman['city'] ?? $invoice->jajman?->city ?? '');
        $clientAddress = htmlspecialchars($jajman['address'] ?? '');

        $currency = $invoice->currency === 'INR' ? '₹' : $invoice->currency;
        $statusLabel = strtoupper(str_replace('_', ' ', $invoice->status));
        $statusColor = match ($invoice->status) {
            'paid' => '#16a34a',
            'partially_paid' => '#ea580c',
            'overdue' => '#dc2626',
            'cancelled' => '#6b7280',
            'draft' => '#4b5563',
            default => '#2563eb',
        };

        $itemsRows = '';
        $idx = 1;
        foreach ($invoice->items as $item) {
            $desc = htmlspecialchars($item->description);
            $qty = (float) $item->quantity;
            $unit = number_format((float) $item->unit_price, 2);
            $total = number_format((float) $item->line_total, 2);

            $itemsRows .= "
            <tr>
                <td style=\"text-align: center; border-bottom: 1px solid #e5e7eb; padding: 10px;\">{$idx}</td>
                <td style=\"border-bottom: 1px solid #e5e7eb; padding: 10px;\"><strong>{$desc}</strong></td>
                <td style=\"text-align: center; border-bottom: 1px solid #e5e7eb; padding: 10px;\">{$qty}</td>
                <td style=\"text-align: right; border-bottom: 1px solid #e5e7eb; padding: 10px;\">{$currency} {$unit}</td>
                <td style=\"text-align: right; border-bottom: 1px solid #e5e7eb; padding: 10px;\">{$currency} {$total}</td>
            </tr>";
            $idx++;
        }

        $discountHtml = '';
        if ((float) $invoice->discount_amount > 0) {
            $discLabel = $invoice->discount_type === 'percentage'
                ? "Discount (" . number_format((float) $invoice->discount_rate, 0) . "%)"
                : "Discount";
            $discAmt = number_format((float) $invoice->discount_amount, 2);
            $discountHtml = "
            <tr>
                <td style=\"padding: 6px 10px; color: #4b5563;\">{$discLabel}:</td>
                <td style=\"text-align: right; padding: 6px 10px; color: #dc2626;\">- {$currency} {$discAmt}</td>
            </tr>";
        }

        $taxHtml = '';
        if ($invoice->tax_enabled && (float) $invoice->tax_amount > 0) {
            $taxName = htmlspecialchars($invoice->tax_name ?: 'GST');
            $taxRate = number_format((float) $invoice->tax_rate, 2);
            $taxAmt = number_format((float) $invoice->tax_amount, 2);
            $inclusiveNote = $invoice->is_tax_inclusive ? ' (Inclusive)' : '';

            $taxHtml = "
            <tr>
                <td style=\"padding: 6px 10px; color: #4b5563;\">{$taxName} ({$taxRate}%){$inclusiveNote}:</td>
                <td style=\"text-align: right; padding: 6px 10px;\">{$currency} {$taxAmt}</td>
            </tr>";
        }

        $notesHtml = '';
        if (!empty($invoice->notes) || !empty($invoice->payment_terms)) {
            $notes = nl2br(htmlspecialchars($invoice->notes ?? ''));
            $terms = nl2br(htmlspecialchars($invoice->payment_terms ?? ''));
            $notesHtml = "
            <div style=\"margin-top: 25px; padding: 15px; background: #f9fafb; border-radius: 6px; font-size: 12px; color: #4b5563;\">
                " . (!empty($invoice->notes) ? "<p style=\"margin: 0 0 8px;\"><strong>Notes:</strong><br>{$notes}</p>" : '') . "
                " . (!empty($invoice->payment_terms) ? "<p style=\"margin: 0;\"><strong>Payment Terms:</strong><br>{$terms}</p>" : '') . "
            </div>";
        }

        $subtotal = number_format((float) $invoice->subtotal, 2);
        $grandTotal = number_format((float) $invoice->grand_total, 2);
        $amountPaid = number_format((float) $invoice->amount_paid, 2);
        $outstanding = number_format((float) $invoice->outstanding_balance, 2);
        $invoiceDate = $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : now()->format('d M Y');
        $dueDate = $invoice->due_date ? $invoice->due_date->format('d M Y') : 'Due on receipt';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset=\"utf-8\">
            <title>Invoice {$invoice->invoice_number}</title>
            <style>
                body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #1f2937; margin: 0; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
            </style>
        </head>
        <body>
            <!-- Header Section -->
            <table>
                <tr>
                    <td style=\"width: 60%; vertical-align: top;\">
                        <h2 style=\"margin: 0 0 4px; color: #4338ca;\">{$businessName}</h2>
                        <div style=\"color: #6b7280; font-size: 13px;\">{$designation}</div>
                        " . ($profAddress ? "<div style=\"color: #4b5563; margin-top: 4px;\">{$profAddress}</div>" : '') . "
                        <div style=\"color: #4b5563;\">" . ($profPhone ? "Phone: {$profPhone} &bull; " : '') . ($profEmail ? "Email: {$profEmail}" : '') . "</div>
                        " . ($gstin ? "<div style=\"color: #374151; font-weight: bold; margin-top: 4px;\">GSTIN: {$gstin}" . ($pan ? " &bull; PAN: {$pan}" : '') . "</div>" : '') . "
                    </td>
                    <td style=\"width: 40%; text-align: right; vertical-align: top;\">
                        <div style=\"font-size: 24px; font-weight: bold; color: #1e1b4b;\">INVOICE</div>
                        <div style=\"font-size: 14px; font-weight: bold; color: #4338ca; margin-top: 4px;\"># {$invoice->invoice_number}</div>
                        <div style=\"margin-top: 6px; display: inline-block; padding: 4px 10px; border-radius: 4px; background: {$statusColor}; color: #ffffff; font-weight: bold; font-size: 11px;\">{$statusLabel}</div>
                    </td>
                </tr>
            </table>

            <hr style=\"border: none; border-top: 2px solid #e5e7eb; margin: 20px 0;\">

            <!-- Billed To and Meta Info -->
            <table>
                <tr>
                    <td style=\"width: 55%; vertical-align: top;\">
                        <div style=\"font-size: 11px; font-weight: bold; color: #6b7280; text-transform: uppercase;\">Billed To</div>
                        <div style=\"font-size: 15px; font-weight: bold; margin-top: 4px;\">{$clientName}</div>
                        " . ($clientPhone ? "<div style=\"color: #4b5563; margin-top: 2px;\">Phone: {$clientPhone}</div>" : '') . "
                        " . ($clientEmail ? "<div style=\"color: #4b5563;\">Email: {$clientEmail}</div>" : '') . "
                        " . ($clientCity || $clientAddress ? "<div style=\"color: #4b5563;\">" . ($clientAddress ? "{$clientAddress}, " : '') . "{$clientCity}</div>" : '') . "
                    </td>
                    <td style=\"width: 45%; vertical-align: top;\">
                        <table style=\"font-size: 13px;\">
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Invoice Date:</td>
                                <td style=\"text-align: right; font-weight: bold;\">{$invoiceDate}</td>
                            </tr>
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Payment Due:</td>
                                <td style=\"text-align: right; font-weight: bold;\">{$dueDate}</td>
                            </tr>
                            " . ($invoice->appointment_id ? "<tr><td style=\"padding: 3px 0; color: #6b7280;\">Appointment ID:</td><td style=\"text-align: right;\">#{$invoice->appointment_id}</td></tr>" : '') . "
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Line Items Table -->
            <table style=\"margin-top: 25px;\">
                <thead>
                    <tr style=\"background: #f3f4f6;\">
                        <th style=\"padding: 10px; border-bottom: 2px solid #d1d5db; width: 6%; text-align: center;\">#</th>
                        <th style=\"padding: 10px; border-bottom: 2px solid #d1d5db; text-align: left;\">Service / Description</th>
                        <th style=\"padding: 10px; border-bottom: 2px solid #d1d5db; width: 12%; text-align: center;\">Qty</th>
                        <th style=\"padding: 10px; border-bottom: 2px solid #d1d5db; width: 20%; text-align: right;\">Rate</th>
                        <th style=\"padding: 10px; border-bottom: 2px solid #d1d5db; width: 20%; text-align: right;\">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsRows}
                </tbody>
            </table>

            <!-- Totals Section -->
            <table style=\"margin-top: 15px;\">
                <tr>
                    <td style=\"width: 50%; vertical-align: top;\">
                        " . ($invoice->is_tax_inclusive ? "<div style=\"font-size: 11px; color: #6b7280; font-style: italic;\">* Prices are inclusive of applicable taxes.</div>" : '') . "
                    </td>
                    <td style=\"width: 50%; vertical-align: top;\">
                        <table>
                            <tr>
                                <td style=\"padding: 6px 10px; color: #4b5563;\">Subtotal:</td>
                                <td style=\"text-align: right; padding: 6px 10px; font-weight: bold;\">{$currency} {$subtotal}</td>
                            </tr>
                            {$discountHtml}
                            {$taxHtml}
                            <tr style=\"border-top: 2px solid #e5e7eb;\">
                                <td style=\"padding: 8px 10px; font-size: 15px; font-weight: bold;\">Total Amount:</td>
                                <td style=\"text-align: right; padding: 8px 10px; font-size: 15px; font-weight: bold; color: #1e1b4b;\">{$currency} {$grandTotal}</td>
                            </tr>
                            <tr>
                                <td style=\"padding: 4px 10px; color: #16a34a;\">Amount Paid:</td>
                                <td style=\"text-align: right; padding: 4px 10px; color: #16a34a; font-weight: bold;\">{$currency} {$amountPaid}</td>
                            </tr>
                            <tr style=\"background: #fef2f2;\">
                                <td style=\"padding: 8px 10px; font-weight: bold; color: #991b1b;\">Balance Due:</td>
                                <td style=\"text-align: right; padding: 8px 10px; font-weight: bold; color: #991b1b;\">{$currency} {$outstanding}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {$notesHtml}

            <!-- Footer -->
            <div style=\"margin-top: 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 15px;\">
                This is a computer generated document issued via <strong>Pandit Setu</strong> &bull; Operating System for Spiritual Practitioners
            </div>
        </body>
        </html>";
    }

    /**
     * Build responsive, print-ready HTML for payment receipt.
     */
    public function buildReceiptHtml(ProfessionalTransaction $transaction): string
    {
        $admin = $transaction->professional;
        $jajman = $transaction->jajman;
        $invoice = $transaction->invoice;
        $currency = $transaction->currency === 'INR' ? '₹' : $transaction->currency;

        $receiptNo = $transaction->ensureReceiptNumber();
        $dateStr = $transaction->transaction_at ? $transaction->transaction_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A');
        $amountStr = number_format((float) $transaction->amount, 2);

        $profName = htmlspecialchars($admin?->name ?? 'Practitioner');
        $profPhone = htmlspecialchars($admin?->phone ?? '');
        $profEmail = htmlspecialchars($admin?->email ?? '');

        $clientName = htmlspecialchars($jajman?->name ?? 'Valued Client');
        $clientPhone = htmlspecialchars($jajman?->phone ?? '');
        $clientCity = htmlspecialchars($jajman?->city ?? '');

        $methodLabel = strtoupper(str_replace('_', ' ', $transaction->payment_method));
        $refStr = htmlspecialchars($transaction->reference_id ?? 'N/A');
        $notesStr = htmlspecialchars($transaction->notes ?? '');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset=\"utf-8\">
            <title>Receipt {$receiptNo}</title>
            <style>
                body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #1f2937; margin: 0; padding: 25px; }
                table { width: 100%; border-collapse: collapse; }
            </style>
        </head>
        <body>
            <!-- Receipt Header -->
            <table>
                <tr>
                    <td style=\"width: 60%; vertical-align: top;\">
                        <h2 style=\"margin: 0 0 4px; color: #15803d;\">{$profName}</h2>
                        <div style=\"color: #4b5563;\">" . ($profPhone ? "Phone: {$profPhone} &bull; " : '') . ($profEmail ? "Email: {$profEmail}" : '') . "</div>
                    </td>
                    <td style=\"width: 40%; text-align: right; vertical-align: top;\">
                        <div style=\"font-size: 22px; font-weight: bold; color: #15803d;\">PAYMENT RECEIPT</div>
                        <div style=\"font-size: 14px; font-weight: bold; color: #166534; margin-top: 4px;\"># {$receiptNo}</div>
                        <div style=\"margin-top: 4px; color: #6b7280; font-size: 12px;\">{$dateStr}</div>
                    </td>
                </tr>
            </table>

            <hr style=\"border: none; border-top: 2px solid #bbf7d0; margin: 20px 0;\">

            <!-- Amount Highlight Banner -->
            <div style=\"background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 25px;\">
                <div style=\"font-size: 12px; font-weight: bold; color: #166534; text-transform: uppercase; letter-spacing: 1px;\">Amount Received</div>
                <div style=\"font-size: 32px; font-weight: bold; color: #15803d; margin-top: 6px;\">{$currency} {$amountStr}</div>
                <div style=\"font-size: 12px; color: #15803d; margin-top: 4px; font-weight: 600;\">Payment Status: COMPLETED</div>
            </div>

            <!-- Particulars Table -->
            <table style=\"margin-top: 10px;\">
                <tr>
                    <td style=\"width: 50%; vertical-align: top; padding-right: 15px;\">
                        <div style=\"font-size: 11px; font-weight: bold; color: #6b7280; text-transform: uppercase;\">Received From</div>
                        <div style=\"font-size: 16px; font-weight: bold; margin-top: 4px;\">{$clientName}</div>
                        " . ($clientPhone ? "<div style=\"color: #4b5563; margin-top: 2px;\">Phone: {$clientPhone}</div>" : '') . "
                        " . ($clientCity ? "<div style=\"color: #4b5563;\">Location: {$clientCity}</div>" : '') . "
                    </td>
                    <td style=\"width: 50%; vertical-align: top;\">
                        <div style=\"font-size: 11px; font-weight: bold; color: #6b7280; text-transform: uppercase;\">Transaction Details</div>
                        <table style=\"font-size: 13px; margin-top: 4px;\">
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Payment Method:</td>
                                <td style=\"text-align: right; font-weight: bold;\">{$methodLabel}</td>
                            </tr>
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Reference UTR:</td>
                                <td style=\"text-align: right; font-weight: bold;\">{$refStr}</td>
                            </tr>
                            " . ($invoice ? "
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Linked Invoice:</td>
                                <td style=\"text-align: right; font-weight: bold;\"># {$invoice->invoice_number}</td>
                            </tr>
                            <tr>
                                <td style=\"padding: 3px 0; color: #6b7280;\">Remaining Balance:</td>
                                <td style=\"text-align: right; font-weight: bold; color: #991b1b;\">{$currency} " . number_format((float) $invoice->outstanding_balance, 2) . "</td>
                            </tr>" : '') . "
                        </table>
                    </td>
                </tr>
            </table>

            " . ($notesStr ? "
            <div style=\"margin-top: 25px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 12px; color: #4b5563;\">
                <strong>Notes / Purpose:</strong> {$notesStr}
            </div>" : '') . "

            <!-- Footer -->
            <div style=\"margin-top: 40px; text-align: center; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 15px;\">
                Official Payment Receipt &bull; Issued through <strong>Pandit Setu</strong> &bull; Thank you for your offering
            </div>
        </body>
        </html>";
    }
}
