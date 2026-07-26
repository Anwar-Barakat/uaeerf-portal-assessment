<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Soap\RegistrationsService;
use Illuminate\Console\Command;

/**
 * Link a local user to a federation account the web service will accept.
 *
 * Normally this happens by itself: a portal user logs in, their
 * eefsys.WebUserProfile row is matched and its UserId stored. This command
 * covers the cases where it cannot - an account created in this app, or a
 * demo run - so linking is an explicit, validated, auditable action rather
 * than a hand-edit of the database.
 */
class LinkFederationAccount extends Command
{
    protected $signature = 'federation:link
                            {email : Local user email}
                            {federation-user-id : eefsys.WebUserProfile.UserId}
                            {--force : Link even if the federation will not accept this id}';

    protected $description = 'Link a local user to a federation user id, verifying the federation accepts it';

    public function handle(RegistrationsService $registrations): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error("No local user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $federationUserId = (int) $this->argument('federation-user-id');

        if (!$registrations->acceptsUserId($federationUserId)) {
            $this->error("The federation does not accept registrations for user id {$federationUserId}.");
            $this->line('Accepted ids come from Get_DEC_UserList. Run with --force to link anyway');
            $this->line('(rider registration will be refused, but renewals will still work).');

            if (!$this->option('force')) {
                return self::FAILURE;
            }
        }

        $previous = $user->federation_user_id;
        $user->update(['federation_user_id' => $federationUserId]);

        $this->info("Linked {$user->email}: " . ($previous ?? 'none') . " -> {$federationUserId}");

        return self::SUCCESS;
    }
}
