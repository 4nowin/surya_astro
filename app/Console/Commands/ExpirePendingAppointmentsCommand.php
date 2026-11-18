<?php

namespace App\Console\Commands;

use App\Services\Services\ServiceOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingAppointmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monetization:expire-appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and expire pending appointments past their 24-hour acceptance deadline and issue full wallet refunds';

    /**
     * Execute the console command.
     */
    public function handle(ServiceOrderService $serviceOrderService): int
    {
        $this->info('Scanning for unconfirmed appointments exceeding the 24-hour acceptance window...');

        $expiredCount = $serviceOrderService->expirePendingAppointments();

        if ($expiredCount > 0) {
            $msg = "Successfully expired {$expiredCount} appointment(s) and issued automatic full wallet refunds.";
            $this->info($msg);
            Log::info("[Monetization] ExpirePendingAppointmentsCommand: {$msg}");
        } else {
            $this->info('No overdue pending appointments found.');
        }

        return Command::SUCCESS;
    }
}
