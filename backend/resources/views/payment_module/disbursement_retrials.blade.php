<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Disbursement Re-trials</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>

body {
    font-family: "Inter", sans-serif;
    background: #f4f6f9;
    margin: 0;
}

/* HEADER */
.header {
    background: #1b5e20;
    color: white;
    padding: 16px 25px;
    font-size: 20px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* CONTAINER */
.container {
    width: 96%;
    margin: 20px auto;
}

/* CARD */
.card {
    background: white;
    padding: 18px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

/* STATES */
.loading-text, .empty-text {
    text-align: center;
    font-size: 15px;
    margin: 20px 0;
    display: none;
}

.empty-text {
    color: #c62828;
}

/* TABLE */
.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

/* HEADER */
th {
    background: #f8f9fb;
    font-weight: 600;
    font-size: 13px;
    color: #444;
    padding: 12px;
    border-bottom: 1px solid #eee;
    position: sticky;
    top: 0;
}

/* ROWS */
td {
    padding: 12px;
    font-size: 13px;
    border-bottom: 1px solid #f0f0f0;
}

/* zebra effect */
tr:nth-child(even) {
    background: #fafafa;
}

/* NUMBER ALIGN */
.number-col {
    text-align: center;
}

/* BADGE */
.desc-badge {
    background: #fff5f5;
    color: #c62828;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
    border: 1px solid #ffd6d6;
    max-width: 300px;
}

/* STATUS */
.status-badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}

.status-failed {
    background: #ffe5e5;
    color: #c62828;
}

.status-success {
    background: #e8f5e9;
    color: #1b5e20;
}

/* BUTTON */
.retry-btn {
    background: #1b5e20;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: 0.2s;
}

.retry-btn:hover {
    background: #144a19;
}

.retry-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* MODAL */
#confirmModal {
    display:none;
    position:fixed;
    top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.4);
    z-index:9999;
    align-items:center;
    justify-content:center;
}

.modal-box {
    background:white;
    padding:25px;
    width:350px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 5px 20px rgba(0,0,0,0.15);
}

.modal-actions {
    margin-top:20px;
    display:flex;
    justify-content:space-between;
}

.modal-actions button {
    padding:8px 14px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

.cancel-btn { background:#777; color:white; }
.confirm-btn { background:#1b5e20; color:white; }

/* TOAST */
.toast {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 9999;
    box-shadow: 0 4px 14px rgba(0,0,0,0.2);
}

.toast-success { background: #1b5e20; }
.toast-error { background: #c62828; }

</style>
</head>

<body>

<div class="header">Disbursement Re-trials</div>

<div class="container">
<div class="card">

<div id="loading" class="loading-text">Fetching failed payments...</div>
<div id="empty" class="empty-text">No failed payments found.</div>

<div class="table-wrapper">
<table id="failedTable" style="display:none;">
<thead>
<tr>
<th>#</th>
<th>District</th>
<th>Bank</th>
<th>Account</th>
<th>Branch</th>
<th>Sort Code</th>
<th class="number-col">Amount</th>
<th>Transaction ID</th>
<th class="number-col">Code</th>
<th>Description</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody id="failedBody"></tbody>
</table>
</div>

</div>
</div>

<!-- MODAL -->
<div id="confirmModal">
<div class="modal-box">
<h3>Confirm Retry</h3>
<p>Are you sure you want to retry this payment?</p>
<div class="modal-actions">
<button class="cancel-btn" onclick="closeRetryModal()">Cancel</button>
<button id="confirmRetryBtn" class="confirm-btn">Retry</button>
</div>
</div>
</div>

<script>

let retryData = null;

// FETCH DATA
window.addEventListener("DOMContentLoaded", () => {

    fetch("/api/zispis/v1/pg/failed-payments")
        .then(res => res.json())
        .then(data => {

            const rows = data.data?.data || [];

            if (!rows.length) {
                document.getElementById("empty").style.display = "block";
                return;
            }

            document.getElementById("failedTable").style.display = "table";

            rows.forEach((row, index) => {

                const tr = document.createElement("tr");

                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${row.district_name ?? ''}</td>
                    <td>${row.district_bank_name ?? ''}</td>
                    <td>${row.district_bank_account ?? ''}</td>
                    <td>${row.district_branch ?? ''}</td>
                    <td>${row.district_sort_code ?? ''}</td>
                    <td class="number-col">${row.grant_amount ?? 0}</td>
                    <td>${row.transaction_id}</td>
                    <td class="number-col">${row.result_code ?? ''}</td>
                    <td><span class="desc-badge">${row.result_details ?? ''}</span></td>
                    <td>
                        <span class="status-badge ${row.status === 'failed' ? 'status-failed' : 'status-success'}">
                            ${row.status}
                        </span>
                    </td>
                    <td>
                        <button class="retry-btn" onclick="openRetryConfirm('${row.transaction_id}', this)">
                            Retry
                        </button>
                    </td>
                `;

                document.getElementById("failedBody").appendChild(tr);
            });

        });

    document.getElementById("confirmRetryBtn").addEventListener("click", () => {
        if (!retryData) return;
        closeRetryModal();
        retryPayment(retryData.transaction_id, retryData.btn);
    });
});

// MODAL
function openRetryConfirm(transaction_id, btn) {
    retryData = { transaction_id, btn };
    document.getElementById("confirmModal").style.display = "flex";
}

function closeRetryModal() {
    document.getElementById("confirmModal").style.display = "none";
}

// RETRY
function retryPayment(transaction_id, btn) {

    btn.disabled = true;
    btn.innerText = "Retrying...";

    fetch("/api/zispis/v1/pg/retry-one-payment-district", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ transaction_id })
    })
    .then(res => res.json())
    .then(data => {

        const pg = data.pg_response;
        const message = data.message || "Operation completed";

        if (data.status === true && pg?.ResultCode === 100) {

            showToast("success", message, pg?.ResultDetails);
            setTimeout(() => location.reload(), 1500);

        } else {

            showToast("error", message, pg?.ResultDetails);
            btn.disabled = false;
            btn.innerText = "Retry";
        }

    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = "Retry";
        showToast("error", "Error", "Request failed");
    });
}

// TOAST
function showToast(type, title, message) {

    const box = document.createElement("div");
    box.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'}`;

    box.innerHTML = `<strong>${title}</strong><br>${message || ''}`;

    document.body.appendChild(box);

    setTimeout(() => box.remove(), 4000);
}

</script>

</body>
</html>