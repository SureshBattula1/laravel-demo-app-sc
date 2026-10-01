<?php

namespace App\NotificationCampaigns;

use Illuminate\Support\Collection;

interface NotificationCampaignModule
{
    public function slug(): string;

    public function label(): string;

    /**
     * @return list<array{key:string,label:string}>
     */
    public function statuses(): array;

    public function requiresEventDate(): bool;

    public function confirmSelection(): bool;

    /**
     * @return 'today_only'|'today_or_future'|'any'
     */
    public function eventDatePolicy(): string;

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    public function eligibleTargets(int $branchId, string $date): array;

    /**
     * @param  Collection<int, \App\Models\Student>  $students
     * @return array<int, string> user_id => status_key
     */
    public function classifyByUserId(int $branchId, string $date, Collection $students): array;

    /**
     * @param  array<string, mixed>  $baseContext
     * @return array<string, string>
     */
    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array;
}
