<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Disbursement Re-trials</title>

<style>
    body {
        font-family: "Inter", sans-serif;
        background: #f5f7fa;
        margin: 0;
    }

    .header {
        background: #1b5e20;
        color: white;
        padding: 15px 20px;
        font-size: 22px;
        font-weight: 600;
    }

    .container {
        width: 95%;
        margin: 10px auto;
        padding: 20px;
    }

    .card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.06);
    }

    .loading-text, .empty-text {
        text-align: center;
        font-size: 18px;
        margin-top: 20px;
        display: none;
    }

    .empty-text {
        color: #b30000;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    th, td {
        padding: 12px 10px;
        border-bottom: 1px solid #eee;
        text-align: left;
        font-size: 14px;
    }

    th {
        background: #f0f0f0;
        font-weight: 600;
    }

    .retry-btn {
        background: #1b5e20;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        transition: 0.2s;
    }

    .retry-btn:hover {
        background: #144a19;
    }

    .desc-badge {
        background: #ffebee; 
        color: #c62828;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        display: inline-block;
        border: 1px solid #ffcdd2;
    }

    td.number-col, th.number-col {
        text-align: center !important;
    }


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
                    <th>School</th>
                    <th>District</th>                    
                    <th class="number-col">Phase</th>
                    <th>Ref No</th>
                    <th class="number-col">Total Amount(Kwacha)</th>
                    <th>Transaction ID</th>
                    <th class="number-col">Code</th>
                    <th>Description</th>
                    <th>Timestamp</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="failedBody"></tbody>
        </table>

    </div>
</div>

<!-- ============================
     CONFIRMATION MODAL
============================ -->
<div id="confirmModal" 
     style="display:none; position:fixed; top:0; left:0; right:0; bottom:0;
            background:rgba(0,0,0,0.4); z-index:9999;
            align-items:center; justify-content:center;">

    <div style="background:white; padding:25px; width:350px; border-radius:8px;
                box-shadow:0 4px 12px rgba(0,0,0,0.15); text-align:center;">

        <h3 style="margin-top:0; font-size:18px;">Confirm Retry?</h3>
        <p style="margin-top:10px; font-size:14px;">Are you sure you want to retry this payment?</p>

        <div style="margin-top:20px; display:flex; justify-content:space-between;">
            <button onclick="closeRetryModal()" 
                    style="padding:8px 14px; background:#777; color:white; border:none; border-radius:5px;">
                Cancel
            </button>

            <button id="confirmRetryBtn"
                    style="padding:8px 14px; background:#1b5e20; color:white; border:none; border-radius:5px;">
                Retry Payment
            </button>
        </div>

    </div>
</div>


<script>
let retryData = null;

