<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSmsGatewayConfig extends Model
{
    public const PROVIDER_TWILIO = 'twilio';

    public const PROVIDER_MSG91 = 'msg91';

    public const PROVIDER_LOCAL_TEXT = 'local_text';

    public const PROVIDER_NEXMO = 'nexmo';

    public const PROVIDERS = [
        self::PROVIDER_TWILIO,
        self::PROVIDER_MSG91,
        self::PROVIDER_LOCAL_TEXT,
        self::PROVIDER_NEXMO,
    ];

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'branch_id',
        'provider',
        'channel',
        'is_active',
        'credentials',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
