<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDefaultsToCwacColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('ppmuserssetup_allocated_schools')
            ->whereNull('cwac_id')
            ->update(['cwac_id' => 0]);
            
        DB::table('ppmuserssetup_allocated_schools')
            ->whereNull('cwac_name')
            ->update(['cwac_name' => 'N/A']);

        DB::statement("ALTER TABLE ppmuserssetup_allocated_schools MODIFY cwac_id INT DEFAULT 0");
        DB::statement("ALTER TABLE ppmuserssetup_allocated_schools MODIFY cwac_name VARCHAR(255) DEFAULT 'N/A'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE ppmuserssetup_allocated_schools MODIFY cwac_id INT DEFAULT NULL");
        DB::statement("ALTER TABLE ppmuserssetup_allocated_schools MODIFY cwac_name VARCHAR(255) DEFAULT NULL");
    }
}