<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CUSTOMERS: prepares birthday-reward eligibility — see
     * docs/POINTS_ENGINE.md#birthday-reward for the full privacy/scope
     * note. Deliberately stored as separate `birthday_month`/`birthday_day`
     * smallints, NOT a full date: a birth YEAR is materially more
     * sensitive PII than month/day alone (it reveals age, relevant to
     * data-minimization principles), and the reward rule only ever
     * needs "is today the customer's birthday," never their age or
     * full birthdate. No source populates these columns yet in this
     * milestone — Shopify's core customer object doesn't universally
     * include a birthday (it's typically a merchant-defined metafield);
     * wiring a real collection source (a metafield sync, or a
     * storefront-widget form) is left for a future milestone. The
     * scheduled `AwardBirthdayPointsJob` simply finds nothing to do
     * until some customer rows have these populated, which is the
     * correct, safe behavior for unpopulated data rather than an error.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('birthday_month')->nullable()->after('last_name');
            $table->unsignedTinyInteger('birthday_day')->nullable()->after('birthday_month');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['shop_id', 'birthday_month', 'birthday_day']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'birthday_month', 'birthday_day']);
            $table->dropColumn(['birthday_month', 'birthday_day']);
        });
    }
};
