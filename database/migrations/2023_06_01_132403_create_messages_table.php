<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            //$table->id();
            $table->uuid('id')->primary();
            $table->timestamps();
            $table->integer('sender_id')->nullable();
            $table->string('receiver_id')->nullable();
            $table->string('documentStandard')->default('urn:no:difi:arkivmelding:xsd::arkivmelding');
            $table->string('documentType')->default('arkivmelding');
            $table->uuid('conversationId')->default(Str::uuid());
            $table->string('processIdentifier')->default(
                config('eformidling.process_pre').
                config('eformidling.out.type').
                config('eformidling.process_post')
            );
            $table->json('content');
            $table->string('mainDocument')->nullable();
            $table->json('attachments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
