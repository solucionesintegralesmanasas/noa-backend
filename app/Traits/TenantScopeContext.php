<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Mantiene el estado global compartido para exenciones y bypass del scope multi-tenant (Issue #2).
 */
final class TenantScopeContext
{
    public static bool $bypass = false;
}
