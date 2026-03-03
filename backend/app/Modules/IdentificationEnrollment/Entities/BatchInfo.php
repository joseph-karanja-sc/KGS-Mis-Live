<?php

namespace App\Modules\IdentificationEnrollment\Entities;

use Illuminate\Database\Eloquent\Model;

class BatchInfo extends Model
{
    protected $table = 'batch_info';
    public $timestamps=false;
    protected $guarded = [];
}
