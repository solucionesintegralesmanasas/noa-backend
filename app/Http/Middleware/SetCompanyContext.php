<?php

// app/Http/Middleware/SetCompanyContext.php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        // Si Sanctum aún no se ha ejecutado en este punto del pipeline, resolver usuario desde el Bearer token
        if (! Auth::check() && $request->bearerToken()) {
            $personalToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
            if ($personalToken && $personalToken->tokenable && (! $personalToken->expires_at || now()->lessThan($personalToken->expires_at))) {
                Auth::setUser($personalToken->tokenable);
            }
        }

        $companyUuid = $this->resolveCompanyUuid($request);

        if ($companyUuid) {
            $company = Company::withoutGlobalScopes()
                ->where('uuid', $companyUuid)
                ->where('is_active', true)
                ->first();

            if (! $company) {
                throw new NotFoundHttpException('Company not found or inactive');
            }

            if (Auth::check()) {
                $user = Auth::user();
                $companyUser = $user->companies()->where('company_user.company_uuid', $company->uuid)->first()
                    ?? $user->companies()->where('companies.uuid', $company->uuid)->first()
                    ?? $user->companies()->first();

                if (! $companyUser && ! $user->hasRole('SUPERADMIN')) {
                    throw new AccessDeniedHttpException('Access denied to this company');
                }

                $thirdPartyUuid = $companyUser?->pivot?->third_party_uuid;
                if ($thirdPartyUuid) {
                    $request->attributes->set('current_third_party_uuid', $thirdPartyUuid);
                }

                if ($user->hasRole('CONDUCTOR') && $thirdPartyUuid) {
                    $affiliate = \App\Models\OwnerDriver::withoutGlobalScopes()->whereIn(
                        'driver_license_uuid',
                        \App\Models\DriverLicense::withoutGlobalScopes()
                            ->where('third_party_uuid', $thirdPartyUuid)
                            ->whereIn('status', ['VIGENTE', 'ACTIVA'])
                            ->pluck('uuid')
                    )->first();

                    if ($affiliate) {
                        $request->attributes->set('current_affiliate_uuid', $affiliate->third_party_uuid);
                    }
                }
            }

            $request->attributes->set('current_company_uuid', $company->uuid);
            $request->attributes->set('current_company', $company);
            session(['current_company_uuid' => $company->uuid]);
        }

        return $next($request);
    }

    protected function resolveCompanyUuid(Request $request): ?string
    {
        return $request->header('X-Company-UUID')
            ?? $request->header('X-Tenant-ID')
            ?? $request->input('company_uuid')
            ?? session('current_company_uuid')
            ?? Auth::user()?->companies()->first()?->uuid;
    }
}
