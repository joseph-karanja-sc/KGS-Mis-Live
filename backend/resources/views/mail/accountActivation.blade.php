@extends('layouts.masterMail')
@section('content')
    <h4>Hi {{$username}} !!</h4>
    <h4>Thank You for Registering. </h4>
    <p>Your account initial password is: <b>{{$password}}</b></p>
    <p>Please consider changing your password once logged in.</p>
    <p>You can click <a id="link" href="{{$link}}">here</a> to login</p>
@stop