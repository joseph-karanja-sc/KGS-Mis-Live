<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\ScheduleObjects\AssetsDueMail;
use App\ScheduleObjects\AssetsAutoMaintMove;
use App\ScheduleObjects\ChangeStatusToDepreciated;
use App\ScheduleObjects\FeesKnockOut;
use App\ScheduleObjects\FeeKnockoutReversal;



class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\PromoteBeneficiaries::class,
        Commands\LogLaggingGrievances::class,
        Commands\SendLaggingGrievancesEmailNot::class,
        Commands\SendFailedEmailsCmd::class,
        Commands\FeesKnockOut::class,//job on 07/05/2022
        Commands\FeeKnockoutReversal::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
        // $schedule->command('command:promote')->cron('00 23 30 12 *');
        $schedule->command('command:promote')->cron('00 15 31 12 *');
        $schedule->command('command:logLaggingGrievances')->everyMinute();
        $schedule->command('command:notifyOnLaggingGrievances')->daily();
        $schedule->command('command:sendFailedEmails')->everyMinute();
        $schedule->call(new AssetsDueMail)->daily();
        $schedule->call(new AssetsAutoMaintMove)->daily();
        $schedule->call(new ChangeStatusToDepreciated)->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
