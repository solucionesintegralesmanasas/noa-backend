<?php

use Illuminate\Support\Facades\Schedule;
use App\Services\Notifications\NotificationsService;
use App\Services\Fleet\SocialSecurityContributionService;

// Digest de vencimientos: franja de la mañana y franja de la tarde.
// El servicio decide la franja por la hora y evita duplicados por empresa,
// fecha y franja, así que es seguro ante reintentos del webcron.
Schedule::command('fleet:notify-expiring-documents')->dailyAt('08:00');
Schedule::command('fleet:notify-expiring-documents')->dailyAt('15:00');

Schedule::call(function (NotificationsService $service) {
    $service->syncNotifications();
})->hourly();

Schedule::call(function (SocialSecurityContributionService $service) {
    $service->autoUpdateExpiredStatuses();
})->daily();
