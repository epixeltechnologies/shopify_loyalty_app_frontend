<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Added as a follow-up migration rather than editing the original
     * `create_webhooks_table` migration — same append-only convention
     * used for every other post-launch schema change (see
     * `add_last_activity_at_to_shops_table`).
     *
     * `payload_hash` (sha256 of the raw request body) is a SECOND,
     * independent duplicate-detection signal alongside
     * `shopify_webhook_id`'s unique constraint — Shopify's webhook ID
     * header is the primary idempotency key, but it's technically
     * optional per-delivery; hashing the payload means a delivery that
     * somehow arrives without that header still can't be silently
     * double-processed if its content is identical to something already
     * received. Indexed (not unique) since two genuinely different
     * events can legitimately hash-collide in theory, however
     * astronomically unlikely — this is a duplicate-detection aid, not
     * a second uniqueness constraint.
     *
     * `failed_at` complements `processed_at`: a webhook event now has a
     * clear, queryable answer to "did this finish, and if not, did it
     * fail or is it still in flight" without inferring it from `status`
     * plus `updated_at` alone — useful for both the retry-inspection
     * tooling this task asks for and for alerting on failure rate.
     */
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('payload_hash', 64)->nullable()->after('payload');
            $table->timestamp('failed_at')->nullable()->after('processed_at');

            $table->index('payload_hash');
            $table->index(['shop_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropIndex(['payload_hash']);
            $table->dropIndex(['shop_id', 'topic']);
            $table->dropColumn(['payload_hash', 'failed_at']);
        });
    }
};
