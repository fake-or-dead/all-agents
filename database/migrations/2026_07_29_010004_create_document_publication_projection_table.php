<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_publication_projections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_session_id');
            $table->string('key', 64);
            $table->string('title_th');
            $table->unsignedInteger('version');
            $table->string('checksum', 128)->nullable();
            $table->string('visibility', 32);
            $table->string('approval_state', 32);
            $table->string('lifecycle_state', 32);
            $table->string('quarantine_reason')->nullable();
            $table->string('disposition', 32)->default('local-placeholder');
            $table->unique(['course_session_id', 'key']);
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_publication_projections');
    }
};
