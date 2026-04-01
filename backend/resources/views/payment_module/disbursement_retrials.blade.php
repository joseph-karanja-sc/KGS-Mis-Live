<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Disbursement Re-trials</title>

<style>
body { font-family: "Inter", sans-serif; background: #f5f7fa; margin: 0; }
.header { background: #1b5e20; color: white; padding: 15px 20px; font-size: 22px; font-weight: 600; }
.container { width: 95%; margin: 10px auto; padding: 20px; }
.card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.06); }
.loading-text, .empty-text { text-align: center; font-size: 18px; margin-top: 20px; display: none; }
.empty-text { color: #b30000; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { padding: 12px 10px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
th { background: #f0f0f0; font-weight: 600; }
.retry-btn { background: #1b5e20; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
.desc-badge { background: #ffebee; color: #c62828; padding: 3px 8px; border-radius: 4px; font-size: 12px; display: inline-block; border: 1px solid #ffcdd2; }
td.number-col, th.number-col { text-align: center !important; }
</style>
</head>

<body>

<div class="header">Disbursement Re-trials</div>

<div class="container">
<div class="card">

<div id="loading" class="loading-text">Fetching failed payments...</div>
<div id="empty" class="empty-text">No failed payments found.</div>

<table id="failedTable" style="display:none;">
<thead>
<tr>
<th>#</th>
<th>District</th>
<th>Bank Name</th>
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

<!-- Modal -->
<div id="confirmModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
<div style="background:white; padding:25px; width:350px; border-radius:8px; text-align:center;">
<h3>Confirm Retry?</h3>
<p>Are you sure you want to retry this payment?</p>
<div style="margin-top:20px; display:flex; justify-content:space-between;">
<button onclick="closeRetryModal()">Cancel</button>
<button id="confirmRetryBtn">Retry Payment</button>
</div>
</div>
</div>

<script>
let retryData = null;

// Fetch failed payments
window.addEventListener("DOMContentLoaded", () => {
    const apiUrl = "/api/zispis/v1/pg/failed-payments";

    fetch(apiUrl)
        .then(res => res.json())
        .then(data => {
            const rows = data.data?.data || [];

            if (rows.length === 0) {
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
                    <td>${row.status ?? ''}</td>
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

function openRetryConfirm(transaction_id, btn) {
    retryData = { transaction_id, btn };
    document.getElementById("confirmModal").style.display = "flex";
}

function closeRetryModal() {
    document.getElementById("confirmModal").style.display = "none";
}

function retryPayment(transaction_id, btn) {
    btn.disabled = true;
    btn.innerText = "Retrying...";

    fetch("/api/zispis/v1/pg/retry-one-payment", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ transaction_id })
    })
    .then(res => res.json())
    .then(data => {
        const pg = data.pg_response;

        if (pg?.ResultCode === 100) {
            alert("Success: " + pg.ResultDetails);
            location.reload();
        } else {
            alert("Failed: " + (pg?.ResultDetails || 'Error'));
            btn.disabled = false;
            btn.innerText = "Retry";
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = "Retry";
    });
}
</script>

</body>
</html>