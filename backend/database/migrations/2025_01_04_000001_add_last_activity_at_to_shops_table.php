<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Added as a follow-up migration rather than editing the original
     * `create_shops_table` migration in place — this project's
     * migrations are treated as an append-only history once written,
     * the same convention applied to every other schema change.
     *
     * Tracks the last time a shop made an authenticated request (set by
     * App\Http\Middleware\EnsureShopIsActive), independent of
     * `installed_at`/`uninstalled_at`. Useful for support/ops queries
     * ("which installed shops haven't been seen in 30 days") without
     * scanning `audit_logs` or the `security` log channel.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('uninstalled_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
