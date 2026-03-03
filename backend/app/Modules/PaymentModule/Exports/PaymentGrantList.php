<?php
 //job 21/03/2022
namespace App\Modules\PaymentModule\Exports;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
class PaymentGrantList extends DefaultValueBinder  implements WithCustomValueBinder,WithColumnFormatting,FromCollection,WithHeadings, ShouldAutoSize, WithEvents,WithStyles
{
    protected   $payment_grant_list_id;
    protected $user_id;
    protected $num_of_records;
 
    public function __construct($payment_grant_list_id,$payment_verification_status,$user_id)
    {
        $this->payment_grant_list_id =    $payment_grant_list_id;
        $this->payment_verification_status= $payment_verification_status;
        $this->user_id=$user_id;
        $this->num_of_records="";
      

    }
    public function headings(): array
    {
       
        return [
            array('Payment Grant List'),
            array(
                "Beneficiary Id",
                "Beneficiary Name",
                "Payment Grade",
                "Current Grade",
                "Current Status",
                "School Status",
                "School",
                "Home District",
                "Home Province",
                "School District",
                "CWAC",
                "House Hold NRC No",
                "House Hold Name",
                "G&C Name",
                "G&C Phone",
                "SCT MIS ID",
                "Grant Received"  
            )
         ];
    }


    public function columnFormats(): array
    {
        return [
           
            'A' => NumberFormat::FORMAT_NUMBER,
           
        ];
    }

    public function bindValue(Cell $cell, $value)
  {
        $numericalColumns = ['A']; // columns with numerical values

        if (!in_array($cell->getColumn(), $numericalColumns) || $value == '' || $value == null) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        if (in_array($cell->getColumn(), $numericalColumns)) {
            if($value!="Beneficiary Id"){
            $cell->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);

            return true;
           }
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
  }

