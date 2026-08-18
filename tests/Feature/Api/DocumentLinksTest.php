<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vault documents and loan / card documents carry the same fetchable-link
 * contract as proof attachments: enough metadata to render a row, and a URL
 * that returns the real bytes without a Bearer token.
 */
class DocumentLinksTest extends TestCase
{
    use RefreshDatabase;

    private function unlockedVaultUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/vault/pin', ['pin' => '1357', 'pin_confirmation' => '1357'])->assertOk();

        return $user;
    }

    public function test_a_vault_document_lists_its_metadata_and_a_fetchable_url(): void
    {
        Storage::fake('local');
        $this->unlockedVaultUser();

        $this->post('/api/vault/documents', [
            'category' => 'pan',
            'title' => 'My PAN Card',
            'file' => UploadedFile::fake()->image('pan.jpg', 30, 30),
        ])->assertCreated()
            ->assertJsonStructure(['document' => ['id', 'title', 'name', 'mime_type', 'size', 'url', 'view_url', 'download_url']]);

        $document = $this->getJson('/api/vault')->assertOk()->json('documents.0');

        foreach (['id', 'name', 'mime_type', 'size', 'url', 'view_url'] as $field) {
            $this->assertArrayHasKey($field, $document, "the vault row is missing `{$field}`");
        }

        $response = $this->get($document['url']);
        $response->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('content-disposition'));
    }

    public function test_the_vault_view_endpoint_returns_the_original_bytes_inline(): void
    {
        Storage::fake('local');
        $this->unlockedVaultUser();

        $file = UploadedFile::fake()->image('aadhaar.jpg', 30, 30);
        $original = file_get_contents($file->getRealPath());

        $id = $this->post('/api/vault/documents', [
            'category' => 'aadhaar',
            'title' => 'Aadhaar',
            'file' => $file,
        ])->json('document.id');

        $response = $this->get("/api/vault/documents/{$id}/view");

        $response->assertOk();
        $this->assertSame('inline', explode(';', (string) $response->headers->get('content-disposition'))[0]);
        $this->assertSame($original, $response->streamedContent());
    }

    public function test_vault_categories_come_from_the_backend(): void
    {
        $this->unlockedVaultUser();

        $categories = $this->getJson('/api/vault')->assertOk()->json('categories');

        $this->assertNotEmpty($categories);
        $this->assertSame(['key', 'label', 'count'], array_keys($categories[0]));
    }

    public function test_a_debt_document_carries_metadata_and_a_fetchable_url(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create());

        $debtId = $this->postJson('/api/debts', [
            'name' => 'Home Loan',
            'kind' => 'loan',
            'balance' => 1800000,
            'interest_rate' => 8.5,
            'emi' => 22000,
            'due_day' => 5,
        ])->assertCreated()->json('debt.id');

        $this->post("/api/debts/{$debtId}/documents", [
            'documents' => [UploadedFile::fake()->image('sanction.jpg', 30, 30)],
        ])->assertCreated()
            ->assertJsonStructure(['documents' => [['id', 'name', 'mime_type', 'size', 'url', 'view_url', 'download_url']]]);

        $document = $this->getJson("/api/debts/{$debtId}")->assertOk()->json('debt.documents.0');

        $this->assertSame('sanction.jpg', $document['name']);
        $this->assertGreaterThan(0, $document['size']);

        $this->get($document['url'])->assertOk()->assertHeader('content-type', 'image/jpeg');
    }
}
