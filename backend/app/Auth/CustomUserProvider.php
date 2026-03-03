<?php


namespace App\Auth;

use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class CustomUserProvider implements UserProvider
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

        $qry = User::where('id', '=', $identifier);

        if ($qry->count() > 0) {
            $user = $qry->select('*')->first();

            $attributes = array(
                'id' => $user->admin_id,
                'email' => $user->email,
                'password' => $user->password,
                'name' => $user->first_name . ' ' . $user->last_name,
            );

            return $user;
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
        $qry = User::where('id', '=', $identifier)->where('remember_token', '=', $token);

        if ($qry->count() > 0) {
            $user = $qry->select('*')->first();

            $attributes = array(
                'id' => $user->admin_id,
                'email' => $user->email,
                'password' => $user->password,
                'name' => $user->first_name . ' ' . $user->last_name,
            );

            return $user;
        }
        return null;


    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // TODO: Implement updateRememberToken() method.
        $user->setRememberToken($token);

        $user->save();

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
        $qry = User::where('email', '=', $credentials['email']);
        if ($qry->count() > 0) {
            //$user = $qry->select('id', 'username', 'first_name', 'last_name', 'email', 'password')->first();
            $user = $qry->select('*')->first();
            return $user;
        }
        return null;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // TODO: Implement validateCredentials() method.
        // we'll assume if a user was retrieved, it's good
        $email = $credentials['email'];
        $password = $credentials['password'];
        $uuid = $credentials['uuid'];
        $hashedPwd = hashPwd($email, $uuid, $password);
        if ($user->email == $credentials['email'] && $user->getAuthPassword() == $hashedPwd)
        {
            $user->last_login_time = Carbon::now();
            $user->save();
            //log
            $loginLogParams = array(
                'account_id' => $user->id,
                'email' => aes_decrypt($email),
                'ip_address' => request()->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'time' => Carbon::now()
            );
            DB::table('login_logs')->insert($loginLogParams);
            return true;
        }
        return false;
    }

}
