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
        Schema::table('tags', function (Blueprint $table) {
            $table->string('tag_category')->nullable()->after('slug');
            $table->string('color')->nullable()->after('tag_category');
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type', 191);
            $table->string('taggable_id', 191);
            $table->timestamps();

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['tag_category', 'color']);
        });
    }
};
