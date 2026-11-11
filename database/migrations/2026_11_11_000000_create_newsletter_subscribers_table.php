<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('newsletter.database.connection');
    }

    public function up(): void
    {
        // One row per email address: pending until the link in the
        // confirmation email is followed, then subscribed, until it
        // unsubscribes.
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email', 254)->unique();
            $table->string('name')->nullable();
            $table->string('status', 20)->index();
            $table->string('site')->nullable();
            $table->string('locale')->nullable();
            $table->text('consent')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
