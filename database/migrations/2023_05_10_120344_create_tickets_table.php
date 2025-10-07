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
            $table->integer('id')->nullable();
            $table->integer('requestNumber')->nullable();
            $table->integer('assignedAgentId')->nullable();
            $table->integer('assignedTeamId')->nullable();
            $table->integer('assignedDepartmentId')->nullable();
            $table->integer('userId');
            $table->integer('priorityId')->default(2);
            $table->integer('statusId')->default(1);
            $table->integer('sourceId')->default(1);
            $table->string('customerReference')->nullable();
            $table->integer('category1Id')->nullable();
            $table->integer('category2Id')->nullable();
            $table->integer('category3Id')->nullable();
            $table->integer('ticketTypeId')->nullable();
            $table->integer('visibility')->default(0);

            $table->string('emailAddress')->nullable();
            $table->string('subject');
            $table->longText('description');
            $table->longText('solution')->nullable();

            $table->boolean('eFormidling')->default(false);
            $table->string('action')->default('normalSend');
            $table->json('attachments')->nullable();
            $table->string('pdf')->nullable();

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
