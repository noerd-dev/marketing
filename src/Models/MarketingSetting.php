<?php

namespace Noerd\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Marketing\Database\Factories\MarketingSettingFactory;
use Noerd\Traits\BelongsToTenant;

class MarketingSetting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public static function forTenant(int $tenantId): ?self
    {
        return self::withoutGlobalScopes()->firstWhere('tenant_id', $tenantId);
    }

    protected static function newFactory(): MarketingSettingFactory
    {
        return MarketingSettingFactory::new();
    }
}
