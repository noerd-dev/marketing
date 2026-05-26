<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketing_settings')) {
            return;
        }

        Schema::create('marketing_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('from_email')->nullable();
            $table->string('reply_email')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_settings');
    }
};
