@extends('layouts.parentMailLayout')
@section('content')
    <table border='2' cellpadding='3' class="table table-striped">
        <thead>
        <tr>
            <td>SN</td>
            <td>Form No</td>
            <td>Complaint details</td>
            <td>Date of Entry</td>
            <td>No of days</td>
        </tr>
        </thead>
        <tbody>
        <?php
        $count = 1;
        foreach ($complaints as $complaint) {?>
        <tr>
            <td> <?php echo $count; ?></td>
            <td><?php echo $complaint->complaint_form_no; ?></td>
            <td><?php echo $complaint->complaint_details; ?></td>
            <td><?php echo converter2($complaint->complaint_record_date); ?></td>
            <td><?php echo $complaint->numOfDays; ?></td>
        </tr>
        <?php $count++; ?>
        <?php }
        ?>
        </tbody>
    </table>
@stop
