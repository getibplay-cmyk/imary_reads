<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('reader')->index();
            $t->string('locale', 5)->default('ar');
            $t->timestamp('banned_at')->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('authors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('slug')->unique();
            $t->text('bio')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->json('name');
            $t->string('icon')->default('book');
            $t->timestamps();
        });
        Schema::create('works', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('author_id')->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('slug')->unique();
            $t->string('title', 180);
            $t->text('synopsis');
            $t->string('language', 8)->default('ar')->index();
            $t->string('kind', 20)->default('novel');
            $t->string('status', 20)->default('draft')->index();
            $t->string('completion', 20)->default('ongoing');
            $t->json('tags')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('cover_asset')->nullable();
            $t->string('license', 40)->default('all_rights_reserved');
            $t->text('rights_note')->nullable();
            $t->string('source_url', 1000)->nullable();
            $t->boolean('rights_confirmed')->default(false);
            $t->boolean('allow_download')->default(false);
            $t->boolean('allow_comments')->default(true);
            $t->boolean('featured')->default(false);
            $t->timestamp('published_at')->nullable()->index();
            $t->text('moderation_note')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'category_id', 'language']);
        });
        Schema::create('chapters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->string('title', 180);
            $t->unsignedInteger('position');
            $t->longText('content');
            $t->longText('plain_text');
            $t->unsignedInteger('word_count')->default(0);
            $t->unsignedInteger('revision')->default(1);
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->unique(['work_id', 'position']);
        });
        Schema::create('chapter_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('revision');
            $t->string('title', 180);
            $t->longText('content');
            $t->timestamp('created_at');
            $t->unique(['chapter_id', 'revision']);
        });
        Schema::create('work_files', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->string('path')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->string('scan_status', 20)->default('pending')->index();
            $t->text('scan_note')->nullable();
            $t->boolean('import_requested')->default(false);
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $t->text('body');
            $t->string('status', 20)->default('visible')->index();
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('body')->nullable();
            $t->timestamps();
            $t->unique(['work_id', 'user_id']);
        });
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['work_id', 'user_id']);
        });
        Schema::create('follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('author_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['author_id', 'user_id']);
        });
        Schema::create('reading_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('offset')->default(0);
            $t->string('mode', 10)->default('scroll');
            $t->timestamps();
            $t->unique(['user_id', 'work_id']);
        });
        Schema::create('bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('offset')->default(0);
            $t->string('note', 250)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'chapter_id', 'offset']);
        });
        Schema::create('shelves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->timestamps();
            $t->unique(['user_id', 'name']);
        });
        Schema::create('shelf_work', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shelf_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->unique(['shelf_id', 'work_id']);
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('comment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 50);
            $t->text('details');
            $t->string('status', 20)->default('open')->index();
            $t->text('resolution')->nullable();
            $t->timestamps();
        });
        Schema::create('work_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->string('visitor_hash', 64);
            $t->date('day');
            $t->unique(['work_id', 'visitor_hash', 'day']);
        });
        Schema::create('download_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('format', 10);
            $t->timestamp('created_at');
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 100);
            $t->string('subject_type', 80);
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->index();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->json('title');
            $t->json('body');
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'posts', 'notifications', 'audit_logs', 'download_events', 'work_views', 'reports', 'shelf_work', 'shelves', 'bookmarks', 'reading_progress', 'follows', 'favorites', 'reviews', 'comments', 'work_files', 'chapter_versions', 'chapters', 'works', 'categories', 'authors'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'locale', 'banned_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
