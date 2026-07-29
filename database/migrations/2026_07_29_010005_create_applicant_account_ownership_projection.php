<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_account_ownerships', function (Blueprint $table): void {
            $table->uuid('account_id')->primary();
            $table->uuid('person_id')->unique();
            $table->string('account_status', 24);
            $table->string('identity_role', 24);
            $table->timestampsTz();

            $table->index(['account_status', 'identity_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_account_ownerships');
    }
};
