<?php

namespace App\Console\Commands;

use App\Models\CashDrawer;
use App\Models\ShiftLog;
use App\Models\User;
use Illuminate\Console\Command;

class ReleaseOrphanCashDrawers extends Command
{
    protected $signature = 'cash-drawers:release-orphans {--fix : Actually release the drawers} {--branch= : Filter by branch_id}';

    protected $description = 'Find cash drawers stuck is_active=1 with no user holding them and no open shift_log referencing them';

    public function handle()
    {
        $shouldFix = $this->option('fix');
        $branchId = $this->option('branch');

        $query = CashDrawer::where('is_active', 1);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $activeDrawers = $query->get();

        if ($activeDrawers->isEmpty()) {
            $this->info('No drawers currently flagged is_active = 1. Nothing to do.');
            return self::SUCCESS;
        }

        $heldByUser = User::whereNotNull('cash_drawer_id')->pluck('cash_drawer_id')->unique();
        $heldByOpenShift = ShiftLog::whereNull('time_out')->whereNotNull('cash_drawer_id')->pluck('cash_drawer_id')->unique();
        $held = $heldByUser->merge($heldByOpenShift)->unique();

        $orphans = $activeDrawers->whereNotIn('id', $held);

        if ($orphans->isEmpty()) {
            $this->info(sprintf('Scanned %d active drawer(s); all are properly held. Nothing to do.', $activeDrawers->count()));
            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d orphan drawer(s):', $orphans->count()));
        foreach ($orphans as $d) {
            $this->line(sprintf('  - drawer id=%d branch_id=%d name=%s updated_at=%s', $d->id, $d->branch_id, $d->name, $d->updated_at));
        }

        if (! $shouldFix) {
            $this->newLine();
            $this->info('Dry run. Re-run with --fix to release these drawers.');
            return self::SUCCESS;
        }

        CashDrawer::whereIn('id', $orphans->pluck('id'))->update(['is_active' => false]);
        $this->info(sprintf('Released %d drawer(s).', $orphans->count()));

        return self::SUCCESS;
    }
}
