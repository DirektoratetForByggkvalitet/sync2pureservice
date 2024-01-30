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
        Schema::create('ps_emails', function (Blueprint $table) {
            $table->id('dbid');
            $table->timestamps();
            $table->integer('id')->unique();
            $table->integer('requestId');
            $table->integer('assetId')->nullable();
            $table->string('from');
            $table->string('fromName');
            $table->string('to')->index();
            $table->string('cc')->nullable();
            $table->string('bcc')->nullable();
            $table->string('messageId');
            $table->text('subject');
            $table->longText('text');
            $table->string('inReplyTo')->nullable();
            $table->string('references')->nullable();
            $table->integer('emailDataId')->nullable();
            $table->integer('attachmentStrategy')->default(0);
            $table->integer('direction');
            $table->integer('channelId');
            $table->integer('status');
            $table->dateTime('statusDate')->nullable();
            $table->string('statusMessage')->nullable();
            $table->string('statusDetails')->nullable();
            $table->boolean('isInitial')->default(false);
            $table->boolean('isSystem')->default(false);
            $table->boolean('isBoundary')->default(false);
            $table->dateTime('created')->nullable();
            $table->dateTime('modified')->nullable();
            $table->integer('createdBy')->nullable();
            $table->integer('modifiedBy')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ps_emails');
    }
};
