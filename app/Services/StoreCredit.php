<?php

namespace App\Services;

use App\Models\CreditLog;
use App\Models\User;
use App\Support\Triggers;
use Illuminate\Support\Facades\DB;

/** Store credit wallet: every change is written to credit_log with the resulting balance. */
final class StoreCredit
{
    public static function enabled(): bool
    {
        return setting('credit_on', '1') === '1';
    }

    /** Add (positive) or remove (negative) credit. Returns the new balance. Never goes below zero. */
    public static function add(User $u, int $cents, string $reason, ?int $orderId = null, ?string $actor = null): int
    {
        $balance = DB::transaction(function () use ($u, $cents, $reason, $orderId, $actor) {
            $row = User::whereKey($u->id)->lockForUpdate()->first();
            $new = max(0, (int) $row->credit + $cents);
            $applied = $new - (int) $row->credit;
            $row->update(['credit' => $new]);
            CreditLog::create(['user_id' => $u->id, 'amount' => $applied, 'balance' => $new, 'reason' => mb_substr($reason, 0, 190), 'order_id' => $orderId, 'actor' => $actor, 'created_at' => now()]);

            return $new;
        });
        $u->credit = $balance;
        if ($cents > 0 && $actor !== 'system:restore') {
            Triggers::fire('credit.added', ['email' => $u->email, 'name' => $u->name, 'user' => $u, 'total' => $cents, 'credit_amount' => $cents, 'ref' => 'cr'.$u->id.'-'.now()->timestamp.'-'.$cents]);
        }

        return $balance;
    }

    /** Deduct up to $cents atomically; returns the amount actually taken. */
    public static function take(User $u, int $cents, string $reason, ?int $orderId = null): int
    {
        return DB::transaction(function () use ($u, $cents, $reason, $orderId) {
            $row = User::whereKey($u->id)->lockForUpdate()->first();
            $took = max(0, min($cents, (int) $row->credit));
            if ($took <= 0) return 0;
            $row->update(['credit' => (int) $row->credit - $took]);
            CreditLog::create(['user_id' => $u->id, 'amount' => -$took, 'balance' => (int) $row->credit, 'reason' => mb_substr($reason, 0, 190), 'order_id' => $orderId, 'actor' => 'system', 'created_at' => now()]);

            return $took;
        });
    }

    public static function balance(?User $u): int
    {
        return $u ? (int) User::whereKey($u->id)->value('credit') : 0;
    }
}
