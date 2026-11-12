<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Jajman;
use App\Models\ProfessionalDocumentSetting;
use App\Models\ProfessionalInvoice;
use App\Models\ProfessionalService;
use App\Models\ProfessionalTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function createPractitioner(array $attrs = []): Admin
    {
        Role::firstOrCreate(['name' => 'Astrologer', 'guard_name' => 'admin']);
        $admin = Admin::create(array_merge([
            'name' => 'Pandit Dev Sharma',
            'email' => 'sharma_' . uniqid() . '@panditsetu.test',
            'password' => bcrypt('secret123'),
        ], $attrs));
        $admin->assignRole('Astrologer');
        return $admin;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * 1. Unauthenticated requests are rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/astrologer/invoices')->assertUnauthorized();
        $this->postJson('/api/astrologer/invoices', [])->assertUnauthorized();
        $this->getJson('/api/astrologer/document-settings')->assertUnauthorized();
    }

    /**
     * 2. Practitioner can retrieve and configure business details & tax settings.
     */
    public function test_practitioner_can_configure_document_and_tax_settings(): void
    {
        $practitioner = $this->createPractitioner([
            'name' => 'Pandit Gangadhar Shastri',
            'email' => 'gangadhar_' . uniqid() . '@example.com',
            'phone' => '9812345678',
        ]);
        Sanctum::actingAs($practitioner);

        // Retrieve defaults
        $resGet = $this->getJson('/api/astrologer/document-settings');
        $resGet->assertOk();
        $this->assertEquals('Pandit Gangadhar Shastri', $resGet->json('data.business_name'));
        $this->assertEquals('INV', $resGet->json('data.invoice_prefix'));

        // Update settings with GSTIN and tax
        $resUpdate = $this->postJson('/api/astrologer/document-settings', [
            'business_name' => 'Vedic Astro & Purohit Sansthan',
            'designation' => 'Principal Vedic Acharya',
            'business_address' => 'Assi Ghat, Varanasi, UP - 221005',
            'gstin' => '09AAACG1234F1Z5',
            'tax_enabled' => true,
            'tax_name' => 'GST',
            'default_tax_rate' => 18.00,
            'is_tax_inclusive' => false,
            'invoice_prefix' => 'VAS',
            'receipt_prefix' => 'VRC',
            'invoice_terms' => 'Please pay within 7 days of invoice date.',
        ]);

        $resUpdate->assertOk();
        $this->assertEquals('Vedic Astro & Purohit Sansthan', $resUpdate->json('data.business_name'));
        $this->assertEquals('09AAACG1234F1Z5', $resUpdate->json('data.gstin'));
        $this->assertTrue($resUpdate->json('data.tax_enabled'));
        $this->assertEquals('VAS', $resUpdate->json('data.invoice_prefix'));
    }

    /**
     * 3. Draft invoice creation, updating, and decimal-safe total calculations.
     */
    public function test_draft_invoice_creation_updating_and_calculation(): void
    {
        $practitioner = $this->createPractitioner();
        Sanctum::actingAs($practitioner);

        $jajman = Jajman::create([
            'name' => 'Anil Kapoor',
            'phone' => '9876543210',
            'city' => 'Jaipur',
        ]);
        $jajman->professionals()->attach($practitioner->id, ['status' => 'active', 'source' => 'manual']);

        // 1. Create Draft Invoice with 2 items and 10% discount and 18% tax (exclusive)
        $resCreate = $this->postJson('/api/astrologer/invoices', [
            'jajman_id' => $jajman->id,
            'invoice_date' => '2026-11-12',
            'due_date' => '2026-11-20',
            'status' => 'draft',
            'discount_type' => 'percentage',
            'discount_rate' => 10,
            'tax_enabled' => true,
            'tax_name' => 'GST',
            'tax_rate' => 18,
            'is_tax_inclusive' => false,
            'items' => [
                [
                    'description' => 'Rudrabhishek Puja Ritual',
                    'quantity' => 1,
                    'unit_price' => 5000,
                ],
                [
                    'description' => 'Kundli Milan & Consultation',
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        $resCreate->assertCreated();
        $invoiceId = $resCreate->json('data.id');

        // Subtotal: 5000 + 2000 = 7000.
        // Discount 10%: 700. Taxable: 6300.
        // Tax 18%: 6300 * 0.18 = 1134.
        // Grand total: 6300 + 1134 = 7434.
        $this->assertEquals(7000.0, (float) $resCreate->json('data.subtotal'));
        $this->assertEquals(700.0, (float) $resCreate->json('data.discount_amount'));
        $this->assertEquals(1134.0, (float) $resCreate->json('data.tax_amount'));
        $this->assertEquals(7434.0, (float) $resCreate->json('data.grand_total'));
        $this->assertEquals(7434.0, (float) $resCreate->json('data.outstanding_balance'));
        $this->assertEquals('draft', $resCreate->json('data.status'));

        // 2. Update Draft items
        $resUpdate = $this->putJson("/api/astrologer/invoices/{$invoiceId}", [
            'discount_type' => 'none',
            'tax_enabled' => false,
            'items' => [
                [
                    'description' => 'Rudrabhishek Puja Ritual (Revised)',
                    'quantity' => 1,
                    'unit_price' => 4500,
                ],
            ],
        ]);

        $resUpdate->assertOk();
        $this->assertEquals(4500.0, (float) $resUpdate->json('data.subtotal'));
        $this->assertEquals(0.0, (float) $resUpdate->json('data.discount_amount'));
        $this->assertEquals(0.0, (float) $resUpdate->json('data.tax_amount'));
        $this->assertEquals(4500.0, (float) $resUpdate->json('data.grand_total'));
        $this->assertEquals(4500.0, (float) $resUpdate->json('data.outstanding_balance'));
    }

    /**
     * 4. Issuing invoice generates sequential FY number, creates snapshots, and locks editing.
     */
    public function test_issue_invoice_locks_editing_and_generates_fy_number(): void
    {
        $practitioner = $this->createPractitioner([
            'name' => 'Acharya Mohan',
            'phone' => '9988776655',
        ]);
        Sanctum::actingAs($practitioner);

        ProfessionalDocumentSetting::create([
            'admin_id' => $practitioner->id,
            'business_name' => 'Mohan Jyotish Sansthan',
            'invoice_prefix' => 'MJS',
        ]);

        $jajman = Jajman::create([
            'name' => 'Vikram Seth',
            'phone' => '9123456780',
            'city' => 'Delhi',
        ]);
        $jajman->professionals()->attach($practitioner->id, ['status' => 'active', 'source' => 'manual']);

        $resCreate = $this->postJson('/api/astrologer/invoices', [
            'jajman_id' => $jajman->id,
            'invoice_date' => '2026-11-12',
            'status' => 'draft',
            'items' => [
                ['description' => 'Vastu Inspection', 'quantity' => 1, 'unit_price' => 3000],
            ],
        ]);
        $invoiceId = $resCreate->json('data.id');

        // Issue Invoice
        $resIssue = $this->postJson("/api/astrologer/invoices/{$invoiceId}/issue");
        $resIssue->assertOk();

        $invoiceNumber = $resIssue->json('data.invoice_number');
        $this->assertStringStartsWith('MJS/', $invoiceNumber);
        $this->assertEquals('issued', $resIssue->json('data.status'));
        $this->assertEquals('Mohan Jyotish Sansthan', $resIssue->json('data.professional_details_snapshot.business_name'));
        $this->assertEquals('Vikram Seth', $resIssue->json('data.jajman_details_snapshot.name'));

        // Attempting to update an issued invoice must fail with 422
        $resBlocked = $this->putJson("/api/astrologer/invoices/{$invoiceId}", [
            'items' => [
                ['description' => 'Fraudulent Alteration', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
        $resBlocked->assertStatus(422);
        $this->assertStringContainsString('immutable', $resBlocked->json('message'));
    }

    /**
     * 5. Tenant isolation prevents other practitioners from accessing or mutating invoices.
     */
    public function test_tenant_isolation_prevents_unauthorized_access(): void
    {
        $practitioner1 = $this->createPractitioner();
        $practitioner2 = $this->createPractitioner();

        Sanctum::actingAs($practitioner1);
        $resCreate = $this->postJson('/api/astrologer/invoices', [
            'invoice_date' => '2026-11-12',
            'status' => 'draft',
            'items' => [
                ['description' => 'Private Session', 'quantity' => 1, 'unit_price' => 2000],
            ],
        ]);
        $invoiceId = $resCreate->json('data.id');

        // Switch to practitioner 2
        Sanctum::actingAs($practitioner2);
        $this->getJson("/api/astrologer/invoices/{$invoiceId}")->assertNotFound();
        $this->putJson("/api/astrologer/invoices/{$invoiceId}", ['notes' => 'Hack'])->assertNotFound();
        $this->postJson("/api/astrologer/invoices/{$invoiceId}/issue")->assertNotFound();
        $this->postJson("/api/astrologer/invoices/{$invoiceId}/cancel", ['cancellation_reason' => 'Hack'])->assertNotFound();
    }

    /**
     * 6. Recording payments against invoice generates receipts and updates balances.
     */
    public function test_recording_payments_against_invoice_and_generating_receipts(): void
    {
        $practitioner = $this->createPractitioner();
        Sanctum::actingAs($practitioner);

        $jajman = Jajman::create(['name' => 'Meera Bai', 'phone' => '9888877777']);
        $jajman->professionals()->attach($practitioner->id, ['status' => 'active', 'source' => 'manual']);

        // Create & Issue invoice of ₹4000
        $resCreate = $this->postJson('/api/astrologer/invoices', [
            'jajman_id' => $jajman->id,
            'invoice_date' => '2026-11-12',
            'status' => 'issued',
            'items' => [
                ['description' => 'Navgrah Shanti Havan', 'quantity' => 1, 'unit_price' => 4000],
            ],
        ]);
        $resCreate->assertCreated();
        $invoiceId = $resCreate->json('data.id');

        // 1. Record Partial Payment of ₹1500 (Advance)
        $resPay1 = $this->postJson("/api/astrologer/invoices/{$invoiceId}/payments", [
            'amount' => 1500,
            'payment_method' => 'upi',
            'reference_id' => 'UPI_PARTIAL_01',
            'transaction_type' => 'appointment_advance',
        ]);
        $resPay1->assertCreated();

        $receipt1 = $resPay1->json('data.transaction.receipt_number');
        $this->assertNotEmpty($receipt1);
        $this->assertEquals(1500.0, (float) $resPay1->json('data.invoice.amount_paid'));
        $this->assertEquals(2500.0, (float) $resPay1->json('data.invoice.outstanding_balance'));
        $this->assertEquals('partially_paid', $resPay1->json('data.invoice.status'));

        // 2. Record Final Settlement of ₹2500
        $resPay2 = $this->postJson("/api/astrologer/invoices/{$invoiceId}/payments", [
            'amount' => 2500,
            'payment_method' => 'cash',
            'reference_id' => 'CASH_FINAL_02',
            'transaction_type' => 'service_payment',
        ]);
        $resPay2->assertCreated();

        $receipt2 = $resPay2->json('data.transaction.receipt_number');
        $this->assertNotEmpty($receipt2);
        $this->assertNotEquals($receipt1, $receipt2);
        $this->assertEquals(4000.0, (float) $resPay2->json('data.invoice.amount_paid'));
        $this->assertEquals(0.0, (float) $resPay2->json('data.invoice.outstanding_balance'));
        $this->assertEquals('paid', $resPay2->json('data.invoice.status'));

        // 3. Download Invoice PDF and Receipt PDF
        $resPdfInvoice = $this->get("/api/astrologer/invoices/{$invoiceId}/pdf");
        $resPdfInvoice->assertOk();
        $this->assertEquals('application/pdf', $resPdfInvoice->headers->get('content-type'));

        $txId1 = $resPay1->json('data.transaction.id');
        $resPdfReceipt = $this->get("/api/astrologer/receipts/{$txId1}/pdf");
        $resPdfReceipt->assertOk();
        $this->assertEquals('application/pdf', $resPdfReceipt->headers->get('content-type'));
    }

    /**
     * 7. Cancellation workflow preserves historical audit trail and rejects further payments.
     */
    public function test_invoice_cancellation_workflow(): void
    {
        $practitioner = $this->createPractitioner();
        Sanctum::actingAs($practitioner);

        $resCreate = $this->postJson('/api/astrologer/invoices', [
            'invoice_date' => '2026-11-12',
            'status' => 'issued',
            'items' => [
                ['description' => 'Consultation Session', 'quantity' => 1, 'unit_price' => 1500],
            ],
        ]);
        $invoiceId = $resCreate->json('data.id');

        // Cancel invoice
        $resCancel = $this->postJson("/api/astrologer/invoices/{$invoiceId}/cancel", [
            'cancellation_reason' => 'Client requested rescheduling to next month',
        ]);
        $resCancel->assertOk();
        $this->assertEquals('cancelled', $resCancel->json('data.status'));
        $this->assertEquals('Client requested rescheduling to next month', $resCancel->json('data.cancellation_reason'));

        // Recording payment against cancelled invoice must be rejected with 422
        $resPay = $this->postJson("/api/astrologer/invoices/{$invoiceId}/payments", [
            'amount' => 1500,
            'payment_method' => 'cash',
        ]);
        $resPay->assertStatus(422);
    }
}
