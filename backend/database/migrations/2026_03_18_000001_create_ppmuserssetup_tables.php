<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePpmUsersSetupTables extends Migration
{
    /**
     * Run the migrations.
     * ppmuserssetup migration - creates ppmuserssetup_details, ppmuserssetup_allocated_districts, ppmuserssetup_allocated_schools tables
     */
    public function up()
    {
        // Main PPM user details table - one record per user
        Schema::create('ppmuserssetup_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->unique();
            $table->enum('account_type', ['zonal_accountant', 'school_accountant'])->default('school_accountant');
            $table->boolean('has_kgs_app_access')->default(false);
            $table->boolean('has_ppm_app_access')->default(false);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('account_type');
            $table->index('has_kgs_app_access');
            $table->index('has_ppm_app_access');
        });

        // Allocated districts table - multiple records per user allowed
        Schema::create('ppmuserssetup_allocated_districts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ppm_user_detail_id');
            $table->integer('district_id');
            $table->string('district_name')->nullable(); // as district_assigned_string 
            $table->timestamps();
            
            $table->foreign('ppm_user_detail_id')->references('id')->on('ppmuserssetup_details')->onDelete('cascade');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade');
            // unique combo
            $table->unique(['ppm_user_detail_id', 'district_id'], 'ppm_dist_user_unique');
            $table->index('district_id');
        });

        // Allocated schools table - multiple records per user allowed (for zonal accountants)
        Schema::create('ppmuserssetup_allocated_schools', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ppm_user_detail_id');
            $table->integer('school_id');
            $table->string('emis_code')->nullable();
            $table->string('school_name')->nullable(); // as school_assigned_string: emis - school_name - district_name
            $table->integer('cwac_id')->nullable();
            $table->string('cwac_name')->nullable(); // as school_cwac_string
            $table->timestamps();
            
            $table->foreign('ppm_user_detail_id')->references('id')->on('ppmuserssetup_details')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('school_information')->onDelete('cascade');
            // unique combo
            $table->unique(['ppm_user_detail_id', 'school_id'], 'ppm_school_user_unique');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('ppmuserssetup_allocated_schools');
        Schema::dropIfExists('ppmuserssetup_allocated_districts');
        Schema::dropIfExists('ppmuserssetup_details');
    }
}
