<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Commands;

use Illuminate\Console\Command;

class PruneInvitationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspaces:prune-invitations
                            {--days=30 : Delete invitations older than this many days}
                            {--expired-only : Only delete expired invitations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old or expired workspace invitations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $invitationModel = config('workspaces.models.invitation');
        $days = (int) $this->option('days');

        $query = $invitationModel::query();

        if ($this->option('expired-only')) {
            // Only delete expired invitations that haven't been accepted/declined
            $query->expired();
            $this->info('Pruning expired invitations...');
        } else {
            // Delete all invitations older than X days
            $query->where('created_at', '<', now()->subDays($days));
            $this->info("Pruning invitations older than {$days} days...");
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No invitations to prune.');

            return self::SUCCESS;
        }

        if (! $this->confirm("This will delete {$count} invitation(s). Continue?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Successfully deleted {$deleted} invitation(s).");

        return self::SUCCESS;
    }
}
