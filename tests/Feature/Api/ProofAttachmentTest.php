<?php

namespace Tests\Feature\Api;

use App\Models\EntryAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Proof-of-payment files on income / expense entries: the fields the app reads
 * back, the preview endpoint that returns the real bytes, and removal.
 */
class ProofAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->image('receipt.jpg', 20, 20);
    }

    /** Create a bare transaction through the API and return its id. */
    private function createEntry(): int
    {
        return (int) $this->postJson('/api/entries', $this->entryPayload())->json('entry.id');
    }

    private function entryPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'expense',
            'amount' => 250,
            'category' => 'Food',
            'occurred_on' => now()->toDateString(),
        ], $overrides);
    }

    public function test_creating_a_transaction_with_a_proof_returns_the_full_attachment_shape(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$this->proof()],
        ]));

        $response->assertCreated()
            ->assertJsonStructure([
                'entry' => [
                    'attachments' => [
                        ['id', 'name', 'mime_type', 'size', 'url', 'view_url', 'download_url'],
                    ],
                ],
            ]);

        $attachment = $response->json('entry.attachments.0');
        $this->assertSame('receipt.jpg', $attachment['name']);
        $this->assertSame('image/jpeg', $attachment['mime_type']);
        $this->assertGreaterThan(0, $attachment['size']);
        $this->assertSame($attachment['url'], $attachment['view_url']);
    }

    public function test_attachment_urls_are_fetchable_without_a_bearer_token(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $url = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$this->proof()],
        ]))->json('entry.attachments.0.url');

        // A fresh, tokenless client — this is what an image widget does.
        $response = $this->withoutMiddleware(CheckAbilities::class)
            ->get($url);

        $response->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_download_flag_returns_an_attachment_disposition(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $url = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$this->proof()],
        ]))->json('entry.attachments.0.download_url');

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringStartsWith('attachment;', $response->headers->get('content-disposition'));
    }

    public function test_a_tampered_or_unsigned_file_url_is_rejected(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $id = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$this->proof()],
        ]))->json('entry.attachments.0.id');

        $this->get("/api/files/entry-attachments/{$id}")->assertForbidden();
    }

    public function test_view_endpoint_streams_the_original_bytes(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $file = $this->proof();
        $original = file_get_contents($file->getRealPath());

        $id = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$file],
        ]))->json('entry.attachments.0.id');

        $response = $this->get("/api/entry-attachments/{$id}/view");

        $response->assertOk();
        $this->assertSame('inline', explode(';', (string) $response->headers->get('content-disposition'))[0]);
        $this->assertSame($original, $response->streamedContent());
    }

    public function test_a_proof_cannot_be_viewed_by_another_user(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $id = $this->post('/api/entries', $this->entryPayload([
            'attachments' => [$this->proof()],
        ]))->json('entry.attachments.0.id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/entry-attachments/{$id}/view")->assertForbidden();
    }

    public function test_proof_can_be_attached_to_an_existing_entry(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $entryId = $this->createEntry();

        $this->post("/api/entries/{$entryId}/attachments", [
            'attachments' => [$this->proof()],
        ])->assertCreated()
            ->assertJsonStructure(['attachments' => [['id', 'name', 'mime_type', 'size', 'url', 'view_url']]]);
    }

    public function test_deleting_a_proof_removes_the_row_and_the_encrypted_blob(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $entryId = $this->createEntry();
        $this->post("/api/entries/{$entryId}/attachments", ['attachments' => [$this->proof()]]);

        $attachment = EntryAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->deleteJson("/api/entries/{$entryId}/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJson(['deleted_id' => $attachment->id, 'entry_id' => $entryId]);

        $this->assertDatabaseMissing('entry_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_deleting_a_proof_through_a_mismatched_entry_is_a_404(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $entryId = $this->createEntry();
        $otherId = $this->createEntry();
        $this->post("/api/entries/{$entryId}/attachments", ['attachments' => [$this->proof()]]);

        $attachment = EntryAttachment::firstOrFail();

        $this->deleteJson("/api/entries/{$otherId}/attachments/{$attachment->id}")->assertNotFound();
        $this->assertDatabaseHas('entry_attachments', ['id' => $attachment->id]);
    }

    public function test_another_user_cannot_delete_a_proof(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $entryId = $this->createEntry();
        $this->post("/api/entries/{$entryId}/attachments", ['attachments' => [$this->proof()]]);
        $attachment = EntryAttachment::firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/entry-attachments/{$attachment->id}")->assertForbidden();
        $this->assertDatabaseHas('entry_attachments', ['id' => $attachment->id]);
    }

    public function test_deleting_a_transaction_clears_its_proof_blobs(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $entryId = $this->createEntry();
        $this->post("/api/entries/{$entryId}/attachments", ['attachments' => [$this->proof()]]);
        $path = EntryAttachment::firstOrFail()->path;

        $this->deleteJson("/api/entries/{$entryId}")->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('entry_attachments', 0);
    }
}
