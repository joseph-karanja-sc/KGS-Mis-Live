<?php
/* created by Job on 08/05/2022 */
namespace App\Console\Commands;

use App\Mail\ComplaintsLagNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class FeeKnockoutReversal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:feesknockoutreversal   {user_id} {fees_knockout_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reverse a particular Fees Knockout';

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
        $user_id=$this->argument("user_id");
        $payment_knockout_id = $this->argument('fees_knockout_id');
 
        $was_there_an_update=0;
        $qry= db::table('payment_fees_knockout_logs as t1')
           ->join('payment_fees_knockout as t2','t2.id','t1.fees_knockout_request_id')
           ->join('beneficiary_enrollments as t3','t3.id','t1.enrollment_id')
            ->selectraw('enrollment_id,term_fees,term,decrypt(t3.annual_fees) as annual_fees')
            ->where('fees_knockout_request_id',$payment_knockout_id);
            $results = $qry->get()->toArray();
            foreach($results as $result)
            {
                $current_results=Db::table('beneficiary_enrollments')->where('id',$result->enrollment_id)
                ->selectraw('decrypt(term1_fees) as t1_fees,decrypt(term2_fees) as t2_fees,decrypt(term3_fees) as t3_fees,decrypt(annual_fees) as annual_fees')->get();
                $current_results=$current_results[0];
                $term_to_reverse=$result->term;
         
                $term_to_update="";
                if($term_to_reverse==1)
                {
                    $term_to_update="term1_fees";

                }else if($term_to_reverse==2)
                {
                    $term_to_update="term2_fees";
                }else if($term_to_reverse=10)
                {
                    $term_to_update="exam_fees";
                }
                else{
                    $term_to_update="term3_fees"; 
                }

                $annual_fees=$current_results->annual_fees+$result->term_fees;
                $qry=db::table('beneficiary_enrollments as t1')
                ->selectraw('t1.term1_fees',)
                ->where('id',$result->enrollment_id);
                $result=DB::table('beneficiary_enrollments as t1')
                ->where('id',$result->enrollment_id)
            ->update(["t1.$term_to_update"=>DB::raw('encryptVal('. $result->term_fees.')'),
            't1.annual_fees'=>DB::raw('encryptVal('.$annual_fees.')'),
            "updated_at"=>Carbon::now(),"updated_by"=>$user_id,]);
            $was_there_an_update=1;
            }
            
            if($was_there_an_update==1){
            $result=DB::table('payment_fees_knockout as t1')
            ->where('id',$payment_knockout_id)
        ->update(["t1.status"=>0,
        "updated_at"=>Carbon::now(),"updated_by"=>$user_id]);
    }

}
}