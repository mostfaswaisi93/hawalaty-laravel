<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('reference')->unique();

            $table->date('period_from');
            $table->date('period_to');

            $table->decimal('total_payments', 12, 2);
            $table->decimal('total_expenses', 12, 2);

            $table->decimal('distributable_profit', 12, 2);
            $table->decimal('total_entitlement', 12, 2);
            $table->decimal('total_drawn', 12, 2);
            $table->decimal('total_net', 12, 2);

            $table->decimal('retained', 12, 2);

            $table->string('status')->default('draft');

            $table->foreignUlid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
