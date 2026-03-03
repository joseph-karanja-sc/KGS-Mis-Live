<?php
/* created by Job on 08/05/2022 */
namespace App\Console\Commands;

use App\Mail\ComplaintsLagNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class FeesKnockOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:feesknockout {payment_request_id} {payment_year} {description} {batch_id} {school_status} {term} {gce_external} {user_id} {fees_knockout_id} {knockout_exam_fees}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'KnockOut Fees';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function returnArrayFromStringArray($string_array)
    {

        $string_array=substr(trim($string_array), 0, -1);
        $final_array=explode(',' ,substr($string_array,1));
        return $final_array;
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       
        try{
       $payment_request_id=$this->argument('payment_request_id');
       $payment_year = $this->argument('payment_year');
       $description =$this->argument('description');
       $batch_id =$this->argument('batch_id');
       $school_status=$this->argument("school_status");
       $term = $this->argument("term");
       $gce_external =$this->argument("gce_external");
       $user_id=$this->argument("user_id");
       $fees_knockout_id = $this->argument('fees_knockout_id');
       $batches=Db::table('batch_info')->whereIn('id',$batch_id)->selectraw('batch_no')->get()->toArray();
       $batches = convertStdClassObjToArray($batches);
       $knockout_exam_fees=$this->argument('knockout_exam_fees');
       
        $new_batches=[];
        foreach($batches as $the_batch)
        {
            $new_batches[]=$the_batch['batch_no'];
        }
       $new_batches=implode(",",$new_batches);
       $qry= DB::table('beneficiary_enrollments as t1')
       ->join('beneficiary_payment_records as t2','t2.enrollment_id','=','t1.id')
       ->join('beneficiary_information as t3','t1.beneficiary_id','=','t3.id')
        ->where('t2.payment_request_id',$payment_request_id)
       ->selectraw('t1.id,decrypt(t1.term1_fees) as t1_fees,decrypt(t1.term2_fees) as t2_fees,decrypt(t1.term3_fees) as  t3_fees,
       decrypt(t1.annual_fees) as annual_fees,decrypt(t1.exam_fees) as exam_fees,t3.batch_id as batch_identity,t1.batch_id')
       ->join('payment_verificationbatch as t4','t4.id','=','t1.batch_id')
       ->where('t2.payment_request_id',$payment_request_id)
        ->whereIn('t3.batch_id',$batch_id);


     if(is_array($school_status) && count($school_status)>0)
        {
            //$school_status=$this->returnArrayFromStringArray($school_status);
            $qry->whereIn('beneficiary_schoolstatus_id',$school_status);
        }
		
        if($gce_external==true)
        {
            $qry->where('is_gce_external_candidate',1);
           
        }  
         $results = $qry->get()->toArray();
         $was_knock_out_done=0;
         foreach($results as $result)
         {
             foreach($term as $term_id){
                $current_results=Db::table('beneficiary_enrollments')->where('id',$result->id)
                ->selectraw('decrypt(term1_fees) as t1_fees,decrypt(term2_fees) as t2_fees,decrypt(term3_fees) as t3_fees,decrypt(annual_fees) as annual_fees')->get();
                $current_results=$current_results[0];
             switch($term_id)
           {
             case 1:
                 if($current_results->t1_fees>0){  
                 $annual_fees=$current_results->annual_fees-$current_results->t1_fees;
                 $table_data=array(
                        "enrollment_id"=>$result->id,
                        "fees_knockout_request_id"=>$fees_knockout_id,
                        "term"=>1,
                        "term_fees"=>$current_results->t1_fees,
                        "created_at"=>Carbon::now(),
                        "created_by"=>$user_id
                 );
                 insertRecord('payment_fees_knockout_logs', $table_data, $user_id);
                    $result_updated=DB::table('beneficiary_enrollments as t1')
                         ->where('id',$result->id)
                     ->update(['t1.term1_fees'=>DB::raw('encryptVal(0)'),
                      't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                     "updated_at"=>Carbon::now(),"updated_by"=>$user_id//waas the issue
                 ]); 
                 $was_knock_out_done=1; 
                }   
                 break;
             case 2:
                 if($current_results->t2_fees>0){
                 $annual_fees=$current_results->annual_fees-$current_results->t2_fees;
                 $table_data=array(
                     "enrollment_id"=>$result->id,
                     "fees_knockout_request_id"=>$fees_knockout_id,
                     "term"=>2,
                     "term_fees"=>$current_results->t2_fees,
                     "created_at"=>Carbon::now(),
                     "created_by"=>$user_id
                 );
              insertRecord('payment_fees_knockout_logs', $table_data, $user_id);
              $result_updated=DB::table('beneficiary_enrollments as t1')
                      ->where('id',$result->id)
                  ->update(['t1.term2_fees'=>DB::raw('encryptVal(0)'),
                  't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                  "updated_at"=>Carbon::now(),"updated_by"=>$user_id]); 
                  $was_knock_out_done=1;
                  
               }
                 break;
             case 3:
                 if($current_results->t3_fees>0){
                 $annual_fees=$current_results->annual_fees-$current_results->t3_fees;
                 $table_data=array(
                     "enrollment_id"=>$result->id,
                     "fees_knockout_request_id"=>$fees_knockout_id,
                     "term"=>3,
                     "term_fees"=>$current_results->t3_fees,
                     "created_at"=>Carbon::now(),
                     "created_by"=>$user_id
                 );
              insertRecord('payment_fees_knockout_logs', $table_data, $user_id);
              $result_updated=DB::table('beneficiary_enrollments as t1')
                      ->where('id',$result->id)
                  ->update(['t1.term3_fees'=>DB::raw('encryptVal(0)'),
                  't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                  "updated_at"=>Carbon::now(),"updated_by"=>$user_id
                 ]);
                 $was_knock_out_done=1;
                
               }
               
                 break;
             default:
                 break;
 
           }
         }

         if($knockout_exam_fees==true)
         {
            $current_results=Db::table('beneficiary_enrollments')->where('id',$result->id)
            ->selectraw('decrypt(exam_fees) as exam_fees,decrypt(term1_fees) as t1_fees,decrypt(term2_fees) as t2_fees,decrypt(term3_fees) as t3_fees,decrypt(annual_fees) as annual_fees')->get();
            $current_results=$current_results[0];
            if($current_results->exam_fees>0){
            $annual_fees=$current_results->annual_fees-$current_results->exam_fees;
                 $table_data=array(
                        "enrollment_id"=>$result->id,
                        "fees_knockout_request_id"=>$fees_knockout_id,
                        "term"=>10,
                        "term_fees"=>$current_results->exam_fees,
                        "created_at"=>Carbon::now(),
                        "created_by"=>$user_id
                 );
                 insertRecord('payment_fees_knockout_logs', $table_data, $user_id);
                    $result_updated=DB::table('beneficiary_enrollments as t1')
                         ->where('id',$result->id)
                     ->update(['t1.exam_fees'=>DB::raw('encryptVal(0)'),
                      't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
                     "updated_at"=>Carbon::now(),"updated_by"=>$user_id//
                 ]); 
                 $was_knock_out_done=1;
                }
         }
         }

        //  echo $resultsbu;
        //  if($was_knock_out_done==0)
        // {
        //     DB::table('payment_fees_knockout')
        //     ->where('id', $fees_knockout_id)
        //     ->delete();
        //    // echo 6;
        //     $res = array(
        //         'success' => false,
        //         'message' => "There was a problem with the fee knockout",
        //         //'results' => $results
        //     );
        // }
        // if($was_knock_out_done==1)
        // {
        //     DB::table('payment_fees_knockout')->update(['status'=>1])->where('id',$fees_knockout_id);
        //     $res = array(
        //         'success' => true,
        //         'message' => "Knockout Successfully",
        //         //'results' => $results
        //     );
        //   //  echo 1;
        // }
        // return response()->json($res);
    } catch (\Exception $e) {
       
        $res = array(
            'success' => false,
            'message' => $e->getMessage()
        );
    } catch (\Throwable $throwable) {
        $res = array(
            'success' => false,
            'message' => $throwable->getMessage()
        );
    }
  
    // return response()->json($res);
    // $data = array('name'=>"Virat Gandhi");
    //   Mail::send('mail', $data, function($message) {
    //      $message->to('murumbajob78@gmail.com', 'Tutorials Point')->subject
    //         ('Laravel HTML Testing Mail');
    //      $message->from('xyz@gmail.com','Virat Gandhi');
    //   });
        //return;
    }

}
