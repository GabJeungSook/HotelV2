<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('override_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('guest_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('checkin_detail_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('transfer_reason_id')->nullable()->constrained()->onDelete('set null');
            $table->string('transaction_type')->default('transfer');
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->onDelete('set null');
            $table->foreignId('to_room_id')->nullable()->constrained('rooms')->onDelete('set null');
            $table->foreignId('from_type_id')->nullable()->constrained('types')->onDelete('set null');
            $table->foreignId('to_type_id')->nullable()->constrained('types')->onDelete('set null');
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->decimal('new_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'declined', 'auto_approved'])->default('pending');
            $table->text('decline_reason')->nullable();
            $table->json('request_data')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['supervisor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('override_requests');
    }
};
