<?php

namespace App\Modules\Parameters\Entities;

use Illuminate\Database\Eloquent\Model;

class SuspensionReason extends Model
{
    protected $table = 'suspension_reasons';
    protected $guarded = [];
}
