<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2.6 — email one-time-code sign in. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('login_codes')) {
            Schema::create('login_codes', function (Blueprint $t) {
                $t->id();
                $t->string('email', 190)->index();
                $t->string('code_hash', 64);
                $t->string('purpose', 12)->default('login');
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->timestamp('expires_at');
                $t->timestamp('used_at')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        $firstRun = ! Schema::hasColumn('users', 'email_verified_at');
        if ($firstRun) Schema::table('users', fn (Blueprint $t) => $t->timestamp('email_verified_at')->nullable());
        // accounts that existed before 2.6 are treated as verified (so OTP sign-in never resets their password)
        if (! Schema::hasColumn('users', 'has_password')) Schema::table('users', fn (Blueprint $t) => $t->boolean('has_password')->default(true));
        if ($firstRun) \Illuminate\Support\Facades\DB::table('users')->update(['email_verified_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
