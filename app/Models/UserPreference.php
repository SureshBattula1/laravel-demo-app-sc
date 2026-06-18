<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'dark_mode',
        'language',
        'email_notifications',
        'push_notifications',
        'sms_notifications',
        'date_format',
        'time_format',
        'timezone',
        'items_per_page',
        'dashboard_widgets',
        'default_view',
        'high_contrast',
        'font_size',
        'reduce_motion',
        'additional_settings',
    ];

    protected $casts = [
        'dark_mode' => 'boolean',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'high_contrast' => 'boolean',
        'reduce_motion' => 'boolean',
        'items_per_page' => 'integer',
        'dashboard_widgets' => 'array',
        'additional_settings' => 'array',
    ];

    /**
     * Get the user that owns the preferences
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default preferences
     */
    public static function getDefaults(): array
    {
        return [
            'theme' => 'ocean-blue',
            'dark_mode' => false,
            'language' => 'en',
            'email_notifications' => true,
            'push_notifications' => true,
            'sms_notifications' => false,
            'date_format' => 'YYYY-MM-DD',
            'time_format' => '24h',
            'timezone' => 'UTC',
            'items_per_page' => 10,
            'dashboard_widgets' => null,
            'default_view' => 'grid',
            'high_contrast' => false,
            'font_size' => 'medium',
            'reduce_motion' => false,
            'additional_settings' => null,
        ];
    }

    /**
     * Create default preferences for a user
     */
    public static function createDefaultForUser(int $userId): self
    {
        return self::create(array_merge(
            self::getDefaults(),
            ['user_id' => $userId]
        ));
    }

    /**
     * Update a specific preference
     */
    public function updatePreference(string $key, $value): bool
    {
        if (in_array($key, $this->fillable) && $key !== 'user_id') {
            $this->$key = $value;
            return $this->save();
        }
        return false;
    }

    /**
     * Update multiple preferences at once
     */
    public function updatePreferences(array $preferences): bool
    {
        $filtered = array_filter($preferences, function ($key) {
            return in_array($key, $this->fillable) && $key !== 'user_id';
        }, ARRAY_FILTER_USE_KEY);

        if (empty($filtered)) {
            return false;
        }

        return $this->update($filtered);
    }
}


