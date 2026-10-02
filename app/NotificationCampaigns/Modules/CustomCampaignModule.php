<?php

namespace App\NotificationCampaigns\Modules;

use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Illuminate\Support\Collection;

class CustomCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    public const STATUS_KEY = 'message';

    public function slug(): string
    {
        return 'custom';
    }

    public function label(): string
    {
        return 'Custom';
    }

    public function statuses(): array
    {
        return [
            ['key' => self::STATUS_KEY, 'label' => 'Message'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return false;
    }

    public function confirmSelection(): bool
    {
        return false;
    }

    public function eventDatePolicy(): string
    {
        return 'any';
    }

    public function eligibleTargets(int $branchId, string $date): array
    {
        return $this->enrolledSections($branchId);
    }

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        $map = [];
        foreach ($students as $student) {
            $userId = (int) $student->user_id;
            if ($userId > 0) {
                $map[$userId] = self::STATUS_KEY;
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        return $baseContext;
    }
}
