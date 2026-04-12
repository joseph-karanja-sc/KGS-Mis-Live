<!DOCTYPE html>
<html>
<head>
    <title>School Accountant Submissions</title>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg,#eef2f7,#f9fbfd);
            margin: 0;
            padding: 30px;
        }

        h1 {
            color:#1b5e20;
            margin-bottom: 20px;
        }

        /* CARD */
        .card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        select {
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-bottom: 15px;
        }

        /* TABLE */
        table {
            width:100%;
            border-collapse: collapse;
            margin-top:10px;
        }

        th {
            background:#1b5e20;
            color:white;
            text-align:left;
            padding:12px;
        }

        td {
            padding:12px;
            border-bottom:1px solid #eee;
        }

        tr:hover {
            background:#e8f5e9;
            cursor:pointer;
        }

        /* BUTTONS */
        button {
            padding:8px 14px;
            border:none;
            border-radius:6px;
            background:#1b5e20;
            color:white;
            cursor:pointer;
        }

        button:disabled {
            opacity:0.4;
            cursor:not-allowed;
        }

        /* LOADER */
        .loader-overlay {
            position: fixed;
            top:0; left:0;
            width:100%;
            height:100%;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(6px);
            display:flex;
            align-items:center;
            justify-content:center;
            z-index:9999;
            opacity:0;
            pointer-events:none;
            transition:0.3s;
        }

        .loader-overlay.active {
            opacity:1;
            pointer-events:all;
        }

        .loader-box {
            background:white;
            padding:30px;
            border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,0.1);
            text-align:center;
        }

        .spinner {
            width:40px;
            height:40px;
            border:4px solid #eee;
            border-top:4px solid #1b5e20;
            border-radius:50%;
            animation:spin 0.8s linear infinite;
            margin:auto;
        }

        @keyframes spin {
            from {transform:rotate(0deg);}
            to {transform:rotate(360deg);}
        }

        .loader-text {
            margin-top:10px;
            font-weight:500;
            color:#555;
        }

        .empty {
            text-align:center;
            padding:40px;
            color:#777;
        }
    </style>
</head>

<body>

<h1>School Accountant Dashboard</h1>

<div class="card">

    <select id="districtSelect" onchange="loadSchools()">
        <option value="">Select District</option>
    </select>

    <div id="content" class="empty">
        Please select a district to view data
    </div>

</div>

<!-- LOADER -->
<div id="loader" class="loader-overlay">
    <div class="loader-box">
        <div class="spinner"></div>
        <div id="loaderText" class="loader-text">Loading...</div>
    </div>
</div>

<script>

let currentSchool = null;
let currentPage = 1;
let loaderStart = 0;

/* ======================
   ENTERPRISE LOADER
====================== */
function showLoader(msg="Loading..."){
    loaderStart = Date.now();
    document.getElementById("loaderText").innerText = msg;
    document.getElementById("loader").classList.add("active");
}

function hideLoader(){
    let elapsed = Date.now() - loaderStart;

    let delay = Math.max(0, 3000 - elapsed); // 🔥 FORCE MIN 3s

    setTimeout(()=>{
        document.getElementById("loader").classList.remove("active");
    }, delay);
}

/* ======================
   LOAD DISTRICTS
====================== */
async function loadDistricts(){

    showLoader("Loading districts...");

    let res = await fetch('/api/zispis/v1/sa-districts');
    let data = await res.json();

    let select = document.getElementById("districtSelect");

    data.districts.forEach(d=>{
        let opt = document.createElement("option");
        opt.value = d.id;
        opt.textContent = d.name;
        select.appendChild(opt);
    });

    hideLoader();
}

/* ======================
   LOAD SCHOOLS
====================== */
async function loadSchools(){

    let districtId = document.getElementById("districtSelect").value;
    if(!districtId) return;

    showLoader("Fetching schools...");

    let res = await fetch(`/api/zispis/v1/sa-schools?district_id=${districtId}`);
    let data = await res.json();

    let html = `<h3>Schools</h3><table>
        <tr><th>Name</th><th>EMIS</th><th>Total</th></tr>`;

    data.schools.forEach(s=>{
        html += `
            <tr onclick="openSchool(${s.id})">
                <td>${s.name}</td>
                <td>${s.code}</td>
                <td>${s.total}</td>
            </tr>`;
    });

    html += "</table>";

    document.getElementById("content").innerHTML = html;

    hideLoader();
}

/* ======================
   OPEN SCHOOL
====================== */
function openSchool(id){
    currentSchool = id;
    currentPage = 1;
    loadBeneficiaries();
}

/* ======================
   LOAD BENEFICIARIES
====================== */
async function loadBeneficiaries(){

    showLoader(`Loading page ${currentPage}...`);

    let res = await fetch(
        `/api/zispis/v1/sa-beneficiaries?school_id=${currentSchool}&page=${currentPage}`
    );

    let data = await res.json();

    let html = `<h3>Beneficiaries</h3><table>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Beneficiary No</th>
            <th>Status</th>
        </tr>`;

    data.data.forEach((b,i)=>{
        html += `
            <tr>
                <td>${i+1}</td>
                <td>${b.first_name} ${b.last_name}</td>
                <td>${b.beneficiary_no}</td>
                <td>${b.payment_status}</td>
            </tr>`;
    });

    html += "</table>";

    html += `
        <div style="margin-top:15px;">
            <button onclick="prevPage()" ${currentPage==1?'disabled':''}>Prev</button>
            Page ${currentPage} of ${data.last_page}
            <button onclick="nextPage(${data.last_page})" ${currentPage==data.last_page?'disabled':''}>Next</button>
        </div>
    `;

    document.getElementById("content").innerHTML = html;

    hideLoader();
}

/* ======================
   PAGINATION
====================== */
function nextPage(lastPage){
    if(currentPage < lastPage){
        currentPage++;
        loadBeneficiaries();
    }
}

function prevPage(){
    if(currentPage > 1){
        currentPage--;
        loadBeneficiaries();
    }
}

/* INIT */
loadDistricts();

</script>

</body>
</html>