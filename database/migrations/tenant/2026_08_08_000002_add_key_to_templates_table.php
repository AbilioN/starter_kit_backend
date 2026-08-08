<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // Identifies a template as filling a well-known system slot
            // (e.g. 'welcome_email') rather than being an arbitrary
            // user-authored one. Not exposed for editing via the templates
            // API — only ever set by SystemTemplateSeeder at provisioning
            // time. A tenant customizes the SLOT by editing that template's
            // body/subject through the normal Templates UI, not by picking
            // which template fills which slot.
            $table->string('key')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
};
