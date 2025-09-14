<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProcessUserStream extends Command
{
    protected $signature = 'process:user-stream {userId}';
    protected $description = 'Process Redis stream for a user';

    public function handle()
    {
        $userId = $this->argument('userId');
        $streamKey = "user_stream:{$userId}";
        $lastId = '0-0'; // start from the beginning

        while (true) {
            $entries = Redis::xread([$streamKey => $lastId], 10, 5000);

            if (!$entries) {
                continue;
            }

            foreach ($entries as $stream => $events) {
                foreach ($events as $id => $data) {
                    $this->info("Processing event $id for user $userId: " . json_encode($data));

                    DB::transaction(function () use ($userId, $data) {
                        $user = User::whereKey($userId)->lockForUpdate()->first();

                        if (!$user) {
                            return;
                        }

                        switch ($data['action'] ?? null) {
                            case 'create':
                                $user->increment('balance', $data['amount'] ?? 0);
                                break;

                            case 'update':
                                $old = $data['old_amount'] ?? 0;
                                $new = $data['new_amount'] ?? 0;
                                $delta = $new - $old;
                                $user->increment('balance', $delta);
                                break;

                            case 'delete':
                                $user->decrement('balance', $data['old_amount'] ?? 0);
                                break;

                            default:
                                $this->warn("Unknown action: " . json_encode($data));
                                break;
                        }
                    });

                    $lastId = $id;
                }
            }
        }
    }
}
