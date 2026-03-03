<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSelectableanswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grm_selectableanswers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table-> bigInteger('parent_question')->unsigned()->index();
            $table->foreign('parent_question')->references('id')->on('grm_formquestions')->onDelete('cascade')->onUpdate('cascade');
            $table->string('question_number');
            $table->string('description');
            $table->string('answer_type');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grm_selectableanswers');
    }
}
