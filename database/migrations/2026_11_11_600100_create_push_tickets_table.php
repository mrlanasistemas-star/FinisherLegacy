<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expo push tickets awaiting their receipt
 * (https://docs.expo.dev/push-notifications/sending-notifications/#push-receipts).
 * A ticket `ok` only means Expo accepted the message; ~15 minutes later
 * `push:check-receipts` asks for the receipt, deactivates devices whose
 * receipt says `DeviceNotRegistered`, and marks the row checked. Rows are
 * pruned after two days (Expo keeps receipts for 24 h). Additive; down()
 * drops only this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_device_id')->constrained('push_devices')->cascadeOnDelete();
            $table->string('ticket_id', 64)->unique();
            $table->string('status', 16)->nullable();
            $table->string('error', 64)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['checked_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tickets');
    }
};
