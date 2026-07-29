<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->unsignedBigInteger('profile_version')->default(1);
        });

        Schema::create('person_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id')->unique();
            $table->text('email_encrypted')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
        });

        Schema::create('person_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id')->unique();
            $table->text('address_line_1_encrypted');
            $table->text('address_line_2_encrypted')->nullable();
            $table->string('province_id', 16);
            $table->string('amphoe_id', 16);
            $table->string('tambon_id', 16);
            $table->string('postcode', 5);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->foreign('province_id')->references('id')->on('provinces')->restrictOnDelete();
            $table->foreign('amphoe_id')->references('id')->on('amphoes')->restrictOnDelete();
            $table->foreign('tambon_id')->references('id')->on('tambons')->restrictOnDelete();
        });

        Schema::create('person_training_experiences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->text('course_name_encrypted');
            $table->text('provider_name_encrypted');
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->index(['person_id', 'started_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_training_experiences');
        Schema::dropIfExists('person_addresses');
        Schema::dropIfExists('person_contacts');
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn('profile_version');
        });
    }
};
