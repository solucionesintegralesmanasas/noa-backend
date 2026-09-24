<?php

namespace App\Console\Commands;

use App\Services\Notifications\EmailLogService;
use Illuminate\Console\Command;

class NotifyExpiringVehicleDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleet:notify-expiring-documents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía el digest consolidado de vencimientos al correo principal de cada empresa';

    /**
     * Execute the console command.
     */
    public function handle(EmailLogService $emailService)
    {
        $this->info('Iniciando verificación de vencimientos de documentos...');

        $emailService->notifyExpiringDocuments();

        $this->info('Verificación y envío de notificaciones completada con éxito.');
    }
}
