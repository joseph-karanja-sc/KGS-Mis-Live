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

class PaymentGrantList  implements FromCollection,WithHeadings, ShouldAutoSize, WithEvents,WithStyles
{
    public function headings(): array
    {
        // return[
        //     array('CWACS'),
        //     array(
        //         'name',
        //         'code',
        //         "province",
        //         "District"
        //     )
        //     ];
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
            "Province",
            "School District",
            "CWAC",
            "House Hold NRC No",
            "House Hold Name",
            "SCT MIS ID",
            "Grant Received"  
            )


         ];
    }
     /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    { 
       // return db::select('SELECT t1.name,t1.CODE,t2.name AS province,t3.name AS district FROM cwac AS t1  INNER JOIN provinces AS t2 ON t1.province_id=t2.id INNER JOIN districts AS t3 ON t1.district_id=t3.id');

    //     return db::table('cwac as t1')->selectraw('t1.name,t1.code,t6.name as province,t3.name as district')
    //     ->leftJoin('districts as t3', 't1.district_id', '=', 't3.id')
    //     ->leftJoin('provinces as t6', 't1.province_id', '=', 't6.id')
    //  ->get();
        //getSchoolpaymentschoolSummary
      
        return DB::table('beneficiary_information as t1')
        ->join('beneficiary_enrollments as t2', 't1.id', '=', 't2.beneficiary_id')
        ->join('school_information as t3', 't3.id', '=', 't2.school_id')
        ->leftjoin('provinces as t4', 't3.province_id', '=', 't4.id')
        ->leftjoin('districts as t5', 't3.district_id', '=', 't5.id')
        ->leftjoin('cwac as t6', 't3.cwac_id', '=', 't6.id')
        ->leftjoin('households as t7','t1.household_id','t7.id')
        ->join('beneficiary_payment_records as t8', 't2.id', '=', 't8.enrollment_id')
        ->join('beneficiary_enrollement_statuses as t9','t9.id','t1.enrollment_status')
        ->join('beneficiary_school_statuses  as t10',function($join){
            $join->on('t10.id','=','t2.beneficiary_schoolstatus_id');
        })
        ->leftjoin('districts as t11', 't1.district_id', '=', 't11.id')
        // ->join('beneficiary_enrollement_statuses as t9', function ($join) {
        //    $join->on('t9.id', '=', 't2.enrollment_status_id');
        //          //->on('t9.school_id', '=', 't1.school_id');
        //  })
        ->selectraw("t1.beneficiary_id,CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as names,t2.school_grade,t1.current_school_grade,t9.name as status,t10.name as school_status,t3.name,CONCAT_WS('-',t11.code,t11.name) as home_district,t4.name as province,CONCAT_WS('-',t5.code,t5.name) as district,CONCAT_WS('-',t6.code,t6.name) as cwac,
        t7.hhh_nrc_number,CONCAT_WS(' ',t7.hhh_fname,t7.hhh_lname)")
        ->orderBy('t4.name','ASC')
        ->orderBy('t5.name','ASC')
        ->orderBy('t6.name','ASC')
        ->where('year_of_enrollment',date('Y'))->get();    
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