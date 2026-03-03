<?php
//Job 24/02/2022
namespace App\Http\Middleware;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;

class SessionTimeOut
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!session()->has('lastActivityTime')) {
            session(['lastActivityTime' => now()]);
        }
        //config('session.lifetime')//gives session time in conig
        //if (now()->diffInSeconds(session('lastActivityTime')) >= (500) ) {//number is in 
        if (now()->diffInSeconds(session('lastActivityTime')) >= (432000) ) {//number is in seconds
            if (auth()->check() && auth()->id() > 1) {
                $user = auth()->user();
                DB::table('users')->where('id', auth()->id())->update(['last_logout_time'=>Carbon::now(),
                "logout_type"=>"System Timeout"]);
                auth()->logout();
                session()->forget('lastActivityTime');
            }
        }
        session(['lastActivityTime' => now()]);
        return $next($request);
    }
}