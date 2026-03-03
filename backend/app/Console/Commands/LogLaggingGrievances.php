<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LogLaggingGrievances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:logLaggingGrievances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log grievances which are lagging behind';

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
        $log_data = array(
            'process_type' => 'Log Lagging Grievances',
            'process_description' => $this->description,
            'created_at' => Carbon::now()
        );
        try {
            //1. district->ongoing grievances
            $this->logDistrictLevelLaggingComplaints();
            //2. prov/hq->ongoing grievances
            $this->logProvHQLevelLaggingComplaints();

            $log_data['status'] = 'Successful';
            DB::table('auto_processes_logs')
                ->insert($log_data);

        } catch (\Exception $exception) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = $exception->getMessage();
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info($exception->getMessage());
        } catch (\Throwable $throwable) {
            $log_data['status'] = 'Failed';
            $log_data['failure_reason'] = $throwable->getMessage();
            DB::table('auto_processes_logs')
                ->insert($log_data);
            $this->info($throwable->getMessage());
        }
        return;
    }

    public function logDistrictLevelLaggingComplaints()
    {
        //districts=40days, province/hq=60days
        $districtDays = Config('constants.GRM.grm_district_lag');
        $provHqDays = Config('constants.GRM.grm_provhq_lag');
        //1. district->ongoing grievances
        $qry1 = DB::table('grm_complaint_details as t1')
            ->leftJoin('grm_lagging_grievances as t2', function ($join) {
                $join->on('t2.complaint_id', '=', 't1.id')
                    ->where('t2.notification_level', 'district')
                    ->where('t2.status_type', 'ongoing');
            })
            ->select(DB::raw("t1.id as complaint_id,'district' as notification_level,'ongoing' as status_type"))
            ->where('t1.workflow_stage_id', '<>', 3)
            ->where('t1.record_status_id', 1)
            ->whereNull('t2.id')
            ->whereRaw("TOTAL_WEEKDAYS(t1.complaint_record_date,now()) BETWEEN ($districtDays+1) AND $provHqDays");
        $ongoingDistData = $qry1->get();
        $ongoingDistData = convertStdClassObjToArray($ongoingDistData);
        DB::table('grm_lagging_grievances')->insert($ongoingDistData);
        $this->info('Lagging grievances logged successfully. (' . count($ongoingDistData) . ' [District]');
    }

    public function logProvHQLevelLaggingComplaints()
    {
        //districts=40days, province/hq=60days
        $provHqDays = Config('constants.GRM.grm_provhq_lag');
        //2. prov/hq->ongoing grievances
        $qry2 = DB::table('grm_complaint_details as t1')
            ->leftJoin('grm_lagging_grievances as t2', function ($join) {
                $join->on('t2.complaint_id', '=', 't1.id')
                    ->where('t2.notification_level', 'provhq')
                    ->where('t2.status_type', 'ongoing');
            })
            ->select(DB::raw("t1.id as complaint_id,'provhq' as notification_level,'ongoing' as status_type"))
            ->where('t1.workflow_stage_id', '<>', 3)
            ->where('t1.record_status_id', 1)
            ->whereNull('t2.id')
            ->whereRaw("TOTAL_WEEKDAYS(t1.complaint_record_date,now())>$provHqDays");
        $ongoingProvHqData = $qry2->get();
        $ongoingProvHqData = convertStdClassObjToArray($ongoingProvHqData);
        DB::table('grm_lagging_grievances')->insert($ongoingProvHqData);
        $this->info('Lagging grievances logged successfully. (' . count($ongoingProvHqData) . '[Province/HQ])');
    }

}
