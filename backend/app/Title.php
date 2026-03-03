<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
	public function user() {
		return $this->hasMany(App\user);
	}
	
	public function getNameAttribute($value){
		return ucfirst($value);
	}
}
