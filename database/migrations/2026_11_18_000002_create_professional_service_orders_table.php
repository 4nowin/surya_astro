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
        if (!Schema::hasTable('professional_service_orders')) {
            Schema::create('professional_service_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 64)->unique();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->unsignedBigInteger('admin_id')->index();
                $table->unsignedBigInteger('astrologer_id')->nullable()->index();
                $table->unsignedBigInteger('service_id')->index();
                $table->string('order_type', 50)->default('custom'); // janam_kundli, kundli_consultation, vastu_audit, muhurat, custom
                $table->string('status', 32)->default('submitted'); // pending_payment, submitted, in_progress, delivered, completed, disputed, cancelled, refunded
                $table->decimal('base_price', 10, 2);
                $table->decimal('platform_markup', 10, 2);
                $table->decimal('customer_price', 10, 2);
                $table->string('currency', 10)->default('INR');
                $table->unsignedInteger('delivery_timeframe_days')->default(3);
                $table->timestamp('delivery_deadline_at')->nullable()->index();
                $table->json('input_payload')->nullable(); // birth details, question/notes, attachments
                $table->longText('deliverable_text')->nullable(); // written reading, report text, recommendations
                $table->json('deliverable_attachments')->nullable(); // report URLs, chart files, documents
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_by', 32)->nullable(); // user, astrologer, admin, system
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('disputed_at')->nullable();
                $table->text('dispute_reason')->nullable();
                $table->string('dispute_status', 32)->default('none'); // none, pending_review, resolved_refund, resolved_payout
                $table->timestamp('dispute_resolved_at')->nullable();
                $table->text('dispute_resolution_notes')->nullable();
                $table->unsignedBigInteger('escrow_transaction_id')->nullable();
                $table->string('client_nonce', 191)->nullable()->unique();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
                $table->foreign('service_id')->references('id')->on('professional_services')->cascadeOnDelete();

                $table->index(['user_id', 'status'], 'idx_prof_orders_user_status');
                $table->index(['admin_id', 'status'], 'idx_prof_orders_admin_status');
                $table->index(['service_id', 'status'], 'idx_prof_orders_service_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_service_orders');
    }
};
