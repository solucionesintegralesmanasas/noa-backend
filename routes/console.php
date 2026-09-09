<?php

use Illuminate\Support\Facades\Schedule;
use App\Services\Notifications\NotificationsService;
use App\Services\Fleet\SocialSecurityContributionService;

Schedule::command('fleet:notify-expiring-documents')->dailyAt('08:00');

Schedule::call(function (NotificationsService $service) {
    $service->syncNotifications();
})->hourly();

Schedule::call(function (SocialSecurityContributionService $service) {
    $service->autoUpdateExpiredStatuses();
})->daily();
