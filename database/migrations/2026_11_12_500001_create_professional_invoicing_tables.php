<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Professional document and business settings
        if (!Schema::hasTable('professional_document_settings')) {
            Schema::create('professional_document_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')
                    ->constrained('admins')
                    ->onDelete('cascade');
                $table->string('business_name')->nullable();
                $table->string('designation')->nullable();
                $table->text('business_address')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('website')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('gstin', 20)->nullable();
                $table->string('pan', 20)->nullable();
                $table->boolean('tax_enabled')->default(false);
                $table->string('tax_name', 50)->default('GST');
                $table->decimal('default_tax_rate', 5, 2)->default(18.00);
                $table->boolean('is_tax_inclusive')->default(false);
                $table->string('place_of_supply', 100)->nullable();
                $table->string('invoice_prefix', 20)->default('INV');
                $table->string('receipt_prefix', 20)->default('REC');
                $table->text('invoice_notes')->nullable();
                $table->text('invoice_terms')->nullable();
                $table->timestamps();

                $table->unique('admin_id');
            });
        }

        // 2. Professional Invoices table
        if (!Schema::hasTable('professional_invoices')) {
            Schema::create('professional_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')
                    ->constrained('admins')
                    ->onDelete('cascade');
                $table->foreignId('jajman_id')
                    ->nullable()
                    ->constrained('jajmans')
                    ->onDelete('set null');
                $table->foreignId('appointment_id')
                    ->nullable()
                    ->constrained('appointments')
                    ->onDelete('set null');
                $table->foreignId('consultation_id')
                    ->nullable()
                    ->constrained('consultations')
                    ->onDelete('set null');
                $table->foreignId('service_id')
                    ->nullable()
                    ->constrained('professional_services')
                    ->onDelete('set null');

                $table->string('invoice_number', 60);
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->string('currency', 10)->default('INR');

                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->string('discount_type', 20)->default('none'); // 'none', 'fixed', 'percentage'
                $table->decimal('discount_rate', 5, 2)->nullable();
                $table->decimal('discount_amount', 12, 2)->default(0.00);

                $table->boolean('tax_enabled')->default(false);
                $table->string('tax_name', 50)->nullable();
                $table->decimal('tax_rate', 5, 2)->nullable();
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->boolean('is_tax_inclusive')->default(false);

                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->decimal('amount_paid', 12, 2)->default(0.00);
                $table->decimal('outstanding_balance', 12, 2)->default(0.00);

                // 'draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'
                $table->string('status', 30)->default('draft');
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();

                $table->text('notes')->nullable();
                $table->text('payment_terms')->nullable();

                // Historical immutable snapshots
                $table->json('professional_details_snapshot')->nullable();
                $table->json('jajman_details_snapshot')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['admin_id', 'invoice_number']);
                $table->index(['admin_id', 'status']);
                $table->index(['admin_id', 'invoice_date']);
                $table->index(['admin_id', 'jajman_id']);
            });
        }

        // 3. Invoice line items
        if (!Schema::hasTable('professional_invoice_items')) {
            Schema::create('professional_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')
                    ->constrained('professional_invoices')
                    ->onDelete('cascade');
                $table->foreignId('service_id')
                    ->nullable()
                    ->constrained('professional_services')
                    ->onDelete('set null');
                $table->text('description');
                $table->decimal('quantity', 8, 2)->default(1.00);
                $table->decimal('unit_price', 12, 2)->default(0.00);
                $table->decimal('line_total', 12, 2)->default(0.00);
                $table->timestamps();

                $table->index('invoice_id');
            });
        }

        // 4. Update professional_transactions table with invoice_id and receipt_number
        if (Schema::hasTable('professional_transactions')) {
            Schema::table('professional_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('professional_transactions', 'invoice_id')) {
                    $table->foreignId('invoice_id')
                        ->nullable()
                        ->after('consultation_id')
                        ->constrained('professional_invoices')
                        ->onDelete('set null');
                    $table->index('invoice_id');
                }
                if (!Schema::hasColumn('professional_transactions', 'receipt_number')) {
                    $table->string('receipt_number', 60)
                        ->nullable()
                        ->after('reference_id');
                    $table->index(['admin_id', 'receipt_number']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'receipt_number']);
        });

        Schema::dropIfExists('professional_invoice_items');
        Schema::dropIfExists('professional_invoices');
        Schema::dropIfExists('professional_document_settings');
    }
};
