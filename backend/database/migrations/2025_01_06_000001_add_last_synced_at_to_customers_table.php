<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Added as a follow-up migration, per this project's append-only
     * convention. Distinct from `updated_at` (bumped by ANY change,
     * including a loyalty-domain action like a VIP tier recalculation)
     * — `last_synced_at` specifically answers "when did we last hear
     * from Shopify about this customer," stamped only by
     * ShopifyCustomerSyncService. Useful for support/ops ("this
     * customer's data hasn't synced in 90 days — investigate") without
     * being polluted by unrelated internal writes.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
