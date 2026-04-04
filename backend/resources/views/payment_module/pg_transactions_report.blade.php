<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PG Transactions Monitor</title>

<style>
    body {
        font-family: "Inter", sans-serif;
        background: #f5f7fa;
        margin: 0;
        font-size: 14px;
    }

    /* Header */
    .header {
        background: #1b5e20;
        color: white;
        padding: 15px 20px;
        font-size: 20px;
        font-weight: 600;
    }

    .container {
        max-width: 1500px;
        margin: auto;
        padding: 20px;
    }

    /* Filters */
    .filters {
        background: white;
        padding: 15px;
        border-radius: 6px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: end;
    }

    .filters input,
    .filters select {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        width: 200px;
    }

    .btn {
        padding: 10px 16px;
        background: #1b5e20;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn:hover {
        background: #145a17;
    }

    /* Table */
    table {
        width: 100%;
        margin-top: 20px;
        border-collapse: collapse;
        background: white;
        border-radius: 6px;
        overflow: hidden;
    }

    th, td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        text-align: left;
        font-size: 14px;
    }

    th {
        background: #f0f0f0;
        font-weight: 600;
    }

    tr:hover td {
        background: #f7f7f7;
    }

    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: white;
    }
    .success { background: #2e7d32; }
    .failed  { background: #d32f2f; }
    .error   { background: #f57c00; }
    .pending { background: #757575; }

    /* Modal */
    #txnModal {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    #txnModalContent {
        width: 80%;
        background: white;
        padding: 20px;
        border-radius: 6px;
        max-height: 90vh;
        overflow-y: auto;
        font-family: monospace;
        white-space: pre-wrap;
    }

    .modal-close {
        float: right;
        cursor: pointer;
        color: #555;
        font-size: 20px;
    }
    /* NEW ENTERPRISE MODAL DESIGN */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(2px);
        }

        .modal-box {
            width: 70%;
            max-height: 90vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 14px;
            padding: 25px 35px;
            animation: popIn 0.25s ease-out;
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.9); }
            to   { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            color: #1b5e20;
            font-weight: 700;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 22px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 14px;
            background: white;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid #eee;
        }

        .summary-label {
            font-weight: 600;
            color: #444;
        }

        .summary-value {
            color: #222;
        }

        /* Status badge inside modal */
        .badge-modal {
            padding: 4px 10px;
            border-radius: 6px;
            color: #fff;
            font-weight: 600;
            font-size: 12px;
        }

        .details-section {
            margin-top: 18px;
        }

        .details-section h4 {
            margin-bottom: 8px;
            color: #1b5e20;
            font-size: 16px;
        }
        /* Dark hacker theme */
        .code-block {
            background: #050505 !important;
            padding: 14px;
            border-radius: 8px;
            font-family: "JetBrains Mono", monospace;
            font-size: 13px;
            white-space: pre-wrap;
            color: #00ff90;
            text-shadow: 0 0 6px #00ff90;
            border: 1px solid #00ff9033;
        }


        /* JSON syntax coloring */
        .json-key {
            color: #00ff90;          /* bright neon green */
            font-weight: 600;
        }

        .json-string {
            color: #7affb6;          /* soft mint green */
        }

        .json-number {
            color: #00e676;          /* deep lime */
        }

        .json-boolean {
            color: #00c853;          /* darker green */
        }

        .json-null {
            color: #66ffa6;
        }


        .modal-close {
            cursor: pointer;
            font-size: 22px;
            font-weight: bold;
            color: #777;
        }

        .modal-close:hover {
            color: black;
        }

        .inspect-btn {
            display: flex;
            align-items: center;
            gap: 4px;
        }


</style>

</head>
<body>

<!-- HEADER -->
<div class="header">Payment Gateway Transactions Monitor</div>

<div class="container">

    <!-- Filters -->
    <div class="filters">
        <div>
            <label>Payment Ref No</label><br>
            <input id="fRefNo">
        </div>

        <div>
            <label>Phase</label><br>
            <select id="fPhase">
                <option value="">All</option>
                <option value="1">Phase 1</option>
                <option value="2">Phase 2</option>
            </select>
        </div>

        <div>
            <label>Status</label><br>
            <select id="fStatus">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
                <option value="error">Error</option>
            </select>
        </div>

        <div>
            <label>Search</label><br>
            <input id="fSearch" placeholder="Transaction ID / School">
        </div>

        <button class="btn" onclick="loadTransactions()">Search</button>
    </div>

    <!-- Table -->
    <table id="pgTable">
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>School</th>
                <th>Ref No</th>
                <th>Phase</th>
                <th>Status</th>
                <th>Result Code</th>
                <th>HTTP</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</div>


<!-- Modern Modal -->
<div id="txnModal" class="modal-overlay">
    <div class="modal-box">

        <div class="modal-header">
            <h3>Transaction Details</h3>
            <span class="modal-close" onclick="hideModal()">×</span>
        </div>

        <div id="txnSummary" class="summary-grid"></div>

        <div class="details-section">
            <h4>Request URL</h4>
            <div class="code-block" id="txnReqUrl"></div>
        </div>

        <div class="details-section">
            <h4>Request Payload</h4>
            <pre class="code-block" id="txnReqPayload"></pre>
        </div>

        <div class="details-section">
            <h4>Response Body</h4>
            <pre class="code-block" id="txnResBody"></pre>
        </div>

        <div class="details-section">
            <h4>Headers</h4>
            <pre class="code-block" id="txnHeaders"></pre>
        </div>

    </div>
</div>


<script>
/* Load transactions list */
async function loadTransactions1() {

    let params = {
        payment_ref_no : document.getElementById("fRefNo").value.trim(),
        payment_phase  : document.getElementById("fPhase").value,
        status         : document.getElementById("fStatus").value,
        search         : document.getElementById("fSearch").value.trim(),
    };

    let url = "/api/zispis/v1/pg/transactions?" + new URLSearchParams(params);
    console.log("Loading:", url);

    const res = await fetch(url);
    const json = await res.json();

    const tbody = document.querySelector("#pgTable tbody");
    tbody.innerHTML = "";

    if (!json.status) {
        tbody.innerHTML = "<tr><td colspan='8' style='text-align:center'>No data</td></tr>";
        return;
    }

    json.data.data.forEach(row => {

        let badgeClass =
            row.pg_status === "success" ? "success" :
            row.pg_status === "failed"  ? "failed" :
            row.pg_status === "error"   ? "error"   : "pending";

        let badge = `<span class="badge ${badgeClass}">${row.pg_status}</span>`;

        tbody.innerHTML += `
            <tr>
                <td>${row.transaction_id}</td>
                <td>${row.school_name ?? "-"}</td>
                <td>${row.payment_ref_no}</td>
                <td>${row.payment_phase}</td>
                <td>${badge}</td>
                <td>${row.http_status ?? "-"}</td>
                <td>${row.created_at}</td>
                <td>
                    <button class="btn" onclick="viewTxn('${row.transaction_id}')">🐞 Inspect</button>
                </td>



            </tr>
        `;
    });

    console.log("URL CALLED:", url);
    console.log("RAW RESPONSE:", res);
    console.log("JSON:", json);

}

async function loadTransactions(customUrl = null) {

    let params = {
        payment_ref_no : document.getElementById("fRefNo").value.trim(),
        payment_phase  : document.getElementById("fPhase").value,
        status         : document.getElementById("fStatus").value,
        search         : document.getElementById("fSearch").value.trim(),
    };

    let url = customUrl ?? "/api/zispis/v1/pg/transactions?" + new URLSearchParams(params);

    const res = await fetch(url);
    const json = await res.json();

    const tbody = document.querySelector("#pgTable tbody");
    tbody.innerHTML = "";

    if (!json.status || json.data.length === 0) {
        tbody.innerHTML = "<tr><td colspan='9' style='text-align:center'>No data</td></tr>";
        return;
    }

    json.data.forEach(row => {

        let badgeClass =
            row.pg_status === "success" ? "success" :
            row.pg_status === "failed"  ? "failed" :
            row.pg_status === "error"   ? "error"   : "pending";

        let badge = `<span class="badge ${badgeClass}">${row.pg_status}</span>`;

        tbody.innerHTML += `
            <tr>
                <td>${row.transaction_id}</td>
                <td>${row.school_name ?? "-"}</td>
                <td>${row.payment_ref_no}</td>
                <td>${row.payment_phase}</td>
                <td>${badge}</td>
                <td>${row.result_code ?? "-"}</td>
                <td>${row.http_status ?? "-"}</td>
                <td>${row.created_at}</td>
                <td>
                    <button onclick="viewTxn('${row.transaction_id}')">Inspect</button>
                </td>
            </tr>
        `;
    });

    renderPagination(json.pagination);
}

function renderPagination(pagination) {

    let html = "";

    if (pagination.prev_page) {
        html += `<button onclick="loadTransactions('${pagination.prev_page}')">Prev</button>`;
    }

    html += ` Page ${pagination.current_page} of ${pagination.last_page} `;

    if (pagination.next_page) {
        html += `<button onclick="loadTransactions('${pagination.next_page}')">Next</button>`;
    }

    document.getElementById("pagination").innerHTML = html;
}


/* View Transaction Modal */
async function viewTxn(txnId) {

    let url = `/api/zispis/v1/pg/transactions/${txnId}`;
    const res = await fetch(url);
    const json = await res.json();

    if (!json.status) {
        showModal();
        document.getElementById("txnReqUrl").innerText = "Error loading transaction.";
        return;
    }

    const t = json.transaction;

    // determine badge color
    let badgeClass =
        t.pg_status === "success" ? "success" :
        t.pg_status === "failed"  ? "failed"  :
        t.pg_status === "error"   ? "error"   : "pending";

    // Build summary section
    document.getElementById("txnSummary").innerHTML = `
        <div class="summary-item">
            <span class="summary-label">School</span>
            <span class="summary-value">${t.school_name}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Transaction ID</span>
            <span class="summary-value">${t.transaction_id}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Payment Ref</span>
            <span class="summary-value">${t.payment_ref_no}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Phase</span>
            <span class="summary-value">${t.payment_phase}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Status</span>
            <span class="badge-modal ${badgeClass}">${t.pg_status}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">HTTP</span>
            <span class="summary-value">${t.http_status ?? "-"}</span>
        </div>
    `;

    // Fill JSON sections
    document.getElementById("txnReqUrl").innerText = t.request_url;
    document.getElementById("txnReqPayload").innerHTML = formatJson(t.request_payload);
    document.getElementById("txnResBody").innerHTML = formatJson(t.response_body);
    document.getElementById("txnHeaders").innerHTML = formatJson(t.headers);


    showModal();
}


function showModal() {
    document.getElementById("txnModal").style.display = "flex";
}

function hideModal() {
    document.getElementById("txnModal").style.display = "none";
}

function formatJson(str) {
    try {
        let json = JSON.parse(str);

        // Convert json → pretty string
        let pretty = JSON.stringify(json, null, 2);

        // Syntax highlighting
        pretty = pretty
            .replace(/\"(.*?)\":/g, '<span class="json-key">"$1"</span>:')
            .replace(/: \"(.*?)\"/g, ': <span class="json-string">"$1"</span>')
            .replace(/: (\d+)/g, ': <span class="json-number">$1</span>')
            .replace(/: (true|false)/g, ': <span class="json-boolean">$1</span>')
            .replace(/: null/g, ': <span class="json-null">null</span>');

        return pretty;

    } catch (e) {
        return str;
    }
}


document.addEventListener("DOMContentLoaded", function () {
    console.log("Page loaded → fetching transactions...");
    loadTransactions();
});

</script>

</body>
</html>
