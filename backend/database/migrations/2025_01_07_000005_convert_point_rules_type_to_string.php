<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: converts `point_rules.type` from a MySQL `ENUM` to a
     * plain `VARCHAR` — needed to add `review_reward` (the one new
     * earning-rule type this milestone introduces) without a
     * database-specific, non-portable enum-modification statement.
     * Laravel 12's schema builder handles `change()` natively (no
     * `doctrine/dbal` dependency needed, unlike pre-11 versions) across
     * both MySQL (this app's production database) and SQLite (this
     * app's test database — see phpunit.xml), which is what makes this
     * a plain, portable migration rather than driver-conditional raw SQL.
     *
     * A plain string also sidesteps the whole class of problem going
     * forward: any future rule type is just a new allowed value in the
     * `Rule::in([...])` list (see `StorePointRuleRequest`) — already
     * the real, authoritative validation — with no migration needed at
     * all. The database-level enum was always secondary defense-in-depth,
     * never the primary safeguard.
     */
    public function up(): void
    {
        Schema::table('point_rules', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('point_rules', function (Blueprint $table) {
            $table->enum('type', ['points_per_dollar', 'signup_bonus', 'referral_bonus', 'birthday_bonus', 'custom'])->change();
        });
    }
};
