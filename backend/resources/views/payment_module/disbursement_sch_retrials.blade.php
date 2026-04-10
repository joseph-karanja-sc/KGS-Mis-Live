<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>School Fees Disbursement Re-trials</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <style>
        body {
            font-family: "Inter", sans-serif;
            background: #f4f6f9;
            margin: 0;
        }

        .header {
            background: #1b5e20;
            color: white;
            padding: 16px 25px;
            font-size: 20px;
            font-weight: 600;
        }

        .container {
            width: 96%;
            margin: 20px auto;
        }

        .card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
        }

        .loading-text,
        .empty-text {
            text-align: center;
            font-size: 15px;
            margin: 20px 0;
            display: none;
        }

        .empty-text {
            color: #c62828;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #f8f9fb;
            font-weight: 600;
            font-size: 13px;
            padding: 12px;
            position: sticky;
            top: 0;
        }

        td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        .number-col {
            text-align: center;
        }

        .desc-badge {
            background: none;
            border: none;
            padding: 0;
            color: #c62828;
            font-size: 13px;
            line-height: 1.4;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .status-failed {
            background: #ffe5e5;
            color: #c62828;
        }

        .status-success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .retry-btn {
            background: #1b5e20;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }

        .retry-btn:disabled {
            opacity: 0.5;
        }

        /* refresh button */
        .refresh-btn {
            background: #1976d2;
            color: white;
            border: none;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        /* modal */
        #confirmModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-box {
            background: white;
            padding: 25px;
            width: 350px;
            border-radius: 10px;
            text-align: center;
        }

        .modal-actions {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }

        .cancel-btn {
            background: #777;
            color: white;
        }

        .confirm-btn {
            background: #1b5e20;
            color: white;
        }

        .toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            z-index: 9999;
        }

        .toast-success {
            background: #1b5e20;
        }

        .toast-error {
            background: #c62828;
        }
    </style>
</head>

<body>

    <div class="header">School Fees Disbursement Re-trials</div>

    <div class="container">
        <div class="card">

           <div style="display:flex; justify-content:flex-end; padding: 10px;">
                <button class="refresh-btn" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh 
                </button>
            </div>


            <div id="loading" class="loading-text">This feature will be available soon...</div>

        </div>
    </div>

    <script>
        // refresh button jose
        function refreshData() {
            // loadFailedPayments();
        }
    </script>

</body>

</html>