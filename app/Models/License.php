<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_uuid',
        'license_key',
        'signature',
        'expiry_date',
        'status',
        'online_verification_enabled',
        'activated_ip',
        'activated_domain',
        'offline_verifications',
        'online_verifications',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'online_verification_enabled' => 'boolean',
        'offline_verifications' => 'integer',
        'online_verifications' => 'integer',
    ];

    /**
     * Get the company that owns this license.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_uuid', 'company_uuid');
    }

    /**
     * Check if license is valid (not expired and active status).
     */
    public function isValid(): bool
    {
        return $this->status === 'active' && $this->expiry_date >= now()->toDateString();
    }

    /**
     * Increment offline verification counter.
     */
    public function incrementOffline(): void
    {
        $this->offline_verifications += 1;
        $this->save();
    }

    /**
     * Increment online verification counter.
     */
    public function incrementOnline(): void
    {
        $this->online_verifications += 1;
        $this->save();
    }

    /**
     * Soft expire the license.
     */
    public function expire(): void
    {
        $this->status = 'expired';
        $this->save();
    }
}