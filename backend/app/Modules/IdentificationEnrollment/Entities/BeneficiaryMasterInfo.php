<?php


namespace App\Modules\IdentificationEnrollment\Entities;


use Illuminate\Database\Eloquent\Model;

class BeneficiaryMasterInfo extends Model
{
    protected $table = 'beneficiary_master_info';
    public $timestamps=false;
    protected $guarded=[];
}