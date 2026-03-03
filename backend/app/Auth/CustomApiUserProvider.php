<?php
/**
 * Created by PhpStorm.
 * User: Kip
 * Date: 12/13/2019
 * Time: 11:18 PM
 */

namespace App\Auth;

use App\ApiUser;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class CustomApiUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        // TODO: Implement retrieveById() method.

        $qry = ApiUser::where('id', '=', $identifier);

        if ($qry->count() > 0) {
            $apiuser = $qry->select('*')->first();

            $attributes = array(
                'id' => $apiuser->id,
                'client_username' => $apiuser->client_username,
                'client_secret' => $apiuser->client_secret,
                'client_name' => $apiuser->client_name
            );

            return $apiuser;
        }
        return null;
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.
     *
     * @param  mixed $identifier
     * @param  string $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        // TODO: Implement retrieveByToken() method.
        $qry = ApiUser::where('id', '=', $identifier)->where('remember_token', '=', $token);

        if ($qry->count() > 0) {
            $apiuser = $qry->select('*')->first();

            $attributes = array(
                'id' => $apiuser->id,
                'client_username' => $apiuser->client_username,
                'client_secret' => $apiuser->client_secret,
                'client_name' => $apiuser->client_name
            );

            return $apiuser;
        }
        return null;


    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $apiuser
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $apiuser, $token)
    {
        // TODO: Implement updateRememberToken() method.
        $apiuser->setRememberToken($token);

        $apiuser->save();

    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        // TODO: Implement retrieveByCredentials() method.
        $qry = ApiUser::where('client_username', '=', $credentials['client_username']);
        if ($qry->count() > 0) {
            //$apiuser = $qry->select('id', 'username', 'first_name', 'last_name', 'email', 'password')->first();
            $apiuser = $qry->select('*')->first();
            return $apiuser;
        }
        return null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $apiuser
     * @param  array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $apiuser, array $credentials)
    {
        // TODO: Implement validateCredentials() method.
        // we'll assume if a user was retrieved, it's good
        $client_username = $credentials['client_username'];
        $client_secret = $credentials['client_secret'];
        $uuid = $credentials['uuid'];
        $hashedPwd = hashPwd($client_username, $uuid, $client_secret);

        if ($apiuser->client_username == $credentials['client_username'] && $apiuser->getAuthPassword() == $hashedPwd)
        {
            $apiuser->last_access_time = Carbon::now();
            $apiuser->save();
            //log
            $loginLogParams = array(
                'account_id' => $apiuser->id,
                'client_username' => aes_decrypt($client_username),
                'ip_address' => request()->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'time' => Carbon::now()
            );
            DB::table('api_access_logs')->insert($loginLogParams);
            return true;
        }
        return false;
    }

}

