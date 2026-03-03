<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class BaseController extends Controller
{

    protected $user_id;
    protected $user_email;
    protected $dms_id;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $this->user_id = Auth::user()->id;
                $this->user_email = Auth::user()->email;
                $this->dms_id = Auth::user()->dms_id;
            } else {
                $res = array(
                    'success' => false,
                    'message' => '<p>NO SESSION, SERVICE NOT ALLOWED!!<br>PLEASE RELOAD THE SYSTEM!!</p>'
                );
                //dd($res);
               // return response()->json($res);
            }
            return $next($request);
        });
    }

}
