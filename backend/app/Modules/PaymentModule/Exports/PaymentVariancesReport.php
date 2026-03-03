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

class PaymentVariancesReport  implements FromCollection,WithHeadings, ShouldAutoSize, WithEvents
{
    protected $payment_request_id;
    protected $term_id;

    public function __construct($payment_request_id,$term_id)
    {
        $this->payment_request_id = $payment_request_id;
        $this->term_id=$term_id;
    }
    public function headings(): array
    {
        return [
            array("Payment Variances  Report Term ". $this->term_id),
            array(
            'Beneficiary Id',
            'Beneficiary Name',
            "Disbursed Fees",
            "Receipted Fees",
            "Varied Amount"
            )
        ];
    }


      /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $fee_disbursed_term="";
        $term= $this->term_id;
        $payment_request_id= $this->payment_request_id;
        if($term=="1")
        {
            $fee_disbursed_term = "decrypt(t2.term1_fees)";
        }
        if($term=="2")
        {
            $fee_disbursed_term = "decrypt(t2.term2_fees)";
        }
        if($term=="3")
        {
            $fee_disbursed_term = "decrypt(t2.term3_fees)";
        }
     
        // $qry="SELECT t1.id,t1.beneficiary_id as  beneficiary_no,
        // CONCAT_WS(' ',decrypt(t1.first_name),decrypt(t1.last_name)) as beneficiary_name,
        //  $fee_disbursed_term AS disbursed_fees, 
        // (SELECT SUM(tt1.receipt_amount) FROM beneficiary_receipting_details AS tt1
        //  INNER JOIN payments_receipting_details AS tt2 ON tt1.payment_receipt_id=tt2.id
        // INNER JOIN beneficiary_enrollments AS tt3 ON tt3.id=tt2.enrollment_id 
        // INNER JOIN beneficiary_information  AS tt4 ON tt4.id=tt3.beneficiary_id 
        // WHERE tt4.id=t1.id AND tt2.term_id=$term
        // GROUP BY payment_receipt_id 
        // HAVING  term1_fees!= sum(tt1.receipt_amount) ) AS receipted_fees
        // FROM beneficiary_information AS t1 INNER JOIN beneficiary_enrollments AS t2 ON t1.id=t2.beneficiary_id
        // INNER JOIN beneficiary_payment_records AS t3 ON t3.enrollment_id=t2.id 
        // WHERE t3.payment_request_id=$payment_request_id  
        // GROUP BY t1.id HAVING receipted_fees>0";


        $qry="SELECT (t1.beneficiary_id) AS beneficiary_no,
        CONCAT_WS(' ',decrypt(first_name),decrypt(last_name)) as beneficiary_name,
        $fee_disbursed_term AS disbursed_fees,
        SUM(t5.receipt_amount) AS receipted_fees,
        (decrypt(t2.term1_fees)-SUM(t5.receipt_amount)) AS varied_amount
        FROM beneficiary_information AS t1 INNER JOIN  beneficiary_enrollments AS t2 ON t1.id=t2.beneficiary_id 
        INNER JOIN beneficiary_payment_records AS t3 ON t3.enrollment_id=t2.id 
        INNER JOIN  payments_receipting_details AS t4 ON t2.id=t4.enrollment_id 
        INNER JOIN beneficiary_receipting_details AS t5 ON t5.payment_receipt_id=t4.id
        WHERE payment_request_id=$payment_request_id AND t4.term_id=$term
        GROUP BY t1.beneficiary_id  
        HAVING   disbursed_fees!= SUM(t5.receipt_amount)";
        $results=db::select($qry);
        $results=collect($results);//convert array to collection
        return $results;
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