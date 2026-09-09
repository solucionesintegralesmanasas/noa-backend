<?php
Route::get('/test-vehicles', function () {
    \$companyUuid = '359df5a8-a673-4ec3-91ce-1f856f89f420';
    \$thirdPartyUuid = '95fe5fcf-abe7-4761-9625-b786bbda6558';
    \$service = app(\App\Services\Fleet\VehicleService::class);
    return \$service->getAllVehicles(\$companyUuid, \$thirdPartyUuid);
});
