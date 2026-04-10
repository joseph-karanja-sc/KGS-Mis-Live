<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>School Fees Disbursement Re-trials</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { font-family: "Inter", sans-serif; background: #f4f6f9; margin: 0; }

        .header { background: #1b5e20; color: white; padding: 16px 25px; font-size: 20px; font-weight: 600; }

        .container { width: 96%; margin: 20px auto; }

        .card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        table { width: 100%; border-collapse: collapse; }
        th { background:#f8f9fb; padding:10px; font-size:13px; }
        td { padding:10px; font-size:13px; border-bottom:1px solid #eee; }

        .status-failed { color:#c62828; }
        .status-success { color:#1b5e20; }

        .retry-btn, .refresh-btn, .retry-all-btn {
            border:none; padding:6px 12px; border-radius:6px; cursor:pointer; color:white;
        }

        .retry-btn { background:#1b5e20; }
        .refresh-btn { background:#1976d2; }
        .retry-all-btn { background:#ef6c00; }

        .top-actions {
            display:flex; gap:10px; justify-content:flex-end; margin-bottom:10px;
        }

        #confirmModal {
            display:none; position:fixed; top:0; left:0; right:0; bottom:0;
            background:rgba(0,0,0,0.4); align-items:center; justify-content:center;
        }

        .modal-box {
            background:white; padding:20px; border-radius:10px; width:350px; text-align:center;
        }

        .toast {
            position:fixed; top:20px; left:50%; transform:translateX(-50%);
            padding:12px 18px; border-radius:8px; color:white;
        }

        .toast-success { background:#1b5e20; }
        .toast-error { background:#c62828; }
    </style>
</head>

<body>

<div class="header">School Fees Disbursement Re-trials</div>

<div class="container">
<div class="card">

    <div id="summaryCard" style="
        background:#ffe5e5;
        color:#c62828;
        padding:12px 16px;
        border-radius:8px;
        margin-bottom:15px;
        font-weight:600;
        font-size:14px;
    ">
        Total Failed Transactions: <span id="totalFailed">0</span>
    </div>

    <div class="top-actions">
        <button class="retry-all-btn" onclick="confirmRetryAll()">
            <i class="fas fa-redo"></i> Retry All
        </button>

        <button class="refresh-btn" onclick="loadData(1)">
            <i class="fas fa-sync"></i> Refresh
        </button>
    </div>

    <div id="tableContainer"></div>

</div>
</div>

<!-- MODAL -->
<div id="confirmModal">
    <div class="modal-box">
        <p id="confirmText">Are you sure?</p>
        <br>
        <button onclick="closeModal()">Cancel</button>
        <button onclick="confirmAction()">OK</button>
    </div>
</div>

<script>

let currentAction = null;
let currentPayload = null;
let currentPage = 1; // ✅ pagination state

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerText = msg;
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),3000);
}

function openModal(text, action, payload=null){
    document.getElementById('confirmText').innerText = text;
    document.getElementById('confirmModal').style.display='flex';
    currentAction = action;
    currentPayload = payload;
}

function closeModal(){
    document.getElementById('confirmModal').style.display='none';
}

function confirmAction(){
    closeModal();
    if(retryData){
        retrySingle(retryData.transaction_id, retryData.btn);
    } else if(currentAction){
        currentAction(currentPayload);
    }
}

function loadData(page = 1) {

    currentPage = page; // ✅ update page

    document.getElementById('tableContainer').innerHTML = 'Loading...';

    fetch(`/api/zispis/v1/pg/failed-payments-fee?page=${currentPage}`)
    .then(res => res.json())
    .then(res => {

        if (!res || !res.data) {
            document.getElementById('tableContainer').innerHTML = 'Error loading data';
            return;
        }

        document.getElementById('totalFailed').innerText = res.data.total || 0;

        let rows = res.data.data || [];
        let pagination = res.data;

        if (!rows.length) {
            document.getElementById('tableContainer').innerHTML = 'No failed transactions';
            return;
        }

        let html = `<table>
        <thead>
            <tr>
                <th>#</th>
                <th>School</th>
                <th>School District</th>
                <th>Bank</th>
                <th>Bank Acc</th>
                <th>Transaction ID</th>
                <th>Result Code</th>
                <th>Status</th>
                <th>Details</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>
        </thead><tbody>`;

        rows.forEach((r, i) => {
            html += `
            <tr>
                <td>${(pagination.from || 0) + i}</td>
                <td>${r.school_name || ''}</td>
                <td>${r.district_name || ''}</td>
                <td>${r.district_bank_name || ''}</td>
                <td>${r.district_bank_account || ''}</td>
                <td>${r.transaction_id || ''}</td>
                <td>${r.result_code || ''}</td>
                <td class="${r.status === 'success' ? 'status-success' : 'status-failed'}">
                    ${r.status || ''}
                </td>
                <td>${r.result_details || ''}</td>
                <td>${r.fee_amount || ''}</td>
                <td>
                    <button class="retry-btn"
                        ${r.status === 'success' ? 'disabled' : ''}
                        onclick="confirmRetrySingle('${r.transaction_id}', this)">
                        Retry
                    </button>
                </td>
            </tr>`;
        });

        html += `
        </tbody></table>

        <!-- PAGINATION -->
        <div style="margin-top:15px; display:flex; justify-content:center; gap:10px;">
            <button onclick="prevPage()" ${pagination.current_page === 1 ? 'disabled' : ''}>
                ⬅ Prev
            </button>

            <span style="padding:6px 12px;">
                Page ${pagination.current_page} of ${pagination.last_page}
            </span>

            <button onclick="nextPage()" ${pagination.current_page === pagination.last_page ? 'disabled' : ''}>
                Next ➡
            </button>
        </div>
        `;

        document.getElementById('tableContainer').innerHTML = html;

    })
    .catch(err => {
        console.error(err);
        document.getElementById('tableContainer').innerHTML = 'Failed to load data';
    });
}

/* PAGINATION FUNCTIONS */
function nextPage(){
    currentPage++;
    loadData(currentPage);
}

function prevPage(){
    currentPage--;
    loadData(currentPage);
}

/* ========================
   RETRY SINGLE
======================== */
let retryData = null;

function confirmRetrySingle(transaction_id, btn){
    retryData = { transaction_id, btn };
    openModal("Retry this transaction?");
}

function retrySingle(transaction_id, btn){

    btn.disabled = true;
    btn.innerText = "Retrying...";

    fetch('/api/zispis/v1/pg/retry-one-payment-school', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ transaction_id })
    })
    .then(res => res.json())
    .then(res => {

        const pg = res.pg_response;

        if(res.status === true && pg?.ResultCode == 100){
            showToast(pg?.ResultDetails || "Payment successful","success");
            setTimeout(()=>loadData(currentPage),1500);
        } else {
            showToast(pg?.ResultDetails || res.error || "Retry failed","error");
            btn.disabled = false;
            btn.innerText = "Retry";
        }

    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = "Retry";
        showToast("Request failed","error");
    });
}

/* ========================
   RETRY ALL
======================== */
function confirmRetryAll(){
    openModal("Retry ALL failed transactions?", retryAll);
}

function retryAll(){

    showToast("Processing all...", "success");

    fetch('/api/zispis/v1/processAllSchoolsForPG', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            payment_ref_no:"KGS/PAY/REQ/2026/0001",
            payment_type:"school",
            mode: "retry"
        })
    })
    .then(res=>res.json())
    .then(res=>{
        showToast(
            `Processed: ${res.processed}, Success: ${res.success}, Failed: ${res.failed}`
        );
        loadData(currentPage);
    });
}

/* INIT */
loadData(1);

</script>

</body>
</html>