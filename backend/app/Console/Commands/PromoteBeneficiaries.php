<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PromoteBeneficiaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:promote';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Annual Beneficiaries Grade Promotion';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*$year = date('Y');
        $description = 'Beneficiary Grade Promotions for the Year ' . $year;
        $meta_params = array(
            'year' => $year,
            'description' => $description,
            'created_at' => Carbon::now()
        );
        $log_data = array(
            'process_type' => 'Beneficiary Grade Annual Promotions',
            'process_description' => $this->description,
            'created_at' => Carbon::now()
        );
        $checker = DB::table('ben_annual_promotions')
            ->where('year', $year)
            ->count();
        if ($checker > 0) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Found another promotion entry for ' . $year;
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info('Status: Failed');
            $this->info('');
            $this->info('Message: Found another promotion entry for ' . $year);
            exit();
        }
         //check for a missed year
        $max_year = DB::table('ben_annual_promotions')->max('year');
        $next_year = ($max_year + 1);
        if ($next_year != $year) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = 'Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year;
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info('Status: Failed');
            $this->info('');
            $this->info('Message: Promotion should be for the year ' . $next_year . ', but trying to do promotion for ' . $year);
            exit();
        }
        DB::transaction(function () use ($meta_params, $log_data, $year) {
            try {
                $prev_year = $year - 1;
                $promotion_id = DB::table('ben_annual_promotions')->insertGetId($meta_params);
                //gradeNines for Promotion
                $where = array(
                    'current_school_grade' => 9,
                    'enrollment_status' => 1,
                    'under_promotion' => 0
                );
                $grade_nines_main_qry = DB::table('beneficiary_information')
                    ->where($where);

                $grade_nines_qry = clone $grade_nines_main_qry;
                $grade_nines_qry->select(DB::raw("id as girl_id,$prev_year as prev_year,$year as promotion_year,'MIS Auto' as created_by"));
                $grade_nines = $grade_nines_qry->get();
                $grade_nines = convertStdClassObjToArray($grade_nines);
                $size = 100;
                $grade_nines_chunks = array_chunk($grade_nines, $size);
                foreach ($grade_nines_chunks as $grade_nines_chunk) {
                    DB::table('grade_nines_for_promotion')->insert($grade_nines_chunk);
                }

                $update_params = array(
                    'under_promotion' => 1,
                    'promotion_year' => $year
                );
                $grade_nines_update_qry = clone $grade_nines_main_qry;
                $grade_nines_update_qry->update($update_params);

                $promotion_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade as from_grade,current_school_grade+1 as to_grade,school_id,$promotion_id as promotion_id"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->get();
                $promotion_data = convertStdClassObjToArray($promotion_data);

                $grade_log_data = DB::table('beneficiary_information')
                    ->select(DB::raw("id as girl_id,current_school_grade+1 as grade,school_id,$year as year"))
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->get();
                $grade_log_data = convertStdClassObjToArray($grade_log_data);

                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->where('current_school_grade', 12)
                    ->update(array('enrollment_status' => 4));
                DB::table('beneficiary_information')
                    ->where('enrollment_status', 1)
                    ->whereIn('current_school_grade', array(8, 10, 11))
                    ->update(array('current_school_grade' => DB::raw('current_school_grade+1'), 'last_annual_promo_date' => DB::raw('NOW()')));

                $promotion_chunks = array_chunk($promotion_data, $size);
                foreach ($promotion_chunks as $promotion_chunk) {
                    DB::table('ben_annual_promotion_details')->insert($promotion_chunk);
                }
                $grade_log_chunks = array_chunk($grade_log_data, $size);
                foreach ($grade_log_chunks as $grade_log_chunk) {
                    DB::table('beneficiary_grade_logs')->insert($grade_log_chunk);
                }

                $log_data['status'] = 'Successful';
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                $this->info('Status: Successful');
                $this->info('');
                $this->info('Message: Promotion for ' . $year . ' executed successfully');
            } catch (\Exception $e) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $e->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                $this->info('Status: Failed');
                $this->info('');
                $this->info('Message: ' . $e->getMessage());
            } catch (\Throwable $throwable) {
                $log_data['status'] = 'Failed';
                $log_data['failure_reason'] = $throwable->getMessage();
                DB::table('auto_processes_logs')
                    ->insert($log_data);
                $this->info('Status: Failed');
                $this->info('');
                $this->info('Message: ' . $throwable->getMessage());
            }
        }, 5);
        return;*/
    }

}
