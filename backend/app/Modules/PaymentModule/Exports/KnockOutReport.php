<?php

namespace App\Modules\PaymentModule\Exports;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Support\Facades\DB;







class KnockOutReport  implements FromCollection,WithHeadings, ShouldAutoSize, WithEvents
{
    protected $payment_knockout_id;

    public function __construct($payment_knockout_id)
    {
        $this->payment_knockout_id = $payment_knockout_id;
    }
    public function headings(): array
    {
        return [
            array("KnockOut Report"),
            array(
            'Beneficiary Id',
            'Beneficiary Name',
            'School',
            'District Name',
            'Payment Batch No',   
            "Import Batch No.",
            "Term one Fees",
            "Term Two Fees",
            "Term Three Fees",
            "Annual Fees"
            )
        ];
    }


      /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
    return   $qry = Db::table('payment_fees_knockout as t1')
        ->join('payment_fees_knockout_logs as t2','t2.fees_knockout_request_id','t1.id')
        ->join('beneficiary_enrollments as t3','t3.id','t2.enrollment_id')
        ->join('beneficiary_information as t4','t4.id','t3.beneficiary_id')
        ->join('school_information as t5', 't4.school_id', '=', 't5.id')
        ->leftjoin('districts as t6', 't4.district_id', '=', 't6.id')
        ->join('batch_info as t7','t4.batch_id','=','t7.id')
        ->join('payment_verificationbatch as t8','t8.id','=','t3.batch_id')
        ->selectRaw("t4.beneficiary_id,CONCAT_WS(' ',decrypt(t4.first_name),
            decrypt(t4.last_name)) as student_name, t5.name as school_name,
            t6.name as district_name,t8.batch_no as payment_batch_no,
            t7.batch_no as importation_batch_no,            
            IF(decrypt(t3.term1_fees) IS NULL OR decrypt(t3.term1_fees) < 0, 0, 
            decrypt(t3.term1_fees)) AS t1_fees,
            IF(decrypt(t3.term2_fees) IS NULL OR decrypt(t3.term2_fees) < 0, 0, 
            decrypt(t3.term2_fees)) AS t2_fees,
            IF(decrypt(t3.term3_fees) IS NULL OR decrypt(t3.term3_fees) < 0, 0, 
            decrypt(t3.term3_fees)) AS t3_fees,
            CAST(
            IF(
            (IFNULL(decrypt(t3.term1_fees),0) + IFNULL(decrypt(t3.term2_fees),0) + IFNULL(decrypt(t3.term3_fees),0))=0,0,
            (IFNULL(decrypt(t3.term1_fees),0) + IFNULL(decrypt(t3.term2_fees),0) + IFNULL(decrypt(t3.term3_fees),0))
            ) AS DECIMAL(10,2)
        )as annual_fees")
        ->where('t1.id',$this->payment_knockout_id)
        ->get();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                //todo: A1:M1
                $event->sheet->mergeCells('A1:N1');
                $event->sheet->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getRowDimension('1')->setOutlineLevel(1);
                $event->sheet->getStyle('A1:N1')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A1:N1')->getFont()->applyFromArray(
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
                $event->sheet->getStyle('A2:N2')->getAlignment()->applyFromArray(
                    array(
                        'horizontal' => 'left',
                        'vertical' => 'center'
                    )
                );
                $event->sheet->getStyle('A2:N2')->getFont()->applyFromArray(
                    array(
                        'name' => 'Arial',
                        'bold' => true,
                        'size'=>9
                    )
                );
                $event->sheet->getStyle('A2:N2')->getAlignment()->setWrapText(true);
                //$event->sheet->getColumnDimension('B')->setWidth(15);//remove ShouldAutoSize to use this line
            }
        ];
    }
}