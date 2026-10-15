<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingProvider;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de proveedores tecnológicos. */
class BillingProviderService extends BaseService
{
    protected array $searchableFields = ['name', 'email'];

    protected function getModelInstance(): Model
    {
        return new BillingProvider;
    }
}
