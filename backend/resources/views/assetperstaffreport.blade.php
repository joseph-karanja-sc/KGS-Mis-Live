<html>
    <head>
    
  
      </head>
    <body>
    
  
    <img src={{$image_url}} width="50" alt="Logo" style="display: block;margin-left: 45%;width: 10%;" />
    <h3 style="text-align: center;margin-top:-7px;">REPUBLIC OF ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-7px;">MINISTRY OF EDUCATION</h3>
    <h3  style="text-align: center;margin-top:-7px;">P.O BOX 50093</h3>
    <h3  style="text-align: center;margin-top:-7px;">LUSAKA, ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-15px;text-transform: uppercase;">Asset distribution per staff  Report </h3>

    @foreach($asset_inventory as $key=>$assets_of_user)
    
    <?php $identity=explode(',', $key) ?>
    <h4><u>{{$identity[1]}}</u></h4>
    <table  style="width: 100%;border: 1px solid black; border-collapse: collapse;padding: 15px;text-align: center;
    border-bottom: 1px solid #ddd;tr:nth-child(even) {background-color: #f2f2f2;};" >
    <tr>
        <th  style='{{$header_style}}'>Asset Assigned</th>
        <th  style='{{$header_style}}'>Date Assigned</th>
        <th  style='{{$header_style}}'>Serial No</th>
        <th  style='{{$header_style}}'>Grz No</th>
        
    </tr>
    @foreach($assets_of_user as $key2=>$user_assets)
    @if(($key2 % 2)!=0)
    <tr style="background-color:#f2f2f2">
        <td style='{{$data_style}}'>{{ $user_assets['asset_description']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['date_assigned']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['serial_no']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['grz_no']}}</td>
    </tr>
    @else
    <tr>
        <td style='{{$data_style}}'>{{$user_assets['asset_description']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['date_assigned']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['serial_no']}}</td>
        <td style='{{$data_style}}'>{{$user_assets['grz_no']}}</td>
    </tr>
    @endif
    @endforeach
 

    </table>


    @endforeach


    


<footer style="position: fixed; bottom: 20px; left: 25%;height: 20px;">
          Report generated on: {{$report_date}} 
        </footer>
    </body>
</html>


</table>