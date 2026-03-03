<?php
    namespace App\ScheduleObjects;
    use Illuminate\Support\Facades\DB;
    use Mail;
    use Carbon\Carbon;
    class AssetsDueMail
    {
        public function __invoke()
        {
            $due_checkouts=DB::table('ar_asset_checkout_details as t1')
        ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
         ->where("t1.due_date","=",Carbon::now()->format('Y-m-d'))//!important
         ->selectRaw('t1.user_id,t2.description,t2.serial_no')->toArray();
         $due_checkouts2=DB::table('stores_asset_checkout_details as t1')
         ->join('ar_asset_inventory as t2','t2.id','t1.asset_id')
          ->where("t1.due_date","=",Carbon::now()->format('Y-m-d'))//!important
          ->selectRaw('t1.user_id,t2.description,t2.serial_no')->toArray();
        $combined_checkouts=array_merge($due_checkouts,$due_checkouts2);
         $combined_checkouts=convertStdClassObjToArray($due_checkouts);
         $array_of_user_ids=[];
         $checkout_details_mails=[];
         foreach($combined_checkouts as $checkout)
         {
             if (validateisNumeric($checkout['user_id']) && $checkout['user_id']!=0) {
                 $checkout_details_mails[]=array(
                     "user_id"=> $checkout['user_id'],
                     "asset"=>[
                         "description"=>$checkout['description'],
                         "serial_no"=>$checkout['serial_no']
                     ]
                     );
                 if(!in_array($checkout['user_id'],$array_of_user_ids))
                 {
                     $array_of_user_ids[]=$checkout['user_id'];
                     
                 
                 }
             
             }else{
                 $user_ids=$this->returnArrayFromStringArray($checkout['user_id']);
                 foreach($user_ids as $user_id_after_split)
                 {
                     $checkout_details_mails[]=array(
                         "user_id"=>  $user_id_after_split,
                         "asset"=>[
                             "description"=>$checkout['description'],
                             "serial_no"=>$checkout['serial_no']
                         ]
                         );
                     if(!in_array($user_id_after_split,$array_of_user_ids))
                 {
                     $array_of_user_ids[]=$checkout['user_id'];
                 }
                     

                 }

             }

           

         }
         $users_details=Db::table('users as t1')->selectRaw('t1.id as user_id,decrypt(t1.last_name) as user_last_name,
         decrypt(t1.email) as user_email')->whereIn('id',$array_of_user_ids)->get()->toArray();
       

       foreach($users_details as $mail_user)
       {
           $mail_to_person=$mail_user->user_email;
           $user_last_name=$mail_user->user_last_name;
           $mail_to_person="murumbajob78@gmail.com";
           $mail_to_person=$mail_user->user_email;

           $user_check_outs_this=$this->_search_array_by_value($checkout_details_mails,"user_id",$mail_user->user_id);
          
           
           foreach($user_check_outs_this as  $asset_inside_checkout_data)
           {
             $assets[]=$asset_inside_checkout_data['asset'];
           }
           $msg="";
           $subject="";
           $url=url('/');
           $image_url=$url.'/backend/public/moge_logo.png';
           if(count($assets)>1)
           {
               $msg="You are expected to return the following assets today(".date('d/m/Y').") before end of business.";
               $subject="Assets Return";

           }else{
               $msg="You are expected to return this  asset today(".date('d/m/Y').") before end of business.";
               $subject="Asset Return";
           }
           $data=[
             "assets"=>$assets,
             "subject"=>$subject,
             "image_url"=>$image_url,
             "msg"=>$msg,
             "sal"=>"Dear  ".$user_last_name.","
         ];
     
        
            $result= Mail::send('mail.assetDueReminder',$data,function($message) use($mail_to_person,$subject,$data){
             $message->to($mail_to_person,$mail_to_person);
             $message->subject($subject);
             $message->from('mogekgs@gmail.com','MOGE');
         });
        
       }

        }

        private function _search_array_by_value($array,$key,$value) {
            $results = array();
    
            if (is_array($array)) {
                if (isset($array[$key]) && $array[$key] == $value) {
                    $results[] = $array;
                }
        
                foreach ($array as $subarray) {
                    $results = array_merge($results,$this->_search_array_by_value($subarray, $key, $value));
                }
            }
        
            return $results;
        }
        private function returnArrayFromStringArray($string_array)
        {
            $string_array=substr($string_array, 0, -1);
            $final_array=explode(',' ,substr($string_array,1));
            return $final_array;
        }
    }
?>