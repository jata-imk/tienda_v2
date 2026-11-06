<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->index();
            $table->string('module', 32);
            $table->string('action', 96);
            $table->string('outcome', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('role_code', 50)->nullable();
            $table->string('role_name')->nullable();
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('source', 16);
            $table->string('route', 191)->nullable();
            $table->string('method', 8)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['occurred_at', 'id']);
            $table->index(['actor_id', 'occurred_at', 'id']);
            $table->index(['action', 'occurred_at', 'id']);
            $table->index(['module', 'occurred_at', 'id']);
            $table->index(['entity_type', 'entity_id', 'id']);
        });

        Schema::create('audit_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_event_id')->constrained('audit_events')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('operation', 24);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->index(['audit_event_id', 'id']);
            $table->index(['entity_type', 'entity_id', 'audit_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_changes');
        Schema::dropIfExists('audit_events');
    }
};
