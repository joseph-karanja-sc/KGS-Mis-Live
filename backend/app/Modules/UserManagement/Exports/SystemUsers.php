<?php
 //job 24/01/2022
namespace App\Modules\UserManagement\Exports;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class SystemUsers  implements FromCollection,WithHeadings
{
    public function headings(): array
    {
        return [
            'Names',
            'Email',
            'Access Point',
            'Programme',
            'Role/Position',
            'Last Login',
            'Last Logout',
            "Logout Type"
            
           

        ];
    }
     /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {

       return Db::table('users as t1')
       ->join('access_points', 't1.access_point_id', '=', 'access_points.id')
       ->leftJoin('user_roles', 't1.user_role_id', '=', 'user_roles.id')
       ->join('titles', 't1.title_id', '=', 'titles.id')
       ->leftJoin('grm_gewel_programmes as t7', 't1.gewel_programme_id', '=', 't7.id')
        ->selectRaw("CONCAT_WS(' ',titles.name,decrypt(t1.first_name),decrypt(t1.last_name)) as names,decrypt(t1.email),
        access_points.name as access_point_name,t7.name as gewel_programme,user_roles.name as user_role_name,t1.last_login_time,t1.last_logout_time,t1.logout_type")
        ->get();
       
     
     
    }
}