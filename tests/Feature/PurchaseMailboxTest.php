<?php

namespace Tests\Feature;

use App\Ai\Agents\SupplierInvoiceExtractor;
use App\Contracts\PurchaseMailboxClient;
use App\Models\PurchaseInboxDocument;
use App\Models\PurchaseMailbox;
use App\Models\PurchaseMailboxMessage;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class PurchaseMailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_configures_and_idempotently_synchronizes_a_private_purchase_mailbox(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $commercial = User::factory()->create(['role' => 'commercial']);
        $settings = [
            'name' => 'Supplier invoices', 'email' => 'facturas@arevantes.test', 'host' => 'imap.arevantes.test',
            'port' => 993, 'encryption' => 'ssl', 'username' => 'facturas@arevantes.test', 'password' => 'mailbox-secret',
            'folder' => 'INBOX', 'lookback_days' => 30, 'validate_certificate' => '1', 'auto_extract' => '1', 'is_active' => '1',
        ];

        $this->actingAs($commercial)->put(route('purchase-inbox.mailbox.update'), $settings)->assertForbidden();
        $this->actingAs($admin)->put(route('purchase-inbox.mailbox.update'), $settings)->assertRedirect();
        $mailbox = PurchaseMailbox::firstOrFail();
        $this->assertSame('mailbox-secret', $mailbox->password);
        $this->assertNotSame('mailbox-secret', DB::table('purchase_mailboxes')->value('password'));

        $client = new class implements PurchaseMailboxClient
        {
            public function messages(PurchaseMailbox $mailbox, CarbonInterface $since): iterable
            {
                yield [
                    'uid' => '501', 'message_id' => 'supplier-501@example.test', 'subject' => 'Factura julio',
                    'from_name' => 'Solar Supply', 'from_email' => 'billing@solar.test', 'received_at' => now()->subHour(),
                    'attachments' => [
                        ['name' => 'factura-501.png', 'mime_type' => 'application/octet-stream', 'content' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')],
                        ['name' => 'terms.txt', 'mime_type' => 'text/plain', 'content' => 'Not an invoice'],
                    ],
                ];
                yield ['uid' => '502', 'subject' => 'No attachment', 'from_email' => 'info@example.test', 'attachments' => []];
            }
        };
        $this->app->instance(PurchaseMailboxClient::class, $client);

        $this->post(route('purchase-inbox.mailbox.sync'))->assertRedirect();
        $this->post(route('purchase-inbox.mailbox.sync'))->assertRedirect();
        $this->assertSame(1, PurchaseInboxDocument::count());
        $document = PurchaseInboxDocument::firstOrFail();
        $this->assertSame('email', $document->source);
        $this->assertSame('factura-501.png', $document->original_name);
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame(2, PurchaseMailboxMessage::count());
        $this->assertDatabaseHas('purchase_mailbox_messages', ['message_uid' => '501', 'status' => 'imported', 'attachment_count' => 2, 'imported_count' => 1]);
        $this->assertDatabaseHas('purchase_mailbox_messages', ['message_uid' => '502', 'status' => 'no_attachments', 'imported_count' => 0]);
        $this->assertNotNull($mailbox->refresh()->last_synced_at);

        $this->get(route('purchase-inbox.index'))->assertInertia(fn (Assert $page) => $page
            ->component('purchases/inbox/index')
            ->where('mailbox.email', 'facturas@arevantes.test')
            ->where('mailbox.messages_count', 2)
            ->where('canConfigureMailbox', true));
        $this->get(route('purchase-inbox.show', $document))->assertInertia(fn (Assert $page) => $page
            ->where('inboxDocument.mailbox_message.subject', 'Factura julio')
            ->where('inboxDocument.mailbox_message.from_email', 'billing@solar.test'));
    }

    public function test_email_documents_are_automatically_extracted_and_mailbox_failures_are_visible(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['legal_name' => 'Automated Solar SL', 'tax_id' => 'B99887766', 'country' => 'ES', 'is_active' => true]);
        $mailbox = PurchaseMailbox::create([
            'name' => 'Invoices', 'email' => 'invoice@arevantes.test', 'host' => 'imap.test', 'port' => 993,
            'encryption' => 'ssl', 'validate_certificate' => true, 'username' => 'invoice', 'password' => 'secret',
            'folder' => 'INBOX', 'lookback_days' => 30, 'auto_extract' => true, 'is_active' => true,
        ]);
        $message = $mailbox->messages()->create(['message_uid' => '700', 'subject' => 'Automated invoice', 'status' => 'imported']);
        $path = 'purchase-inbox/email/auto.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $document = PurchaseInboxDocument::create([
            'purchase_mailbox_message_id' => $message->id, 'source' => 'email', 'original_name' => 'auto.png',
            'path' => $path, 'mime_type' => 'image/png', 'size' => 68, 'file_hash' => hash('sha256', 'auto-document'),
        ]);
        SupplierInvoiceExtractor::fake([[
            'supplier_name' => 'Automated Solar SL', 'supplier_tax_id' => 'B99887766', 'invoice_number' => 'AUTO-7',
            'issue_date' => '2026-07-13', 'due_date' => '2026-08-12', 'subtotal' => 100, 'tax_amount' => 21,
            'total' => 121, 'currency' => 'EUR', 'confidence' => 0.99,
        ]]);

        $this->artisan('purchase-inbox:extract --limit=3')->assertSuccessful();
        $document->refresh();
        $this->assertSame('extracted', $document->extraction_status);
        $this->assertSame('ready', $document->status);
        $this->assertSame($supplier->id, $document->supplier_id);

        $this->app->instance(PurchaseMailboxClient::class, new class implements PurchaseMailboxClient
        {
            public function messages(PurchaseMailbox $mailbox, CarbonInterface $since): iterable
            {
                throw new RuntimeException('IMAP test connection failed.');
            }
        });
        $this->actingAs($admin)->post(route('purchase-inbox.mailbox.sync'))->assertRedirect();
        $this->assertSame('IMAP test connection failed.', $mailbox->refresh()->last_error);
    }
}
