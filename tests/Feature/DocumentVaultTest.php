<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureVaultUnlocked;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentVaultTest extends TestCase
{
    use RefreshDatabase;

    private function withPin(string $pin = '1234'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['vault_pin' => Hash::make($pin)])->save();

        return $user;
    }

    public function test_gate_shows_setup_when_no_pin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('vault.gate'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('documents/Lock')->where('mode', 'setup'));
    }

    public function test_locked_vault_redirects_to_gate(): void
    {
        $this->actingAs($this->withPin())
            ->get(route('vault.index'))
            ->assertRedirect(route('vault.gate'));
    }

    public function test_setting_a_pin_unlocks_and_opens_the_vault(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('vault.pin'), ['pin' => '4321', 'pin_confirmation' => '4321'])
            ->assertRedirect(route('vault.index'));

        $this->assertTrue(Hash::check('4321', $user->fresh()->vault_pin));
        $this->get(route('vault.index'))->assertOk();
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $this->actingAs($this->withPin('1234'))
            ->from(route('vault.gate'))
            ->post(route('vault.unlock'), ['pin' => '9999'])
            ->assertRedirect(route('vault.gate'))
            ->assertSessionHasErrors('pin');

        $this->get(route('vault.index'))->assertRedirect(route('vault.gate'));
    }

    public function test_correct_pin_unlocks(): void
    {
        $this->actingAs($this->withPin('1234'))
            ->post(route('vault.unlock'), ['pin' => '1234'])
            ->assertRedirect(route('vault.index'));

        $this->get(route('vault.index'))->assertOk();
    }

    public function test_upload_encrypts_file_at_rest_and_streams_back_original(): void
    {
        Storage::fake('local');
        $user = $this->unlock($this->withPin());

        $file = UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf');
        $original = $file->get();

        $this->actingAs($user)->post(route('vault.documents.store'), [
            'category' => 'aadhaar',
            'title' => 'My Aadhaar',
            'file' => $file,
        ])->assertRedirect();

        $doc = Document::firstOrFail();
        $this->assertSame($user->id, $doc->user_id);

        // Stored blob must NOT equal the plaintext, but must decrypt to it.
        $stored = Storage::disk('local')->get($doc->path);
        $this->assertNotSame($original, $stored);
        $this->assertSame($original, Crypt::decryptString($stored));

        // The view endpoint returns the original bytes.
        $response = $this->actingAs($user)->get(route('vault.documents.view', $doc));
        $response->assertOk();
        $this->assertSame($original, $response->streamedContent());
    }

    public function test_users_cannot_access_others_documents(): void
    {
        Storage::fake('local');
        $owner = $this->unlock($this->withPin());
        $doc = $owner->documents()->create([
            'category' => 'pan', 'title' => 'PAN', 'original_name' => 'p.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'path' => 'documents/x.enc',
        ]);

        $intruder = $this->unlock($this->withPin());

        $this->actingAs($intruder)->get(route('vault.documents.view', $doc))->assertForbidden();
        $this->actingAs($intruder)->delete(route('vault.documents.destroy', $doc))->assertForbidden();
    }

    public function test_delete_removes_row_and_blob(): void
    {
        Storage::fake('local');
        $user = $this->unlock($this->withPin());

        $this->actingAs($user)->post(route('vault.documents.store'), [
            'category' => 'other', 'title' => 'Doc',
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $doc = Document::firstOrFail();
        Storage::disk('local')->assertExists($doc->path);

        $this->actingAs($user)->delete(route('vault.documents.destroy', $doc))->assertRedirect();

        $this->assertModelMissing($doc);
        Storage::disk('local')->assertMissing($doc->path);
    }

    public function test_upload_rejects_disallowed_file_types(): void
    {
        Storage::fake('local');
        $user = $this->unlock($this->withPin());

        $this->actingAs($user)->post(route('vault.documents.store'), [
            'category' => 'other', 'title' => 'Bad',
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    /**
     * Authenticate and mark the vault session unlocked for this user.
     */
    private function unlock(User $user): User
    {
        $this->actingAs($user);
        session([EnsureVaultUnlocked::SESSION_KEY => time()]);

        return $user;
    }
}
