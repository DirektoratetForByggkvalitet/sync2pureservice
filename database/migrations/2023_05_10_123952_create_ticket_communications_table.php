<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_communications', function (Blueprint $table) {
            $table->id('internal_id');
            $table->integer('id')->unique();
            $table->integer('ticketId')->nullable();
            $table->integer('changeId')->nullable();
            $table->longText('text');
            $table->string('subject')->nullable();
            $table->integer('direction')->nullable();
            $table->integer('visibility')->nullable();
            $table->json('attachments')->nullable();
            $table->json('attachmentIds')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_communications');
    }
};
