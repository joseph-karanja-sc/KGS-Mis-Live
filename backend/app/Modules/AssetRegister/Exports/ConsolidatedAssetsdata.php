<?php
 //job 27/07/2022
namespace App\Modules\AssetRegister\Exports;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Support\Facades\DB;
//use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
class ConsolidatedAssetsData  implements FromCollection,WithHeadings, ShouldAutoSize, WithEvents
{
    public function headings(): array
    {
        
        return [
            array('Ministry of Education - Keeping Girls In School Project'),
            array('ASSET REGISTER AS AT '.date('Y-m-d')),
            array(
                'Item Description',
                'Cost',
                'Date Purchased',
                'Supplier',
                'Manufacturer S/N',
                'Grz Serial No',
                'Status',
                'Location',
                'Depreciation Method',
                'Depreciation Rate',
                'Expected Lifespan In Years',
                "Current Depreciation",
                "Cumulative Depreciation",
                'Net Book Value'

                )
         ];
    }
     /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    { 
       $qry=Db::table('ar_asset_inventory as t1')
       ->join('ar_asset_suppliers as t2','t1.supplier_id','t2.id')
       ->leftjoin('ar_asset_depreciation as t4','t4.asset_id','t1.id')
       ->leftjoin('ar_depreciation_methods as t6','t6.id','depreciation_method_id')
    //    ->leftjoin('ar_asset_checkout_details as t3','t3.asset_id','t1.id')
    //    ->leftjoin('users as t4','t4.id','t3.id')
      ->join('ar_asset_statuses as t3','t3.id','t1.status_id')
     ->leftjoin('ar_asset_sites as t5','t5.id','t1.site_id')
       ->selectraw("t1.id,t1.description,cost,purchase_date,t2.name,serial_no,grz_no,t5.name as site_name,
       t3.name as status,t6.name as depre_method,t4.depreciation_rate as depreciation_rate,asset_life,t4.depreciation_method_id as depreciation_method_id,depreciable_cost,salvage_value");
       $results=$qry->get();
       $qry_users = DB::table('users  as t3')
       ->selectRaw("id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as assigned_user");
       $users = convertStdClassObjToArray( $qry_users->get());
       
       foreach($results as $result)
       {

        $asset_depreciation_method=$result->depreciation_method_id;
        $depreciation_details=(object)array(
            "asset_life"=>$result->asset_life,
            "depreciable_cost"=>$result->depreciable_cost,
            "salvage_value"=>$result->salvage_value,
            "date_acquired"=>$result->purchase_date,
            "depreciation_rate"=>$result->depreciation_rate

        );
        if($result->asset_life && $result->depreciable_cost && intval($result->asset_life)>0 && intval($result->depreciable_cost)>0 ){
        $end_year=date('Y')."-12-31";
        $asset_depreciation_method=4;
        switch($asset_depreciation_method){
            // case 5:
                
            //     $table_data['asset_end_depreciation_date']=calculateSumofYearofDigits($depreciation_details,true);
            //     break;
            case 4:
               
                $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5);
               
                $current_depreciation=calculatePercentageDepreciation($depreciation_details,$end_year,1.5);
                $cumulative_depreciation=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),1.5,false,false,true);
                $result->current_value= $current_val;
                $result->current_depreciation=$current_depreciation;
                $result->cumulative_depreciation=$cumulative_depreciation;
              
                break;
            case 3:
                $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2,false,false,true);
                $current_depreciation=calculatePercentageDepreciation($depreciation_details,$end_year,2.0);
                $cumulative_depreciation=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),2,false,false,true);
                $result->current_value= $current_val;
                $result->cumulative_depreciation=$cumulative_depreciation;
                break;
            case 2:
                $rate=$result->depreciation_rate/100;
                $current_val=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$rate,false,false,true);
                $current_depreciation=calculatePercentageDepreciation($depreciation_details,$end_year,$rate);
                $cumulative_depreciation=calculatePercentageDepreciation($depreciation_details,date('Y-m-d'),$rate,false,false,true);
                $result->current_value= $current_val;
                $result->current_depreciation=$current_depreciation;
                $result->cumulative_depreciation=$cumulative_depreciation;
                break;
            case 1:
                $current_val=calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'));
                $current_depreciation=calculateStraightLineDepreciation($depreciation_details,$end_year);
                $cumulative_depreciation=calculateStraightLineDepreciation($depreciation_details,date('Y-m-d'),false,true);
                $result->current_value= $current_val;
                $result->current_depreciation=$current_depreciation;
                $result->cumulative_depreciation=$cumulative_depreciation;
                break;
        }
    }


        
        //CONCAT_WS(' ',decrypt(t4.first_name),decrypt(t4.middlename),decrypt(t4.last_name)) as assigned_user
         $checkout_details = db::table('ar_asset_checkout_details as t3')
         ->leftjoin('users as t4','t4.id','t3.user_id')
         ->join('ar_asset_sites as t5','t5.id','t3.checkout_site_id')
         ->selectraw('t5.name as site,t3.user_id')
         ->orderBy('t3.created_at','DESC')
         ->where('asset_id',$result->id)->first();
        // dump($checkout_details);
         //$checkout_details= convertStdClassObjToArray($checkout_details);
         //dd($checkout_details);
       // dd($checkout_details->user_id);
      // dd($checkout_details);
                    if(is_object($checkout_details)){
                    if( $checkout_details->user_id!=null){
            
                    $users_id_array=$this->returnArrayFromStringArray($checkout_details->user_id);
                    $user_details=[];
                       foreach ($users_id_array as $key_count=>$user_id_this)
                        { 
                            $result_asset= $this->_search_array_by_value($users,'id',$user_id_this)[0];
                            $user_details[]=$result_asset['assigned_user'];
                        }
                        $result->location=implode(",",$user_details);
                        $user_details=[];
                    }else{
                        $result->location=$checkout_details->site;
                    }
                    }else{
                        $result->location=$result->site_name;
                    }
        

       }
       $final_data=array();
       foreach($results as $result)
       {
         $final_data[]=array(
            "description"=>$result->description,
            "cost"=>$result->cost,
            "purchase_date"=>$result->purchase_date,
            "supplier"=>$result->name,
            "serial_no"=>$result->serial_no,
            "grz_no"=>$result->grz_no,
            "status"=>$result->status,
            "location"=>$result->location,
            "depreciation_method"=>$result->depre_method,
            "depre_rate"=>$result->depreciation_rate,
            "asset_life"=>$result->asset_life/12,
            "current_depre"=>property_exists($result,'current_depreciation')?$result->current_depreciation:"",
            "cumulative_depre"=>property_exists($result,'cumulative_depreciation')?$result->cumulative_depreciation:"",
            "net_book_value"=>property_exists($result,'current_value')?$result->current_value:""

         );
       }
    
       return collect($final_data);
     
    }
    private function _search_array_by_value($array,$key,$value) {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }
    
            foreach ($array as $subarray) {
                $results = array_merge($results,$this->_search_array_by_value($subarray, $key, $value));
            }
        }
    
        return $results;
    }
    private function returnArrayFromStringArray($string_array)
    {
       $backup_string= $string_array;
        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        if($final_array[0]=="")
        {
            $final_array=[$backup_string];
        }
        return $final_array;
    }
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));

        return [
            "A:$lastColumn" => ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_NUMBER]],
        ];
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                //todo: A1:M1
                $event->sheet->mergeCells('A1:O1');
                $event->sheet->mergeCells('A2:O2');
                $event->sheet->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getRowDimension('1')->setOutlineLevel(1);
                $event->sheet->getRowDimension('2')->setRowHeight(30);
                $event->sheet->getRowDimension('2')->setOutlineLevel(1);
                $event->sheet->getStyle('A1:N1')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A2:O2')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A1:B1')->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                $event->sheet->getStyle('A2:B2')->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                $event->sheet->getStyle('A3:N3')->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                // $event->sheet->getStyle('A1:B1')->getFont()->applyFromArray(
                //     array(
                //         'name' => 'Times New Roman',
                //         'bold' => true,
                //         'italic' => false,
                //         'underline' => Font::UNDERLINE_SINGLE,
                //         'strikethrough' => false,
                //         'color' => ['rgb' => '#f2f2f2']
                //     )
                // );
                // //todo: A2:M2
                // $event->sheet->getStyle('A2:B2')->getAlignment()->applyFromArray(
                //     array(
                //         'horizontal' => 'left',
                //         'vertical' => 'center'
                //     )
                // );
                // $event->sheet->getStyle('A2:B2')->getFont()->applyFromArray(
                //     array(
                //         'name' => 'Arial',
                //         'bold' => true,
                //         'size'=>9
                //     )
                // );
                $event->sheet->getStyle('A2:B2')->getAlignment()->setWrapText(true);
                //$event->sheet->getColumnDimension('B')->setWidth(15);//remove ShouldAutoSize to use this line
            }
        ];
    }
}