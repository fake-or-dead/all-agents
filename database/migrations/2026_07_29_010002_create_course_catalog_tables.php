<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_types', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('name_th');
            $table->string('name_en');
            $table->boolean('active')->default(true);
        });

        Schema::create('centers', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('name_th');
            $table->string('name_en');
            $table->text('address_th');
            $table->string('province_id', 16);
            $table->string('map_url');
            $table->boolean('active')->default(true);
            $table->foreign('province_id')->references('id')->on('provinces');
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('course_type_id', 32);
            $table->string('title_th');
            $table->text('summary_th');
            $table->unsignedSmallInteger('minimum_age')->nullable();
            $table->unsignedSmallInteger('maximum_age')->nullable();
            $table->string('applicant_type', 32)->default('trainee');
            $table->json('approved_categories');
            $table->foreign('course_type_id')->references('id')->on('course_types');
        });

        Schema::create('course_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('course_id', 32);
            $table->string('center_id', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestampTz('registration_opens_at');
            $table->timestampTz('registration_closes_at');
            $table->boolean('invite_only')->default(false);
            $table->boolean('published')->default(false);
            $table->foreign('course_id')->references('id')->on('courses');
            $table->foreign('center_id')->references('id')->on('centers');
            $table->index(['published', 'starts_on']);
        });

        Schema::create('teachers', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('name_th');
            $table->boolean('active')->default(true);
        });

        Schema::create('course_session_teachers', function (Blueprint $table): void {
            $table->uuid('course_session_id');
            $table->string('teacher_id', 32);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->primary(['course_session_id', 'teacher_id']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers');
        });

        Schema::create('course_capacity_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_session_id');
            $table->string('category', 32);
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('reserved_count')->default(0);
            $table->unique(['course_session_id', 'category']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
        });

        Schema::create('course_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_session_id');
            $table->string('key', 64);
            $table->string('title_th');
            $table->string('compatibility_path')->unique();
            $table->string('disposition', 32)->default('local-placeholder');
            $table->unique(['course_session_id', 'key']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
        });

        Schema::create('course_application_facts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_session_id');
            $table->string('actor_id', 128);
            $table->string('state', 32);
            $table->unique(['course_session_id', 'actor_id']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_application_facts');
        Schema::dropIfExists('course_documents');
        Schema::dropIfExists('course_capacity_rules');
        Schema::dropIfExists('course_session_teachers');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('course_types');
    }
};
