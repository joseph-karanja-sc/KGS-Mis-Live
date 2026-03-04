
<html>
    <head>
    
  
      </head>
    <body>
    
    <img src={{$image_url}} width="50" alt="Logo" style="display: block;margin-left: 45%;width: 10%;" />
    <h3 style="text-align: center;margin-top:-7px;">REPUBLIC OF ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-7px;">MINISTRY OF EDUCATION</h3>
    <h3  style="text-align: center;margin-top:-7px;">P.O BOX 50093</h3>
    <h3  style="text-align: center;margin-top:-7px;">LUSAKA, ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-15px;text-transform: uppercase;"> {{$report_name}} </h3>

    <table  style="width: 100%;border: 1px solid black; border-collapse: collapse;padding: 15px;text-align: center;
    border-bottom: 1px solid #ddd;tr:nth-child(even) {background-color: #f2f2f2;};" >


    <tr >
      @foreach($headers as $main_key=>$header)
     <th style='{{$header['style']}}'>{{$header['header_name']}}</th>
  
      @endforeach
       
    </tr>


    @foreach ($asset_inventory as $key=>$asset)

    @if(($key % 2)!=0)
    <tr style="background-color:#f2f2f2">
    @foreach($asset as $key=>$data)
    
    <td style='{{$headers[$key]['data_style']}}'>{{$data}}</td>

    @endforeach
    </tr>
   
    @else
    <tr>
    @foreach($asset as $key=>$data)
    <td style='{{$headers[$key]['data_style']}}'>{{$data}}</td>
    
    @endforeach
   
    @endif

    @endforeach
  
    <footer style="position: fixed; bottom: 50px; left: 32%;height: 50px;">
          Report generated on: {{$report_date}} 
        </footer>
    </body>
</html>


</table>