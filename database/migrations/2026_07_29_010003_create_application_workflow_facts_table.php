<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_workflow_facts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_session_id');
            $table->uuid('person_id');
            $table->string('state', 32);
            $table->unique(['course_session_id', 'person_id']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_workflow_facts');
    }
};
