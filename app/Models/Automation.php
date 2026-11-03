<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    protected $guarded = [];

    public function isCustom(): bool
    {
        return ($this->type ?? 'system') === 'custom';
    }

    public function conditionList(): array
    {
        $c = json_decode((string) $this->conditions, true);

        return is_array($c) ? $c : [];
    }

    /** Steps of the sequence: [{delay_minutes, subject, preheader, blocks[]}]. System automations have a single step (design only). */
    public function steps(): array
    {
        $d = json_decode((string) $this->design, true);
        $steps = is_array($d) && isset($d['steps']) && is_array($d['steps']) ? array_values($d['steps']) : [];
        if ($this->isCustom()) return $steps;

        return $steps ? [$steps[0]] : [];
    }

    public function hasDesign(): bool
    {
        foreach ($this->steps() as $s) if (! empty($s['blocks'])) return true;

        return false;
    }

    public function queueRows()
    {
        return $this->hasMany(AutomationQueue::class);
    }
}
