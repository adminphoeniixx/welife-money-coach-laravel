<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Budget;
use App\Models\Challenge;
use App\Models\Debt;
use App\Models\Document;
use App\Models\Entry;
use App\Models\FinanceAccount;
use App\Models\Goal;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes finance records left behind by manual testing (e.g. anything named
 * "Codex Budget", "Codex Loan Check") so they stop showing up in the app.
 *
 * Dry-run by default: it prints what it would remove and changes nothing until
 * --force is passed.
 */
class PurgeTestData extends Command
{
    protected $signature = 'moneycoach:purge-test-data
        {--pattern=Codex : Text to match in a record\'s visible fields}
        {--user= : Limit to one user id}
        {--force : Actually delete (without this the command only reports)}';

    protected $description = 'Remove test/demo finance records whose visible fields contain a pattern (default: "Codex").';

    /**
     * Which text columns identify a record, per model.
     *
     * @var array<class-string, list<string>>
     */
    private const SEARCHABLE = [
        Entry::class => ['description', 'category', 'payee'],
        Debt::class => ['name', 'institution'],
        Bill::class => ['name', 'category'],
        Budget::class => ['category'],
        Goal::class => ['name'],
        FinanceAccount::class => ['name', 'note'],
        Document::class => ['title'],
        Challenge::class => ['title'],
    ];

    public function handle(): int
    {
        $pattern = trim((string) $this->option('pattern'));

        if ($pattern === '') {
            $this->error('A --pattern is required.');

            return self::FAILURE;
        }

        $userId = $this->option('user') === null ? null : (int) $this->option('user');
        $force = (bool) $this->option('force');
        $total = 0;
        $rows = [];

        foreach (self::SEARCHABLE as $model => $columns) {
            /** @var Builder<covariant \Illuminate\Database\Eloquent\Model> $query */
            $query = $model::query()
                ->when($userId !== null, fn (Builder $q) => $q->where('user_id', $userId))
                ->where(function (Builder $q) use ($columns, $pattern): void {
                    foreach ($columns as $column) {
                        $q->orWhereLike($column, "%{$pattern}%");
                    }
                });

            $count = (clone $query)->count();
            $total += $count;

            if ($count > 0) {
                $rows[] = [class_basename($model), $count];

                if ($force) {
                    $query->delete();
                }
            }
        }

        if ($total === 0) {
            $this->info("Nothing matches \"{$pattern}\" — nothing to remove.");

            return self::SUCCESS;
        }

        $this->table(['Record', 'Matches'], $rows);

        if (! $force) {
            $this->warn("Dry run — nothing was deleted. Re-run with --force to remove these {$total} records.");

            return self::SUCCESS;
        }

        $this->info("Removed {$total} records matching \"{$pattern}\".");

        return self::SUCCESS;
    }
}
