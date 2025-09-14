<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Index for tenant filter
            $table->index('tenant_id', 'idx_users_tenant_id');
        });

        Schema::table('form_data', function (Blueprint $table) {
            // Indexes for foreign keys
            $table->index('user_id', 'idx_form_data_user_id');
            $table->index('option_id', 'idx_form_data_option_id');

            // Composite index for queries involving both user_id + option_id
            $table->index(['user_id', 'option_id'], 'idx_form_data_user_option');
        });

        Schema::table('form_options', function (Blueprint $table) {
            // Fulltext index for LIKE %keyword%
            $table->fullText('label', 'idx_form_options_label_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_id');
        });

        Schema::table('form_data', function (Blueprint $table) {
            $table->dropIndex('idx_form_data_user_id');
            $table->dropIndex('idx_form_data_option_id');
            $table->dropIndex('idx_form_data_user_option');
        });

        Schema::table('form_options', function (Blueprint $table) {
            $table->dropFullText('idx_form_options_label_fulltext');
        });
    } 
};
