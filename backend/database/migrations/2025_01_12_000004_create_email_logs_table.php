<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EMAIL: an audit trail of every notification send attempt — never
     * the rendered email body itself (see the task's explicit "do not
     * log sensitive email content unnecessarily"). `recipient` is the
     * email address only, not the customer's name or any other PII
     * beyond what's operationally necessary to answer "did this email
     * actually go out."
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('notification_type', 40);
            $table->string('recipient');
            $table->string('status', 20)->default('queued'); // queued | sent | failed
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'notification_type']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
