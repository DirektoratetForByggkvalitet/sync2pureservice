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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id('internal_id');
            $table->integer('id')->unique();
            $table->integer('requestNumber')->unique();
            $table->integer('assignedAgentId')->nullable();
            $table->integer('assignedTeamId')->nullable();
            $table->integer('assignedDepartmentId')->nullable();
            $table->integer('userId');
            $table->integer('priorityId');
            $table->integer('statusId');
            $table->integer('sourceId');
            $table->integer('category1Id')->nullable();
            $table->integer('category2Id')->nullable();
            $table->integer('category3Id')->nullable();
            $table->integer('ticketTypeId');
            $table->integer('visibility');

            $table->string('emailAddress');
            $table->string('subject');
            $table->longText('description');

            $table->boolean('eForsendelse')->default(false);
            $table->string('action')->default('normalSend');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
