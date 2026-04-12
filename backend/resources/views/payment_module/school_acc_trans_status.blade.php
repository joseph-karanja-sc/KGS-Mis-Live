<!DOCTYPE html>
<html>
<head>
    <title>School Accountant Submissions</title>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: Inter; background:#f5f7fa; padding:20px; }
        h1 { color:#1b5e20; }

        select { padding:8px; border-radius:6px; }

        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th,td { padding:10px; border-bottom:1px solid #eee; }
        th { background:#1b5e20; color:white; }

        .btn { padding:6px 12px; cursor:pointer; }
    </style>
</head>

<body>

<h1>School Accountant App</h1>

<select id="districtSelect" onchange="loadSchools()">
    <option value="">Select District</option>
</select>

<div id="content"></div>

<script>

let currentSchool = null;
let currentPage = 1;

/* ======================
   LOAD DISTRICTS
====================== */
async function loadDistricts() {

    let res = await fetch('/api/zispis/v1/sa-districts');
    let data = await res.json();

    let select = document.getElementById("districtSelect");

    data.districts.forEach(d => {
        let opt = document.createElement("option");
        opt.value = d.id;
        opt.textContent = d.name;
        select.appendChild(opt);
    });
}

/* ======================
   LOAD SCHOOLS
====================== */
async function loadSchools() {

    let districtId = document.getElementById("districtSelect").value;

    let res = await fetch(`/api/zispis/v1/sa-schools?district_id=${districtId}`);
    let data = await res.json();

    let html = `<h3>Schools</h3><table>
        <tr><th>Name</th><th>EMIS</th><th>Total</th></tr>`;

    data.schools.forEach(s => {
        html += `
            <tr onclick="openSchool(${s.id})">
                <td>${s.name}</td>
                <td>${s.code}</td>
                <td>${s.total}</td>
            </tr>`;
    });

    html += "</table>";

    document.getElementById("content").innerHTML = html;
}

/* ======================
   OPEN SCHOOL
====================== */
function openSchool(schoolId) {
    currentSchool = schoolId;
    currentPage = 1;
    loadBeneficiaries();
}

/* ======================
   LOAD BENEFICIARIES (LAZY)
====================== */
async function loadBeneficiaries() {

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

    data.data.forEach((b,i) => {
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
        <div style="margin-top:10px;">
            <button onclick="prevPage()" ${currentPage==1?'disabled':''}>Prev</button>
            Page ${currentPage} of ${data.last_page}
            <button onclick="nextPage(${data.last_page})" ${currentPage==data.last_page?'disabled':''}>Next</button>
        </div>
    `;

    document.getElementById("content").innerHTML = html;
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