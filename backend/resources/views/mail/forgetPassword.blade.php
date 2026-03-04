@extends('layouts.masterMail')
@section('content')

    <h4>Hi {{$username}}</h4>

    <p>
        We've received a request to reset your password. If you didn't make the request,
        just ignore this email. Otherwise,
    <p> you can reset your password using the link below.<p>
    </p>
    <div id="link">
        <a
                style="color:white;
	text-decoration:none;
	padding: 10px 100px;
	background:#e67e22;/*#4479BA;*/
	color: #FFF;
	margin:20px;"

                id="link" href="{{$resetLink}}">Reset Password</a>
    </div>
@stop