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
        Schema::create('ps_statuses', function (Blueprint $table) {
            $table->id('dbid');
            $table->timestamps();
            $table->integer('id')->unique();
            $table->string('name')->index();
            $table->string('userDisplayName')->nullable();
            $table->integer('index')->nullable();
            $table->boolean('disabled')->default(false);
            $table->boolean('default')->default(false);
            $table->integer('coreStatus')->default(20);
            $table->integer('requestTypeId')->default(1);
            $table->string('requestTypeKey')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ps_statuses');
    }
};
