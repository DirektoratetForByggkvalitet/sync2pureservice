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
        Schema::create('messages', function (Blueprint $table) {
            $table->id('internal_id');
            $table->timestamps();
            $table->integer('senderId')->nullable();
            $table->string('sender');
            $table->string('receiver');
            $table->string('receiverId')->nullable();
            $table->string('documentId');
            $table->string('documentStandard');
            $table->string('conversationId');
            $table->string('conversationIdentifier');
            $table->json('content');
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
