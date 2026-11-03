<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** v2.6 — email builder designs, custom (trigger based) automations, automation queue and newsletters. */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'type' => fn (Blueprint $t) => $t->string('type', 10)->default('system'),          // system | custom
            'trigger_event' => fn (Blueprint $t) => $t->string('trigger_event', 40)->nullable(),
            'conditions' => fn (Blueprint $t) => $t->text('conditions')->nullable(),            // json
            'design' => fn (Blueprint $t) => $t->longText('design')->nullable(),                // json {steps:[{delay_minutes,subject,preheader,blocks}]}
            'preheader' => fn (Blueprint $t) => $t->string('preheader', 190)->nullable(),
            'from_name' => fn (Blueprint $t) => $t->string('from_name', 100)->nullable(),
        ] as $col => $def) {
            if (! Schema::hasColumn('automations', $col)) Schema::table('automations', $def);
        }

        if (! Schema::hasTable('automation_queue')) {
            Schema::create('automation_queue', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('automation_id')->index();
                $t->string('email', 190)->index();
                $t->string('name', 150)->nullable();
                $t->string('ref', 80);
                $t->unsignedTinyInteger('step')->default(0);
                $t->longText('payload')->nullable();
                $t->timestamp('run_at')->nullable()->index();
                $t->string('status', 10)->default('pending');     // pending | sent | skipped | failed
                $t->timestamp('sent_at')->nullable();
                $t->string('error', 190)->nullable();
                $t->timestamps();
                $t->unique(['automation_id', 'email', 'ref']);
            });
        }

        if (! Schema::hasTable('newsletters')) {
            Schema::create('newsletters', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150);
                $t->string('subject', 190)->default('');
                $t->string('preheader', 190)->nullable();
                $t->longText('design')->nullable();
                $t->string('status', 10)->default('draft');       // draft | scheduled | sending | sent
                $t->string('segment', 20)->default('all');        // all | customers | subscribers_only | never_ordered
                $t->string('from_name', 100)->nullable();
                $t->timestamp('scheduled_at')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->unsignedInteger('recipients')->default(0);
                $t->unsignedInteger('sent')->default(0);
                $t->unsignedInteger('failed')->default(0);
                $t->unsignedBigInteger('last_id')->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['newsletters', 'automation_queue'] as $t) Schema::dropIfExists($t);
    }
};
