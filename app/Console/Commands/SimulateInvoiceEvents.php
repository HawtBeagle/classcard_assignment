<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\UserEventProducer;

class SimulateInvoiceEvents extends Command
{
    protected $signature = 'simulate:invoice {userId}';
    protected $description = 'Simulate invoice events for a user';

    public function handle()
    {
        $userId = $this->argument('userId');
        $producer = new UserEventProducer();

        $invoiceAmount = rand(100, 1000);
        $newAmount = rand(100, 1000);
        $invoiceId = rand(1000, 9999);

        $producer->pushEvent($userId, [
            'amount' => $invoiceAmount,
            'invoice_id' => $invoiceId,
            'action' => 'create',
        ]);

        $producer->pushEvent($userId, [
            'old_amount' => $invoiceAmount,
            'new_amount' => $newAmount,
            'invoice_id' => $invoiceId,
            'action' => 'update',
        ]);

        $producer->pushEvent($userId, [
            'old_amount' => $invoiceAmount,
            'invoice_id' => $invoiceId,
            'action' => 'delete',
        ]);

        $this->info("Invoice event pushed for user {$userId}");
    }
}