     /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    { 
        $payment_grant_list_id=  $this->payment_grant_list_id;
        $payment_verification_status=$this->payment_verification_status;
        $grant_list_details=DB::table('payments_grant_list_batches')->where('id',  $payment_grant_list_id)->selectraw('start,stop,payment_year,total_records,payment_request_id')->get()->first();
        $start=$grant_list_details->start;
        $limit=$grant_list_details->total_records;
        $payment_request_id=$grant_list_details->payment_request_id;
        $this->num_of_records=$limit;
        
        $data= DB::table('beneficiary_information as t1')
        ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
        ->join('school_information as t3', 't3.id', '=', 't2.school_id')
        ->leftjoin('provinces as t4', 't1.province_id', '=', 't4.id')
        ->leftjoin('districts as t5', 't3.district_id', '=', 't5.id')
        ->leftjoin('cwac as t6', 't1.cwac_id', '=', 't6.id')
        ->leftjoin('households as t7','t1.household_id','t7.id')
        ->join('beneficiary_payment_records as t8', 't2.id', '=', 't8.enrollment_id')
        ->leftjoin('beneficiary_enrollement_statuses as t9','t9.id','t1.enrollment_status')
        ->leftjoin('payment_request_details as t12','t12.id','t8.payment_request_id')
        ->leftjoin('beneficiary_school_statuses  as t10',function($join){
            $join->on('t10.id','=','t2.beneficiary_schoolstatus_id');
        })
        ->leftjoin('districts as t11', 't1.district_id', '=', 't11.id')
        ->leftJoin('school_contactpersons as t17', function($join1) {
            $join1->on('t3.id', '=', 't17.school_id')
                ->where('t17.designation_id', '=', 2);
        })
        // ->leftjoin('school_contactpersons as t17', 't3.id', '=', 't17.school_id')
        // ->join('beneficiary_enrollement_statuses as t9', function ($join) {
        //    $join->on('t9.id', '=', 't2.enrollment_status_id');
        //          //->on('t9.school_id', '=', 't1.school_id');
        //  })
        //CONCAT_WS('-',t6.code,t6.name) as cwac
        ->selectraw("t1.beneficiary_id as ben_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as names,t2.school_grade,t1.current_school_grade,
        t9.name as status,t10.name as school_status,t3.name,CONCAT_WS('-',t11.code,t11.name) as home_district,t4.name as province,
        CONCAT_WS('-',t5.code,t5.name) as district,t1.cwac_txt as cwac,
        t7.hhh_nrc_number,CONCAT_WS(' ',t7.hhh_fname,t7.hhh_lname) as hhh_name,t17.full_names AS gc_name,
     t17.telephone_no AS gc_phone
        ")
        // MAX(CASE WHEN t17.designation_id = 2 THEN t17.full_names END) AS gc_name,
        // MAX(CASE WHEN t17.designation_id = 2 THEN t17.telephone_no END) AS gc_phone

        ->orderBy('t1.beneficiary_id','ASC')
        ->orderBy('t4.name','ASC')
        ->orderBy('t5.name','ASC')
        ->orderBy('t6.name','ASC')
        ->where('t12.payment_year', $grant_list_details->payment_year)
        ->where('t12.status_id',$payment_verification_status);
        //->where('t12.status_id','>',3)
        //->whereraw('IF(`t12`.`status_id`>4,`t12`.`approval_status`=2,`t12`.`status_id`=4)')  
        //->whereIn('t12.status_id',array(4,5))
        //->whereraw('IF(`t12`.`status_id`=5,`t12`.`approval_status`=2,`t12`.`status_id`=4)')
        if(isset($payment_request_id))
        {
            $data->where('t12.id',$payment_request_id);
        }
        $data=$data->skip($start)->take($limit)->get()->toArray();
       
        
        $data_to_save=array();
        $clean_data=array();

        $array_one=array();
        $array_two=array();
        foreach($data as $key=>$item)
        {
            // if(in_array($item->ben_id,$array_one))
            // {
            //     $array_two[]=$item->ben_id;
            // }else{
                
            // $array_one[]=$item->ben_id;
            // }
            
            $is_available=Db::table('payment_grant_list_log')->where(['ben_id'=>$item->ben_id,"payment_year"=>$grant_list_details->payment_year,
            "stage"=>$payment_verification_status,"grantlist_id"=>$payment_grant_list_id])->count();
            if($is_available==0)
            {
                $data_to_save[]=array(
                    "ben_id"=>$item->ben_id,
                    "payment_year"=>$grant_list_details->payment_year,
                    "stage"=>$payment_verification_status,
                    "grantlist_id"=> $payment_grant_list_id,
                  
                    "created_by"=>$this->user_id,
                    "created_at"=>Carbon::now(),
                );

            }
            foreach ((array)$item as $key2 => $val2) {
                 $item->$key2=utf8_encode($val2);
            }

          $clean_data[]=$item;
        }
       
        $number_of_records=count($data_to_save);
        $limit=1500;
        if($number_of_records>$limit && $number_of_records>0)
        {
            $total_loop=ceil($number_of_records/$limit);
            $start_index=0;
            $end_index=$limit;
            for($i=1;$i<=$total_loop;$i++)
            {
                $results_to_insert=array();
                foreach($data_to_save as $key=>$result)
                {
                    if($key>=$start_index && $key<=$end_index)
                    {
                        $results_to_insert[]=$result;
                    }
                }
                
                DB::table("payment_grant_list_log")->insert($results_to_insert);
                $results_to_insert=array();
                if($i!=$total_loop-1){
                    $start_index=$end_index+1;
                    $end_index=$start_index+$limit;
                    }else{
                        $start_index=$end_index+1;
                        $end_index=($number_of_records-1);
                }

            }
        }else{
           DB::table("payment_grant_list_log")->insert($data_to_save);
        }
       
        DB::table('payments_grant_list_batches')->where('id',  $payment_grant_list_id)->update(['status_id'=>1]);
         return collect($clean_data); 
    }


  
    public function styles(Worksheet $sheet): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
        //$sheet->loadView('template');
        $sheet->getProtection()->setPassword('Passcode');
        $sheet->getProtection()->setSheet(true);
        $last_row="O".($this->num_of_records+2);
        $last_row2="N".($this->num_of_records+2);
        $sheet->getStyle("O3:$last_row")->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle("N3:$last_row2")->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
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
                $event->sheet->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getRowDimension('1')->setOutlineLevel(1);
                $event->sheet->getStyle('A1:N1')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A1:O1')->getFont()->applyFromArray(
                    array(
                        'name' => 'Times New Roman',
                        'bold' => true,
                        'italic' => false,
                        'underline' => Font::UNDERLINE_SINGLE,
                        'strikethrough' => false,
                        'color' => ['rgb' => '#f2f2f2']
                    )
                );
                //todo: A2:M2
                $event->sheet->getStyle('A2:O2')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'left',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A2:O2')->getFont()->applyFromArray(
                    array(
                        'name' => 'Arial',
                        'bold' => true,
                        'size'=>9
                    )
                );
                $event->sheet->getStyle('A2:O2')->getAlignment()->setWrapText(true);
                //$event->sheet->getColumnDimension('B')->setWidth(15);//remove ShouldAutoSize to use this line
            }
        ];
    }
}