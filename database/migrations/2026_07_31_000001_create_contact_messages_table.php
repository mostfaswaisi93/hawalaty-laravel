<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enquiries from the public landing page.
     *
     * On the PLATFORM connection, not a tenant's: whoever fills this in has no
     * license yet - that is the whole point of the form.
     *
     * Stored rather than only mailed. Mail is the one delivery path that can
     * fail silently in this app (`MAIL_MAILER=log` in development, a wrong SMTP
     * credential in production), and a sales lead that vanished into a log file
     * is a lost customer nobody can even count. The row is the record; the mail
     * is a notification about it.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('subject');
            $table->text('message');

            // Which plan the visitor was looking at when they wrote. Nullable
            // because the footer form has no plan context.
            $table->string('plan')->nullable();

            // Read-state for the admin inbox, not a workflow: a message is
            // either still waiting for someone or it is not.
            $table->timestamp('read_at')->nullable();

            // Kept for abuse triage only - a burst of submissions from one
            // address is the signal that the throttle needs tightening.
            $table->string('ip_address', 45)->nullable();
            $table->string('locale', 5)->nullable();

            $table->timestamps();

            // The inbox lists unread first, newest first, and nothing else.
            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
