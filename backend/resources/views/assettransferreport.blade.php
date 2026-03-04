<html>
    <head>
    
  
      </head>
    <body>
    
  
    <img src={{$image_url}} width="50" alt="Logo" style="display: block;margin-left: 45%;width: 10%;" />
    <h3 style="text-align: center;margin-top:-7px;">REPUBLIC OF ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-7px;">MINISTRY OF EDUCATION</h3>
    <h3  style="text-align: center;margin-top:-7px;">P.O BOX 50093</h3>
    <h3  style="text-align: center;margin-top:-7px;">LUSAKA, ZAMBIA</h3>
    <h3 style="text-align: center;margin-top:-15px;text-transform: uppercase;">Asset Transfer Report </h3>

    @foreach($asset_inventory as $key=>$assets_of_user)

    @if($assets_of_user['transfer_category']==1)

    @if($assets_of_user['user_transfered_from_name'])
    <p><b>Initially Issued to: </b>{{$assets_of_user['user_transfered_from_name']}}</p>
    @else
    <p><b>Initially Issued to: </b>{{$assets_of_user['previous_site_name']}}</p>
    @endif

    <p><b>Transferred to:</b> {{$assets_of_user['user_transfered_to_name']}}</p>
   
    @else

    @if($assets_of_user['user_transfered_from_name'])
    <p><b>Initially Issued to: </b>{{$assets_of_user['user_transfered_from_name']}}</p>
    @else
    <p><b>Initially Issued to: </b>{{$assets_of_user['previous_site_name']}}</p>
    @endif
    <p><b>Transferred to:</b> {{$assets_of_user['site_transfered_to_name']}}</p>
    @endif

    <p><b>Transfer Date: </b>{{$assets_of_user['transfer_date']}} <p>
    <h3 style="text-align: left;margin-top:-15px;">Reason for Transfer</h3>
    <p>{{$assets_of_user['transfer_reason']}}</hp>
    @endforeach
    <footer style="position: fixed; bottom: 20px; left: 0%;height: 20px;">
            Report generated on: {{$report_date}} 
            </footer>
    </body>
</html>


</table>