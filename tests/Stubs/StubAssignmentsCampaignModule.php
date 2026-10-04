<?php

namespace Tests\Stubs;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use Illuminate\Support\Collection;

/** Test double: fixed published assignments for a date. */
class StubAssignmentsCampaignModule extends AssignmentsCampaignModule
{
    public function __construct(private Collection $published) {}

    public function assignmentsPublishedOnDate(int $branchId, string $date): Collection
    {
        return $this->published;
    }
}
