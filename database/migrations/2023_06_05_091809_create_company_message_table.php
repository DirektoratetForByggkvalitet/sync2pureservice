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
        Schema::create('company_message', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_internal_id')->unsigned();
            $table->uuid('message_id');
            $table->string('type', 20)->default('receiver');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_message');
    }
};
