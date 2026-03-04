<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=10, user-scalable=yes">
    <meta charset="UTF-8">

    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <link rel="shortcut icon" href="{{asset('resources/images/favicon.ico')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('resources/css/toastr.css')}}"/>
    <link rel="stylesheet" type="text/css" href="{{asset('resources/css/pace.css')}}"/>
    <link rel="stylesheet" type="text/css" href="{{asset('resources/css/style.css')}}"/>
    <title>MOE::KGS MIS 3.0.0</title>
</head>
<body>

<div id="loading-mask">
    <img alt="Loading..." src="<?php echo $base_url; ?>/resources/images/loader.gif"/>
</div>

<script type="text/javascript">
    const token = document.querySelector('meta[name="csrf-token"]').content,
        backendVersion = "{{ App::VERSION() }}",
        currentYear = '<?php echo isset($year) ? $year : ''; ?>',
        currentTerm = '<?php echo isset($term) ? $term : ''; ?>',
        currentTermTxt = "Term <?php echo isset($term) ? $term : ''; ?>",
        is_logged_in = '<?php echo isset($is_logged_in) ? $is_logged_in : false; ?>',
        is_reset_pwd = '<?php echo isset($is_reset_pwd) ? $is_reset_pwd : false; ?>',
        guid = "<?php echo isset($guid) ? $guid : ''; ?>",
        user_id = '<?php echo isset($user_id) ? $user_id : ''; ?>',
        title_id = '<?php echo isset($title_id) ? $title_id : ''; ?>',
        gender_id = '<?php echo isset($gender_id) ? $gender_id : ''; ?>',
        profile_pic_url = "<?php echo isset($profile_pic_url) ? $profile_pic_url : ''; ?>",
        first_name = "<?php echo isset($first_name) ? $first_name : ''; ?>",
        last_name = "<?php echo isset($last_name) ? $last_name : ''; ?>",
        fullnames = "<?php echo isset($title) ? $title : '';?>" + " <?php echo isset($first_name) ? $first_name : '';?>" + " <?php echo isset($last_name) ? $last_name : ''; ?>",
        base_url = "<?php echo $base_url; ?>",
        user_role_description = "<?php echo isset($access_point) ? $access_point : ''; ?>" + " <?php echo isset($role) ? $role : ''; ?>",
        email_address = "<?php echo isset($email) ? $email : ''; ?>",
        phone_number = "<?php echo isset($phone) ? $phone : ''; ?>",
        mobile_number = "<?php echo isset($mobile) ? $mobile : ''; ?>",
        dms_url = "<?php echo $base_url . '/mis_dms/'; ?>",
        dms_url2 = "<?php echo $base_url . '/seeddms/'; ?>",
        term_num_days = '<?php echo isset($term_num_days) ? $term_num_days : ''; ?>',
        threshhold_attendance_rate = '<?php echo isset($threshhold_attendance_rate) ? $threshhold_attendance_rate : ''; ?>',
        active_tasks_height = '<?php echo isset($active_tasks_height) ? $active_tasks_height : ''; ?>',
        guidelines_height = '<?php echo isset($guidelines_height) ? $guidelines_height : ''; ?>',
        max_selection = '<?php echo isset($max_selection) ? $max_selection : ''; ?>',
         max_selection_for_payment_request = '<?php echo isset($max_selection_for_payment_request) ? $max_selection_for_payment_request : ''; ?>'
        weekly_border_plus = '<?php echo isset($weekly_border_plus) ? $weekly_border_plus : ''; ?>',
        max_exam_fees='<?php echo isset($max_exam_fees) ? $max_exam_fees : ''; ?>',
        max_map_limit = 2000,
        max_excel_upload = '<?php echo isset($max_excel_upload) ? $max_excel_upload : 20000; ?>',
        version = '<?php echo isset($version) ? $version : '2.0'; ?>',
        default_dashboard = '<?php echo isset($default_dashboard) ? $default_dashboard : '{}'; ?>',
        componentsArray = JSON.parse('<?php echo json_encode(isset($componentsArray) ? $componentsArray : []); ?>'),
        utilisation_button_visibility='<?php 
        use Illuminate\Support\Facades\DB;
        $is_coordinator=Db::table('users')->where('id',auth()->id())->value('is_coordinator');
        echo $is_coordinator==1? false: true?>';

    var Ext = Ext || {}; // Ext namespace won't be defined yet...

    // This function is called by the Microloader after it has performed basic
    // device detection. The results are provided in the "tags" object. You can
    // use these tags here or even add custom tags. These can be used by platform
    // filters in your manifest or by platformConfig expressions in your app.
    //
    Ext.beforeLoad = function (tags) {
        var s = location.search,  // the query string (ex "?foo=1&bar")
            profile;

        // For testing look for "?classic" or "?modern" in the URL to override
        // device detection default.
        //
        if (s.match(/\bclassic\b/)) {
            profile = 'classic';
        } else if (s.match(/\bmodern\b/)) {
            profile = 'modern';
        }

            // uncomment this if you have added native build profiles to your app.json
        /*else if (tags.webview) {
         if (tags.ios) {
         profile = 'ios';
         }
         // add other native platforms here
         }*/
        else {
            //profile = tags.desktop ? 'classic' : 'modern';
            profile = tags.phone ? 'modern' : 'classic';
        }
        profile = 'classic';

        Ext.manifest = profile; // this name must match a build profile name

        // This function is called once the manifest is available but before
        // any data is pulled from it.
        //
        //return function (manifest) {
        // peek at / modify the manifest object
        //};
    };
</script>

<script type="text/javascript" src="{{asset('resources/js/jquery-3.1.1.js')}}"></script>
<script type="text/javascript" src="{{asset('resources/js/toastr.js')}}"></script>
{{--<script type="text/javascript" src="{{asset('resources/js/pace.js')}}"></script>--}}
<script type="text/javascript" src="{{asset('resources/js/custom.js')}}"></script>
<!-- The line below must be kept intact for Sencha Cmd to build your application-->
<script id="microloader" type="text/javascript" src="{{asset('bootstrap.js')}}"></script>
</body>
</html>
