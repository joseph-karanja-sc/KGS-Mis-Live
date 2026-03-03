<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 4/12/2018
 * Time: 11:04 PM
 */

namespace App\Modules\StatusesManagement\Entities;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryStatus extends Model
{
    protected $table='beneficiary_statuses';
    protected $guarded=[];
}