<html>
    <head></head>
    <body>
    <img src={{$image_url}} width="50" alt="Logo" style="display: block;margin-left: 45%;width: 10%;" />
    <h3 style="text-align: center;margin-top:-7px;">Ministry of  Education</h3>
    <h3 style="text-align: center;margin-top:-15px;">{{$subject}}</h3>
    <p>{{$sal}}</p>
    <br/>
        <p>{{$msg}}</p>

        <table style="border-top-style: ridge;border-bottom-style: ridge;
        border-left-style: ridge;
        border-right-style: ridge;
        border-color: black;
        border-width: 3px;">
    <tr>
        <th  style="border-bottom-style: ridge;  border-color: black;" >Asset Description</th>
        <th  style="border-bottom-style: ridge;  border-color: black;">Serial/Grz Number</th>
</tr>
@foreach($assets as $key=>$asset)
@if(($key % 2)!=0)
<tr style="background-color:#f2f2f2">
     <td  >{{$asset['description']}}</td> 
    
     @if($use_serial_no==1)
    <td >{{$asset['serial_no']}}</td>
    @else
    <td >{{$asset['grz_no']}}</td>
    @endif
</tr>
@else
<tr >
     <td >{{$asset['description']}}</td> 
    @if($use_serial_no==1)
    <td >{{$asset['serial_no']}}</td>
    @else
    <td >{{$asset['grz_no']}}</td>
    @endif
</tr>
@endif
@endforeach
</table>
    </body>
</html>