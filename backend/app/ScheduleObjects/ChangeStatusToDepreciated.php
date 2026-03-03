<?php
    namespace App\ScheduleObjects;
    use Illuminate\Support\Facades\DB;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Auth;
    class ChangeStatusToDepreciated
    {
        public function __invoke()
        {   

                    $user_id=4;
                    $depreciated_assets=Db::table('ar_asset_depreciation')
                    ->whereDate('asset_end_depreciation_date','<=',now()->format('Y-m-d'))
                    ->selectRaw('asset_id')->toArray();
                    $depreciated_assets2=Db::table('stores_asset_depreciation')
                    ->whereDate('asset_end_depreciation_date','<=',now()->format('Y-m-d'))
                    ->selectRaw('asset_id')->toArray();
                    $all_depreciated_assets=array_merge($depreciated_assets,$depreciated_assets2);
                    foreach($all_depreciated_assets as $asset)
                    {
                        DB::table('ar_asset_inventory')->where('id',$asset->asset_id)->update(['status_id'=>7]);
                    }
                  
                  
                   
        }
    }