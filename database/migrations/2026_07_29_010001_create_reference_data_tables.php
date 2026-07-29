<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->string('id', 16)->primary();
            $table->string('code', 8)->unique();
            $table->string('name_th');
            $table->string('name_en');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
        });

        Schema::create('amphoes', function (Blueprint $table): void {
            $table->string('id', 16)->primary();
            $table->string('province_id', 16);
            $table->string('code', 8)->unique();
            $table->string('name_th');
            $table->string('name_en');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreign('province_id')->references('id')->on('provinces');
            $table->index(['province_id', 'active', 'display_order']);
        });

        Schema::create('tambons', function (Blueprint $table): void {
            $table->string('id', 16)->primary();
            $table->string('amphoe_id', 16);
            $table->string('code', 8)->unique();
            $table->string('name_th');
            $table->string('name_en');
            $table->string('postcode', 5);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreign('amphoe_id')->references('id')->on('amphoes');
            $table->index(['amphoe_id', 'active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tambons');
        Schema::dropIfExists('amphoes');
        Schema::dropIfExists('provinces');
    }
};
