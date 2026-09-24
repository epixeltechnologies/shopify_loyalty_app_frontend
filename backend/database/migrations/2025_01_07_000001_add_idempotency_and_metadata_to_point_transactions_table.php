<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * POINT SYSTEM: adds the two columns the loyalty engine's
     * idempotency guarantee depends on.
     *
     * `idempotency_key` is a deterministic, caller-constructed string
     * (e.g. `order_points:{shop_id}:{order_id}`,
     * `birthday_reward:{customer_id}:{year}`) — `PointsLedgerService::post()`
     * checks for an existing row with the same key BEFORE inserting
     * (inside the same locked transaction as the balance update), and
     * the UNIQUE index below is the hard backstop if that check ever
     * races. NULL is allowed (and each NULL is distinct under MySQL's
     * unique-index semantics) for the — increasingly rare — call sites
     * that don't have a natural idempotency key of their own.
     *
     * `metadata` is a JSON bag for anything transaction-specific that
     * doesn't warrant its own column (which point rule config produced
     * this amount, which admin made a manual adjustment, which refund
     * ID a reversal corresponds to) — `note` remains the free-text,
     * merchant/customer-facing description; `metadata` is the
     * structured, code-facing counterpart.
     */
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('source_reference_id');
            $table->json('metadata')->nullable()->after('note');

            $table->unique(['shop_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'metadata']);
        });
    }
};
