<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\Support\DemoFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills the demo user's Secure Documents Vault: a known PIN plus encrypted
 * sample files, so the vault gate / unlock / list / view / download endpoints
 * can all be exercised end to end.
 *
 * Demo PIN: 1234
 */
class VaultDemoSeeder extends Seeder
{
    /** Private disk holding the encrypted document blobs (matches DocumentController). */
    private const DISK = 'local';

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            return;
        }

        $this->purge($user);

        $user->forceFill(['vault_pin' => Hash::make('1234')])->save();

        foreach ($this->documents() as $doc) {
            $isImage = $doc['kind'] === 'png';
            $contents = $isImage ? DemoFile::png() : DemoFile::pdf($doc['title']);

            $path = 'documents/'.$user->id.'/'.Str::uuid()->toString().'.enc';
            Storage::disk(self::DISK)->put($path, Crypt::encryptString($contents));

            $user->documents()->create([
                'category' => $doc['category'],
                'title' => $doc['title'],
                'side' => $doc['side'] ?? null,
                'original_name' => $doc['title'].($isImage ? '.png' : '.pdf'),
                'mime_type' => $isImage ? 'image/png' : 'application/pdf',
                'size_bytes' => strlen($contents),
                'path' => $path,
                'notes' => $doc['notes'] ?? null,
            ]);
        }
    }

    /**
     * A spread across vault categories, including a front/back pair so the
     * two-sided card layout has data.
     *
     * @return array<int, array<string, string>>
     */
    private function documents(): array
    {
        return [
            ['category' => 'aadhaar', 'title' => 'Aadhaar Card', 'side' => 'front', 'kind' => 'png'],
            ['category' => 'aadhaar', 'title' => 'Aadhaar Card', 'side' => 'back', 'kind' => 'png'],
            ['category' => 'pan', 'title' => 'PAN Card', 'side' => 'front', 'kind' => 'png', 'notes' => 'Linked to Aadhaar.'],
            ['category' => 'debit_atm_card', 'title' => 'HDFC Debit Card', 'side' => 'front', 'kind' => 'png'],
            ['category' => 'credit_card', 'title' => 'HDFC Millennia Card', 'side' => 'front', 'kind' => 'png', 'notes' => 'Billing date: 22nd of each month.'],
            ['category' => 'driving_license', 'title' => 'Driving License', 'side' => 'front', 'kind' => 'png', 'notes' => 'Valid till 2031.'],
            ['category' => 'passport', 'title' => 'Passport', 'kind' => 'pdf', 'notes' => 'Expires March 2029.'],
            ['category' => 'insurance', 'title' => 'Health Insurance Policy', 'kind' => 'pdf', 'notes' => 'Star Health — family floater, 5L cover.'],
            ['category' => 'vehicle_rc', 'title' => 'Car RC Book', 'kind' => 'pdf'],
            ['category' => 'loan', 'title' => 'Home Loan Agreement', 'kind' => 'pdf'],
            ['category' => 'property', 'title' => 'Flat Sale Deed', 'kind' => 'pdf'],
            ['category' => 'medical', 'title' => 'Annual Health Checkup', 'kind' => 'pdf', 'notes' => 'Apollo, last February.'],
        ];
    }

    /** Drop the previous run's rows and their encrypted blobs. */
    private function purge(User $user): void
    {
        $user->documents()->get()->each(function (Document $document) {
            Storage::disk(self::DISK)->delete($document->path);
            $document->delete();
        });
    }
}
