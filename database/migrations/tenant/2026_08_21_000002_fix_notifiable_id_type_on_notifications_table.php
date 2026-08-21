<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `notifications.notifiable_id` was created by `$table->morphs('notifiable')`,
 * which is a bigint — but every notifiable in this system (Admin, User) has a
 * UUID primary key.
 *
 * The consequence was total and completely silent: MySQL rejected every insert
 * with "Truncated incorrect INTEGER value", Laravel's notification dispatch
 * swallowed it, and the table stayed empty in every tenant. The bell, the
 * /notifications page and every `database`-channel notification in the product
 * (welcome, password changed, tenant suspended) have therefore never shown
 * anything at all — they were reading a table nothing could ever be written to.
 *
 * Found on 2026-08-21 while building GodAdmin impersonation, whose "notify the
 * tenant owner" step is the first one anybody checked against the database
 * rather than against a mocked notification in a test.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_id', 36)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('notifiable_id')->change();
        });
    }
};
