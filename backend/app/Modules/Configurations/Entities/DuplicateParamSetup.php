<?php

namespace App\Modules\Configurations\Entities;

use Illuminate\Database\Eloquent\Model;

class DuplicateParamSetup extends Model
{
    protected $table = 'beneficiary_duplicates_setup';
    protected $guarded = [];
}
