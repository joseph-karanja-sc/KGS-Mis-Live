<?php

namespace App\Modules\IdentificationEnrollment\Entities;

use Illuminate\Database\Eloquent\Model;

class ImportsTemp extends Model
{
    protected $table='temp_uploads';
    protected $guarded=[];

    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }
}
