<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatServiceProvisioner;
use Illuminate\Console\Command;

class ProvisionDefaultChatServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astrologer:provision-chat-services {--admin_id= : Specific admin ID to provision} {--force : Reset to default price}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision default Chat Consultation services for astrologers with per_message pricing';

    /**
     * Execute the console command.
     */
    public function handle(ChatServiceProvisioner $provisioner): int
    {
        $adminId = $this->option('admin_id');
        $force = (bool) $this->option('force');

        if ($adminId) {
            $service = $provisioner->provisionForAdmin((int) $adminId, $force);
            $this->info("Provisioned chat consultation service #{$service->id} for admin #{$adminId}.");
            return Command::SUCCESS;
        }

        $this->info('Provisioning chat consultation services for all active astrologers...');
        $count = $provisioner->provisionAllActiveAstrologers();
        $this->info("Successfully provisioned {$count} new chat services.");

        return Command::SUCCESS;
    }
}
