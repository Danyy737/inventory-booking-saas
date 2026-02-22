<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organisation;
use App\Services\JoinCodeGenerator;

class BackfillOrganisationJoinCodes extends Command
{
    protected $signature = 'orgs:backfill-join-codes {--dry-run}';
    protected $description = 'Backfill join codes for organisations that are missing them';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $orgs = Organisation::query()
            ->whereNull('join_code')
            ->get();

        if ($orgs->isEmpty()) {
            $this->info('No organisations missing join_code.');
            return self::SUCCESS;
        }

        $this->info('Found ' . $orgs->count() . ' organisations missing join_code.');

        foreach ($orgs as $org) {
            $code = JoinCodeGenerator::generate(8);

            $this->line("Org #{$org->id} {$org->name} -> {$code}" . ($dry ? ' (dry-run)' : ''));

            if (!$dry) {
                $org->join_code = $code;
                $org->join_enabled = $org->join_enabled ?? true;
                $org->save();
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
