<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Cómo funciona / Cómo se conecta con Finisher Legacy / Características /
 * Guía de uso / FAQ" (product UX consolidation brief §61, §111-§114) —
 * optional, structured, never a full CMS: a handful of typed sections per
 * product, not free-form page-builder blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_content_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // text | features | steps | video | faq
            $table->string('title');
            $table->json('content'); // shape depends on `type` — see ProductContentSection
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_content_sections');
    }
};
