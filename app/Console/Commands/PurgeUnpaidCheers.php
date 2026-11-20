<?php

namespace App\Console\Commands;

use App\Actions\Training\PurgeUnpaidCheerMessages;
use Illuminate\Console\Command;

/**
 * Paid cheer messages whose payment never arrived lose their audio and text
 * (docs/renovacion/tarjeta-animo.md §Pagos). Scheduled every 10 minutes in
 * routes/console.php; safe to run by hand at any time.
 */
class PurgeUnpaidCheers extends Command
{
    protected $signature = 'training:purge-unpaid-cheers';

    protected $description = 'Borra el audio de los mensajes de ánimo pagados que nunca se pagaron';

    public function handle(PurgeUnpaidCheerMessages $purge): int
    {
        $count = $purge->handle();
        $this->info("Mensajes sin pago eliminados: {$count}");

        return self::SUCCESS;
    }
}
