<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class SystemSettingService
{
    public function group(string $group): array
    {
        return SystemSetting::where('group', $group)->pluck('value', 'key')->all();
    }

    public function save(string $group, array $values, ?int $userId): void
    {
        DB::transaction(function () use ($group, $values, $userId): void {
            SystemSetting::whereIn('key', array_keys($values))->lockForUpdate()->get();

            foreach ($values as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'value' => $value === null ? null : (string) $value, 'type' => 'string', 'updated_by' => $userId]
                );
            }
        });
    }
}
