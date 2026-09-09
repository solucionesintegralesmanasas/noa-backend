<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

// use App\Exceptions\CompanyNotFoundException;

/**
 * @mixin Model
 *
 * @method static void creating(\Closure $callback)
 * @method static void addGlobalScope(string $identifier, \Closure|\Illuminate\Database\Eloquent\Scope $scope)
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyUuid = self::resolveCurrentCompanyUuid();

            if ($companyUuid) {
                $builder->where($builder->getModel()->getTable().'.company_uuid', $companyUuid);
            } elseif (app()->environment('production')) {
                // throw new CompanyNotFoundException('Company context required');
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->company_uuid) && $model->isFillable('company_uuid')) {
                $model->company_uuid = self::resolveCurrentCompanyUuid();
            }
        });
    }

    protected static function resolveCurrentCompanyUuid(): ?string
    {
        return Request::instance()->attributes->get('current_company_uuid')
            ?? Request::instance()->header('X-Company-UUID')
            ?? Request::instance()->input('company_uuid')
            ?? session('current_company_uuid')
            ?? Auth::user()?->companies()->first()?->uuid;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }
}
