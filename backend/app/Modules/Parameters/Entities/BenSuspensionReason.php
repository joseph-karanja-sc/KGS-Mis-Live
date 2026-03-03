<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 1/16/2018
 * Time: 1:46 PM
 */

namespace App\Modules\Parameters\Entities;

use Illuminate\Database\Eloquent\Model;

class BenSuspensionReason extends Model
{
    protected $table = 'suspension_reasons';
    protected $guarded = [];
}