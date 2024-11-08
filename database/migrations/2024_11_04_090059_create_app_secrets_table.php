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
        Schema::create('app_secrets', function (Blueprint $table) {
            $table->id('internal_id');
            $table->timestamps();
            $table->string('id')->unique();
            $table->dateTimeTz('startDateTime')->index();
            $table->dateTimeTz('endDateTime')->index();
            $table->string('displayName')->nullable();
            $table->string('appName');
            $table->string('appId');
            $table->string('keyType')->index()->default('password');
            
            $table->string('comments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_secrets');
    }
};
