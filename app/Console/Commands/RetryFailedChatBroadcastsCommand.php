<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChatBroadcastOutbox;
use App\Services\Chat\ChatBroadcaster;
use Illuminate\Console\Command;

class RetryFailedChatBroadcastsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:retry-broadcasts {--limit=50 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and retry failed Ably chat broadcasts from the outbox';

    /**
     * Execute the console command.
     */
    public function handle(ChatBroadcaster $broadcaster): int
    {
        $limit = (int) $this->option('limit');

        $pending = ChatBroadcastOutbox::where('status', 'pending')
            ->where('attempts', '<', 5)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending chat broadcasts in outbox.');
            return 0;
        }

        $this->info("Processing {$pending->count()} pending broadcasts...");

        $success = 0;
        $failed = 0;

        foreach ($pending as $item) {
            $delivered = $broadcaster->retryOutboxItem($item);
            if ($delivered) {
                $success++;
            } else {
                $failed++;
            }
        }

        $this->info("Broadcast retry completed. Delivered: {$success}, Failed/Pending: {$failed}");

        return 0;
    }
}
