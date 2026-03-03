<?php

namespace App\Modules\PaymentModule\Exports;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class KnockedOutGirls  implements FromCollection,WithHeadings
{
    public function headings(): array
    {
        return [
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
        ];
    }
     /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return   $qry= DB::table('beneficiary_enrollments as t1')
        ->join('payment_verificationbatch as t2','t2.id','=','t1.batch_id')
        ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
        ->leftjoin('districts as t4', 't2.district_id', '=', 't4.id')
        ->join('beneficiary_payment_records as t5','t5.enrollment_id','=','t1.id')
        ->join('school_information as t6', 't3.school_id', '=', 't6.id')
        ->join('batch_info as t7','t3.batch_id','=','t7.id')
        // ->selectRaw('t1.beneficiary_id,t1.batch_id,t2.created_at,decrypt(t1.term1_fees) as t1_fees,
        // decrypt(t1.term2_fees) as t2_fees,t4.name as district_name,batch_no,submission_status')
        ->selectRaw("t3.beneficiary_id,CONCAT_WS(' ',decrypt(t3.first_name),decrypt(t3.last_name)) as student_name, t6.name as school_name,t4.name as district_name,t2.batch_no as payment_batch_no,
       t7.batch_no as importation_batch_no,decrypt(t1.term1_fees) as t1_fees,
       decrypt(t1.term2_fees) as t2_fees, decrypt(t1.term3_fees) as t3_fees,decrypt(t1.annual_fees) as annual_fees")
         ->where('t5.payment_request_id',26)->get();
       // ->whereIn('t3.batch_id',[19,20,24,25,26,27])
      // ->whereIn('t1.submission_status',[2])
      // ->where('status_id',3)->get();//payment validation
     
    }
}