// ==================================
// FETCH FAILED RECORDS
// ==================================
document.addEventListener("DOMContentLoaded", function () {

    const apiUrl = "/api/zispis/v1/pg/failed-payments";
    const loading = document.getElementById("loading");
    const emptyMsg = document.getElementById("empty");
    const table = document.getElementById("failedTable");
    const tbody = document.getElementById("failedBody");

    loading.style.display = "block";

    fetch(apiUrl)
        .then(res => res.json())
        .then(data => {
            loading.style.display = "none";

            if (!data.data || data.data.data.length === 0) {
                emptyMsg.style.display = "block";
                return;
            }

            table.style.display = "table";

            data.data.data.forEach((row, index) => {
                let tr = document.createElement("tr");

                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${row.school_name ?? ''}</td>
                    <td>${row.district_name ?? ''}</td>
                    <td class="number-col">${row.payment_phase}</td>
                    <td>${row.payment_ref_no}</td>
                    <td class="number-col">${row.grant_amount}</td>
                    <td>${row.transaction_id}</td>
                    <td class="number-col">${row.result_code}</td>
                    <td>
                        <span class="desc-badge">${row.result_description ?? ''}</span>
                    </td>
                    <td>${row.created_at}</td>
                    <td>
                        <button class="retry-btn"
                            onclick="openRetryConfirm('${row.school_id}', '${row.payment_ref_no}', '${row.payment_phase}', this)">
                            Retry
                        </button>
                    </td>
                `;

                tbody.appendChild(tr);
            });


        })
        .catch(err => {
            loading.innerText = "Error fetching data.";
            showToast("error", "Error Loading Data", err.message);
        });


    // Assign retry action
    document.getElementById("confirmRetryBtn").addEventListener("click", function () {
        if (!retryData) return;
        closeRetryModal();

        retryPayment(
            retryData.school_id,
            retryData.payment_ref_no,
            retryData.payment_phase,
            retryData.btn
        );
    });
});


// ===============================
// OPEN & CLOSE MODAL
// ===============================
function openRetryConfirm(school_id, payment_ref_no, payment_phase, btn) {
    retryData = { school_id, payment_ref_no, payment_phase, btn };
    document.getElementById("confirmModal").style.display = "flex";
}

function closeRetryModal() {
    document.getElementById("confirmModal").style.display = "none";
}



// ===============================
// REAL RETRY PAYMENT (API)
// ===============================
function retryPayment(school_id, payment_ref_no, payment_phase, btnElement) {

    if (btnElement) {
        btnElement.disabled = true;
        btnElement.innerText = "Retrying...";
        btnElement.style.opacity = "0.7";
    }

    fetch("/api/zispis/v1/pg/retry-one-payment", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify({
            school_id,
            payment_ref_no,
            payment_phase
        })
    })
    .then(async res => {

        // Handle API-level errors (404, 500, etc.)
        if (!res.ok) {
            const text = await res.text();
            throw new Error("API Error: " + text);
        }

        return res.json();
    })
    .then(data => {

        // PG response expected structure
        const pg = data.pg_response;

        if (!pg) {
            showToast("error", "System Error", "Invalid PG response format.");
            return;
        }

        if (pg.ResultCode === 100) {
            showToast("success", "Payment Successful",
                `${pg.ResultDescription}<br>${pg.ResultDetails}`
            );

            setTimeout(() => location.reload(), 1500);
        } else {
            showToast("error", "Payment Failed",
                `${pg.ResultDescription}<br>${pg.ResultDetails}`
            );

            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerText = "Retry";
                btnElement.style.opacity = "1";
            }
        }

    })
    .catch(err => {
        showToast("error", "Request Failed", err.message);

        if (btnElement) {
            btnElement.disabled = false;
            btnElement.innerText = "Retry";
            btnElement.style.opacity = "1";
        }
    });
}



// ===============================
// TOAST MESSAGE COMPONENT
// ===============================
function showToast(type, title, message) {

    let box = document.createElement("div");

    box.style.position = "fixed";
    box.style.top = "20px";
    box.style.left = "50%";
    box.style.transform = "translateX(-50%) translateY(-10px)";
    box.style.padding = "18px 22px";
    box.style.borderRadius = "8px";
    box.style.fontSize = "15px";
    box.style.maxWidth = "450px";
    box.style.width = "auto";
    box.style.zIndex = "9999";
    box.style.color = "white";
    box.style.boxShadow = "0 4px 14px rgba(0,0,0,0.25)";
    box.style.opacity = "0";
    box.style.transition = "all 0.3s ease";

    box.style.background = type === "success" ? "#1b5e20" : "#c62828";

    box.innerHTML = `
        <strong style="font-size:16px;">${title}</strong>
        <div style="margin-top:6px; font-size:14px; opacity:0.9;">${message}</div>
    `;

    document.body.appendChild(box);

    setTimeout(() => {
        box.style.opacity = "1";
        box.style.transform = "translateX(-50%) translateY(0)";
    }, 50);

    setTimeout(() => {
        box.style.opacity = "0";
        box.style.transform = "translateX(-50%) translateY(-10px)";
        setTimeout(() => box.remove(), 300);
    }, 4000);
}
</script>

</body>
</html>
