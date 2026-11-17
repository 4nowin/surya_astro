<?php

namespace Database\Seeders;

use App\Services\Chat\ChatServiceProvisioner;
use Illuminate\Database\Seeder;

class DefaultChatConsultationServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ChatServiceProvisioner $provisioner): void
    {
        $provisioner->provisionAllActiveAstrologers();
    }
}
