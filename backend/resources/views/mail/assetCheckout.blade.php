<html>
    <head>
    
    <style>
        td{
              
    border-right: solid 1px white ;
    border-bottom: 1px solid white;
    text-align: center;
        }
        </style>
      </head>
    <body>
    
    <img src={{$image_url}} width="50" alt="Logo" style="display: block;margin-left: 45%;width: 10%;" />
    <h3 style="text-align: center;margin-top:-7px;">Ministry of  Education</h3>
    <h3 style="text-align: center;margin-top:-15px;">
    @if($isReservation==0)
    <h3>Asset Check-Out </h3>
    @else
    <h3>Asset Reservation</h3>
    @endif
    

    <p style="font-weight:200;">Dear {{$data['user_last_name']}},<p>
  
    @if($isIndividualbulk==0)
    @if($data['no_due_date']==0)
    @if($isReservation==0)
    @if($user_serial_no==1)
    <p style="font-weight:200;">You have been assigned <b>{{$data['description']}}</b> of {{$data['identifier ']}} <b>
        {{$data['serial_no']}}</b>.
    @else
    <p style="font-weight:200;">You have been assigned <b>{{$data['description']}}</b> of {{$data['identifier ']}} <b>
        {{$data['grz_no']}}</b>.
    @endif
    The asset assignment is from  <b>{{$data['checkout_date']}}</b> and you are expected to return the asset on 
    <b>{{$data['due_date']}}</b>.
    <p>
    @else
    
    @if($user_serial_no==1)
    <p style="font-weight:200;">You have reserved <b>{{$data['description']}}</b> of  {{$data['identifier ']}}<b>
        {{$data['serial_no']}}</b>.
        @else
        <p style="font-weight:200;">You have reserved <b>{{$data['description']}}</b> of  {{$data['identifier ']}} <b>
        {{$data['grz_no']}}</b>.
    @endif
    
    The asset reservation is from  <b>{{$data['checkout_date']}}</b> to 
    <b>{{$data['due_date']}}</b>.
    <p>

    @endif

    @else
    <p style="font-weight:200;">You have been assigned <b>{{$data['description']}}</b>{{$data['identifier ']}} <b>
        {{$data['serial_no']}}</b>.
    The asset assignment commences on  <b>{{$data['checkout_date']}}</b> and has no due date.
   <p>
    @endif
@else

<!-- bulk checkout -->
@if($data['no_due_date']==0)
    <p style="font-weight:200;">You have been assigned the following assets with effect from <b>{{$data['checkout_date']}}</b>
    to  <b>{{$data['due_date']}}</b>.
@else
<p style="font-weight:200;">You have been assigned the assets below with effect from  <b>{{$data['checkout_date']}}</b>
      and there is no due date.
   <p>
@endif

<table style="border-top-style: ridge;border-bottom-style: ridge;
        border-left-style: ridge;
        border-right-style: ridge;
        border-color: black;
        border-width: 3px;">
    <tr>
        <th  style="border-bottom-style: ridge;  border-color: black;" >Asset Description</th>
        <th  style="border-bottom-style: ridge;  border-color: black;">Identifier</th>
        <th  style="border-bottom-style: ridge;  border-color: black;">Identifier Type</th>
</tr>
@foreach($assets as $key=>$asset)
@if(($key % 2)!=0)
<tr style="background-color:#f2f2f2">
     <td  >{{$asset['description']}}</td> 
    
    <td >  @if ( isset($asset['serial_no']) ) {{$asset['serial_no']}} @else  {{$asset['grz_no']}} @endif</td>
    <td > {{ $asset['identifier']}}</td>
</tr>
@else
<tr >
     <td >{{$asset['description']}}</td> 
    
     <td >  @if ( isset($asset['serial_no']) ) {{$asset['serial_no']}} @else  {{$asset['grz_no']}} @endif</td>
     <td > {{ $asset['identifier']}}</td>
</tr>
@endif
@endforeach
</table>


@endif
<!-- bulk checkout -->



</body>
</html>