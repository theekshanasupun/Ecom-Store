<?php

/**
 * v3.0.0 post-update hook (executed once by the updater, then deleted).
 * The 2.5 updater writes its own history entry AFTER this file runs, so pruning is deferred to shutdown:
 * keep only the 3 most recent updates in the history + update log + automatic pre-update backups.
 */
// v2.7: seed the loyalty programme (tiers, rewards, badges, challenges) and enrol existing customers. Idempotent.
try {
    \App\Support\Settings::reset();
    \App\Services\LoyaltyReports::backfill();
} catch (\Throwable $e) {
    \Log::warning('v2.7 loyalty backfill: '.$e->getMessage());
}
register_shutdown_function(function () {
    try {
        $keep = 3;
        $row = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'update_history')->first();
        if ($row) {
            $h = json_decode((string) $row->value, true);
            if (is_array($h) && count($h) > $keep) {
                \Illuminate\Support\Facades\DB::table('settings')->where('key', 'update_history')->update(['value' => json_encode(array_slice($h, 0, $keep))]);
            }
        }
        $log = storage_path('logs/updates.log');
        if (is_file($log)) {
            $lines = array_values(array_filter(file($log, FILE_IGNORE_NEW_LINES) ?: []));
            if (count($lines) > $keep) file_put_contents($log, implode("\n", array_slice($lines, -$keep))."\n");
        }
        $zips = glob(storage_path('app/backups/pre-update-*.zip')) ?: [];
        usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($zips, $keep) as $z) @unlink($z);
        \App\Support\Settings::reset();
    } catch (\Throwable) {
    }
});
