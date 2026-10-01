<?php

namespace App\NotificationCampaigns;

class NotificationCampaignModuleRegistry
{
    /** @var array<string, NotificationCampaignModule> */
    private array $modules = [];

    public function register(NotificationCampaignModule $module): void
    {
        $this->modules[$module->slug()] = $module;
    }

    public function get(string $slug): ?NotificationCampaignModule
    {
        return $this->modules[$slug] ?? null;
    }

    /**
     * @return array<string, NotificationCampaignModule>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Legacy API shape: slug => list of statuses.
     *
     * @return array<string, list<array{key:string,label:string}>>
     */
    public function statusesByModule(): array
    {
        $out = [];
        foreach ($this->modules as $slug => $module) {
            $out[$slug] = $module->statuses();
        }

        return $out;
    }

    /**
     * @return array<string, array{label:string,requires_event_date:bool,confirm_selection:bool}>
     */
    public function metaByModule(): array
    {
        $out = [];
        foreach ($this->modules as $slug => $module) {
            $out[$slug] = [
                'label' => $module->label(),
                'requires_event_date' => $module->requiresEventDate(),
                'confirm_selection' => $module->confirmSelection(),
                'event_date_policy' => $module->eventDatePolicy(),
            ];
        }

        return $out;
    }
}
