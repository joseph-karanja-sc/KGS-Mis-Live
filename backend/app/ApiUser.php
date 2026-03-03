<?php

namespace App;

use App\Auth\CustomApiUserProvider;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use SMartins\PassportMultiauth\HasMultiAuthApiTokens;

class ApiUser extends Authenticatable
{
    use Notifiable, HasMultiAuthApiTokens;// HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'client_secret', 'remember_token',
    ];

    public function getEmailAttribute()
    {
        return $this->client_username;
    }

    public function getAuthPassword()
    {
        return $this->client_secret;
    }

    public function validateForPassportPasswordGrant($client_secret)
    {
        $apiUserProvider=new CustomApiUserProvider();
        return  $apiUserProvider->validateCredentials($this,[
            'client_username' => $this->client_username,
            'client_secret' => $client_secret,
            'uuid'=>$this->uuid
        ]);
    }

    public function findForPassport($client_username)
    {
        $apiUserProvider=new CustomApiUserProvider();
        return $apiUserProvider->retrieveByCredentials([
            'client_username' => $client_username,
        ]);
    }

}
