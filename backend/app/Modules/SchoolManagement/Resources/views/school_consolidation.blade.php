<!-- School Consolidation Content -->

<style>
    /* ─────────────────────────────────────────────
       GLOBAL STYLES (matching existing blade)
    ─────────────────────────────────────────────── */
    .consolidation-container {
        padding: 20px;
        max-width: 1800px;
        margin: 0 auto;
    }
    
    /* ─────────────────────────────────────────────
       FILTERS
    ─────────────────────────────────────────────── */
    .filters {
        display: flex;
        gap: 15px;
        align-items: center;
        padding: 20px;
        background: white;
        border-bottom: 1px solid #e0e0e0;
        flex-wrap: wrap;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .select-box,
    .search-box {
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
        font-size: 14px;
        min-width: 220px;
    }
    
    .btn-primary {
        background: #1b5e20;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-primary:hover {
        background: #256628;
    }
    
    .btn-secondary {
        background: #ddd;
        color: #333;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .btn-secondary:hover {
        background: #ccc;
    }
    
    /* ─────────────────────────────────────────────
       DRAG AND DROP PANELS
    ─────────────────────────────────────────────── */
    .consolidation-panels {
        display: flex;
        gap: 20px;
        height: 450px;
    }
    
    .panel {
        flex: 1;
        background: white;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .panel-header {
        padding: 16px 20px;
        background: #f5f5f5;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    
    .panel-header h3 {
        margin: 0;
        font-size: 16px;
        color: #333;
        font-weight: 600;
    }
    
    .panel-header .badge {
        background: #1b5e20;
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }
    
    .panel-body.drag-over {
        background: #e8f5e9;
        border: 2px dashed #1b5e20;
    }
    
    /* ─────────────────────────────────────────────
       SCHOOL ITEM (DRAGGABLE)
    ─────────────────────────────────────────────── */
    .school-item {
        padding: 12px 16px;
        margin-bottom: 8px;
        background: #fafafa;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        cursor: grab;
        transition: all 0.2s;
    }
    
    .school-item:hover {
        background: #f0f7f1;
        border-color: #1b5e20;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .school-item.dragging {
        opacity: 0.5;
        cursor: grabbing;
    }
    
    .school-item.mother-school {
        background: #e8f5e9;
        border-color: #1b5e20;
        border-left: 4px solid #1b5e20;
    }
    
    .school-item .school-name {
        font-weight: 600;
        font-size: 14px;
        color: #333;
        margin-bottom: 4px;
    }
    
    .school-item .school-meta {
        font-size: 12px;
        color: #666;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .school-item .school-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .school-item .mother-badge {
        display: inline-block;
        background: #1b5e20;
        color: white;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
        margin-left: 8px;
    }
    
    /* ─────────────────────────────────────────────
       ACTION BUTTONS
    ─────────────────────────────────────────────── */
    .action-buttons {
        display: flex;
        gap: 10px;
        padding: 16px 20px;
        background: white;
        border-top: 1px solid #e0e0e0;
        border-radius: 0 0 8px 8px;
    }
    
    .btn-edit {
        background: #1976d2;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
    }
    
    .btn-edit:hover {
        background: #1565c0;
    }
    
    .btn-consolidate {
        background: #1b5e20;
        color: white;
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        margin-left: auto;
    }
    
    .btn-consolidate:hover {
        background: #256628;
    }
    
    .btn-consolidate:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    .btn-remove {
        background: transparent;
        color: #d32f2f;
        border: 1px solid #d32f2f;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-remove:hover {
        background: #ffebee;
    }
    
    /* ─────────────────────────────────────────────
       MODAL STYLES
    ─────────────────────────────────────────────── */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 800px;
        max-width: 95%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 28px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.22);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1b5e20;
        font-weight: 700;
        font-size: 20px;
    }
    
    .modal-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        font-size: 14px;
        color: #333;
    }
    
    .form-group label .required {
        color: #d32f2f;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #1b5e20;
        box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
    }
    
    .form-group input.error,
    .form-group select.error {
        border-color: #d32f2f !important;
        background-color: #fff5f5;
    }
    
    .error-message {
        color: #d32f2f;
        font-size: 12px;
        margin-top: 4px;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }
    
    /* ─────────────────────────────────────────────
       LOADING & EMPTY STATES
    ─────────────────────────────────────────────── */
    .loading-spinner {
        padding: 80px 20px;
        text-align: center;
        color: #555;
    }
    
    .loading-spinner::before {
        content: '';
        display: inline-block;
        width: 28px;
        height: 28px;
        border: 4px solid #1b5e20;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 12px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .empty-state {
        padding: 40px;
        text-align: center;
        color: #888;
        font-size: 15px;
    }
    
    /* ─────────────────────────────────────────────
       INSTRUCTIONS BOX (Collapsible)
    ─────────────────────────────────────────────── */
    .instructions-box {
        background: #effff0;
        border: 1px solid #81ff8a;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .instructions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        cursor: pointer;
        user-select: none;
    }
    
    .instructions-header:hover {
        background: #d4f7d9;
    }
    
    .instructions-header h4 {
        margin: 0;
        color: #1b5e20;
        font-size: 14px;
    }
    
    .instructions-toggle {
        font-size: 18px;
        color: #1b5e20;
        transition: transform 0.3s;
    }
    
    .instructions-toggle.expanded {
        transform: rotate(180deg);
    }
    
    .instructions-content {
        display: none;
        padding: 0 16px 16px 16px;
    }
    
    .instructions-content.show {
        display: block;
    }
    
    .instructions-content ul {
        margin: 0;
        padding-left: 20px;
        color: #333;
        font-size: 13px;
    }
    
    .instructions-content li {
        margin-bottom: 4px;
    }
    
    /* ─────────────────────────────────────────────
       RESPONSIVE
    ─────────────────────────────────────────────── */
    @media (max-width: 768px) {
        .consolidation-panels {
            flex-direction: column;
        }
        
        .modal-body {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="consolidation-container">
    
    <!-- Instructions -->
    <div class="instructions-box">
        <div class="instructions-header" onclick="toggleInstructions()">
            <h4>How to Consolidate Schools</h4>
            <span class="instructions-toggle" id="instructionsToggle">▼</span>
        </div>
        <div class="instructions-content" id="instructionsContent">
            <ul>
                <li>Drag schools from the left panel to the right panel to select schools for consolidation</li>
                <li>The <strong>first school</strong> in the right panel will be the <strong>Final Active School</strong> (receives all beneficiaries)</li>
                <li>You can drag schools back to the left panel if needed</li>
                <li>Click "Edit Details" to modify school information before consolidation</li>
                <li>Click "Consolidate" to merge all beneficiaries to the Final Active School and mark others as deleted</li>
            </ul>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters">
        <select id="consolidationDistrictFilter" class="select-box">
            <option value="">All Districts</option>
            @foreach($districts as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </select>
        
        <input type="text" id="consolidationSchoolSearch" class="search-box" placeholder="Search school name/emis..." />
        
        <button class="btn-primary" onclick="loadConsolidationSchools()">Apply Filter</button>
        
        <span id="consolidationCount" style="margin-left: auto; font-size: 14px; color: #666;">
            Total Schools: <strong id="totalSchoolsCount">0</strong>
        </span>
    </div>
    
    <!-- Drag and Drop Panels -->
    <div class="consolidation-panels">
        <!-- Left Panel - Available Schools -->
        <div class="panel">
            <div class="panel-header">
                <h3>Available Schools</h3>
                <span class="badge" id="availableCount">0</span>
            </div>
            <div class="panel-body" id="availableSchoolsPanel" 
                 ondrop="drop(event, 'available')" 
                 ondragover="allowDrop(event)"
                 ondragleave="leaveDrop(event)">
                <div class="loading-spinner">
                    <span style="display: block; text-align: center;">Loading schools...</span>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Schools to Consolidate -->
        <div class="panel">
            <div class="panel-header">
                <h3>Schools to Consolidate</h3>
                <span class="badge" id="consolidateCount">0</span>
            </div>
            <div class="panel-body" id="consolidateSchoolsPanel"
                 ondrop="drop(event, 'consolidate')" 
                 ondragover="allowDrop(event)"
                 ondragleave="leaveDrop(event)">
                <div class="empty-state">Drag schools here to consolidate</div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <button class="btn-secondary" onclick="clearConsolidation()">Clear All</button>
        <button class="btn-consolidate" id="consolidateBtn" onclick="consolidateSchoolsFunc()" disabled>
            Consolidate Schools
        </button>
    </div>
</div>

<!-- Edit School Modal -->
<div id="editSchoolModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit School Details</h3>
            <button onclick="closeEditSchoolInfoModal()" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editSchoolId">
            
            <!-- Row 1: Name and EMIS -->
            <div class="form-group">
                <label>School Name <span class="required">*</span></label>
                <input type="text" id="editSchoolName" required>
            </div>
            
            <div class="form-group">
                <label>EMIS Code <span class="required">*</span></label>
                <input type="text" id="editEmisCode" required>
            </div>
            
            <!-- Row 2: Province and District -->
            <div class="form-group">
                <label>School Province</label>
                <input type="text" id="editSchoolProvince" readonly>
            </div>
            
            <div class="form-group">
                <label>School District</label>
                <input type="text" id="editSchoolDistrict" readonly>
            </div>
            
            <!-- Row 3: Phone and Email -->
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" id="editPhone" maxlength="20">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="editEmail">
            </div>
            
            <!-- Row 4: Address (full width) -->
            <div class="form-group full-width">
                <label>Address</label>
                <input type="text" id="editAddress">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeEditSchoolInfoModal()">Cancel</button>
            <button class="btn-primary" onclick="saveSchoolDetails()">Save Changes</button>
        </div>
    </div>
</div>

<script>
    if (typeof $ === 'undefined') {
        console.error('jQuery is required for this page to function properly.');
    }
    if (typeof BASE_URL === 'undefined') {
        const BASE_URL = '{{ url('/') }}';
    }
    
    // State management
    let availableSchools = [];
    let consolidateSchools = [];
    let draggedItem = null;
    let editingSchoolId = null;
    
    // $(document).ready(function() {
    //     initializeConsolidationSelect2();
    //     loadConsolidationSchools();
    // });
    
    function initializeConsolidationSelect2() {
        $('.select2').select2({
            placeholder: "Select option",
            allowClear: true,
            width: '100%'
        });
        
        // Initialize district filter with proper settings
        $('#consolidationDistrictFilter').select2({
            placeholder: "All Districts",
            allowClear: true,
            width: '220px'
        });
        
        // Load schools when district changes
        $('#consolidationDistrictFilter').on('change', function() {
            loadConsolidationSchools();
        });
        
        // Also handle search input enter key
        $('#consolidationSchoolSearch').on('keypress', function(e) {
            if (e.which === 13) {
                loadConsolidationSchools();
            }
        });
    }
    
    // Toggle instructions collapsible
    function toggleInstructions() {
        var content = document.getElementById('instructionsContent');
        var toggle = document.getElementById('instructionsToggle');
        
        if (content.classList.contains('show')) {
            content.classList.remove('show');
            toggle.classList.remove('expanded');
        } else {
            content.classList.add('show');
            toggle.classList.add('expanded');
        }
    }
    
    // // Initialize when document is ready
    // $(document).ready(function() {
    //     initializeConsolidationSelect2();
    //     loadConsolidationSchools();
    // });
    
    // Function to reinitialize when tab is shown
    window.reinitConsolidationTab = function() {
        initializeConsolidationSelect2();
        loadConsolidationSchools();
    };
    
    // Load schools for consolidation
    function loadConsolidationSchools() {
        const panel = document.getElementById('availableSchoolsPanel');
        panel.innerHTML = '<div class="loading-spinner"><span style="display: block; text-align: center;">Loading schools...</span></div>';
        
        const district = document.getElementById('consolidationDistrictFilter').value;
        const search = document.getElementById('consolidationSchoolSearch').value.trim();
        
        let url = `${BASE_URL}/schoolmanagement/schools-for-consolidation`;
        let params = [];
        
        if (district) params.push(`district_id=${district}`);
        if (search) params.push(`search=${encodeURIComponent(search)}`);
        
        if (params.length) url += '?' + params.join('&');
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Filter out schools already in consolidation panel
                    const consolidateIds = consolidateSchools.map(s => s.school_id);
                    availableSchools = data.schools.filter(s => !consolidateIds.includes(s.school_id));
                    
                    renderAvailableSchools();
                    updateCounts();
                } else {
                    panel.innerHTML = '<div class="empty-state">Error loading schools</div>';
                }
            })
            .catch(err => {
                console.error(err);
                panel.innerHTML = '<div class="empty-state">Error loading schools</div>';
            });
    }
    
    function renderAvailableSchools() {
        const panel = document.getElementById('availableSchoolsPanel');
        
        if (availableSchools.length === 0) {
            panel.innerHTML = '<div class="empty-state">No schools found</div>';
            return;
        }
        
        let html = '';
        availableSchools.forEach((school, index) => {
            html += createSchoolItemHtml(school, 'available', index);
        });
        
        panel.innerHTML = html;
    }
    
    function renderConsolidateSchools() {
        const panel = document.getElementById('consolidateSchoolsPanel');
        
        if (consolidateSchools.length === 0) {
            panel.innerHTML = '<div class="empty-state">Drag schools here to consolidate</div>';
            return;
        }
        
        let html = '';
        consolidateSchools.forEach((school, index) => {
            html += createSchoolItemHtml(school, 'consolidate', index);
        });
        
        panel.innerHTML = html;
    }
    
    function createSchoolItemHtml(school, panel, index) {
        const isMother = panel === 'consolidate' && index === 0;
        const motherClass = isMother ? 'mother-school' : '';
        const motherBadge = isMother ? '<span class="mother-badge">Final Active School</span>' : '';
        
        return `
            <div class="school-item ${motherClass}" 
                 draggable="true" 
                 ondragstart="drag(event, '${panel}', ${index})"
                 ondragend="dragEnd(event)"
                 data-school-id="${school.school_id}">
                <div class="school-name">
                    ${school.school_name || 'N/A'} ${motherBadge}
                </div>
                <div class="school-meta">
                    <span>EMIS: ${school.emis || 'N/A'}</span>
                    <span>District: ${school.district_name || 'N/A'}</span>
                    <span>Beneficiaries: ${school.beneficiary_count || 0}</span>
                    <span>Active: ${school.active_beneficiary_count || 0}</span>
                </div>
                ${panel === 'consolidate' ? `
                    <div style="margin-top: 8px; display: flex; gap: 8px;">
                        <button class="btn-edit" onclick="openEditSchoolInfoModal('${school.school_id}')">Edit Details</button>
                        <button class="btn-remove" onclick="removeFromConsolidation(${index})">Remove</button>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    function updateCounts() {
        document.getElementById('availableCount').textContent = availableSchools.length;
        document.getElementById('consolidateCount').textContent = consolidateSchools.length;
        document.getElementById('totalSchoolsCount').textContent = availableSchools.length + consolidateSchools.length;
        
        // Enable/disable consolidate button
        const consolidateBtn = document.getElementById('consolidateBtn');
        consolidateBtn.disabled = consolidateSchools.length < 2;
    }
    
    // Drag and Drop Functions
    function allowDrop(ev) {
        ev.preventDefault();
        ev.currentTarget.classList.add('drag-over');
    }
    
    function leaveDrop(ev) {
        ev.currentTarget.classList.remove('drag-over');
    }
    
    function drag(ev, panel, index) {
        draggedItem = { panel, index };
        ev.dataTransfer.setData("text/plain", JSON.stringify(draggedItem));
        ev.target.classList.add('dragging');
    }
    
    function dragEnd(ev) {
        ev.target.classList.remove('dragging');
        // Remove drag-over from all panels
        document.querySelectorAll('.panel-body').forEach(p => p.classList.remove('drag-over'));
    }
    
    function drop(ev, targetPanel) {
        ev.preventDefault();
        ev.currentTarget.classList.remove('drag-over');
        
        if (!draggedItem) return;
        
        const { panel, index } = draggedItem;
        
        if (panel === targetPanel) {
            draggedItem = null;
            return;
        }
        
        // DISTRICT VALIDATION: Only allow schools from the same district
        if (targetPanel === 'consolidate') {
            // Get the school being dragged
            const school = panel === 'available' ? availableSchools[index] : consolidateSchools[index];
            
            // If there are already schools in consolidation, check district match
            if (consolidateSchools.length > 0) {
                const motherSchool = consolidateSchools[0];
                if (school.district_id !== motherSchool.district_id) {
                    showToast(`Cannot consolidate: "${school.school_name}" is in ${school.district_name || 'a different'} district, but the Final Active School is in ${motherSchool.district_name || 'another'} district. Only schools within the same district can be consolidated.`, 'error');
                    draggedItem = null;
                    return;
                }
            }
        }
        
        if (targetPanel === 'available') {
            // Moving from consolidate to available
            if (index === 0 && consolidateSchools.length > 1) {
                // Moving Final Active School - make next one mother
                const school = consolidateSchools.splice(index, 1)[0];
                availableSchools.push(school);
            } else {
                const school = consolidateSchools.splice(index, 1)[0];
                availableSchools.push(school);
            }
        } else {
            // Moving from available to consolidate
            const school = availableSchools.splice(index, 1)[0];
            consolidateSchools.push(school);
        }
        
        renderAvailableSchools();
        renderConsolidateSchools();
        updateCounts();
        
        draggedItem = null;
    }
    
    function removeFromConsolidation(index) {
        if (index === 0 && consolidateSchools.length > 1) {
            // Moving Final Active School - make next one mother first
            const school = consolidateSchools.splice(index, 1)[0];
            availableSchools.push(school);
        } else {
            const school = consolidateSchools.splice(index, 1)[0];
            availableSchools.push(school);
        }
        
        renderAvailableSchools();
        renderConsolidateSchools();
        updateCounts();
    }
    
    function clearConsolidation() {
        // Move all consolidate schools back to available
        consolidateSchools.forEach(school => {
            availableSchools.push(school);
        });
        consolidateSchools = [];
        
        renderAvailableSchools();
        renderConsolidateSchools();
        updateCounts();
        
        showToast('Cleared all schools from consolidation');
    }
    
    // Edit Modal Functions
    function openEditSchoolInfoModal(schoolId) {
        editingSchoolId = schoolId;
        
        // Find school data
        const school = consolidateSchools.find(s => s.school_id == schoolId);
        if (!school) {
            showToast('School not found', 'error');
            return;
        }
        
        // Populate form with basic info
        document.getElementById('editSchoolId').value = schoolId;
        document.getElementById('editSchoolName').value = school.school_name || '';
        document.getElementById('editEmisCode').value = school.emis || '';
        document.getElementById('editPhone').value = '';
        document.getElementById('editEmail').value = '';
        document.getElementById('editAddress').value = '';
        document.getElementById('editSchoolProvince').value = school.province || '';
        document.getElementById('editSchoolDistrict').value = school.district || '';
        
        // Fetch school details from server
        fetch(`${BASE_URL}/schoolmanagement/school-details/${schoolId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.school) {
                    const s = data.school;
                    document.getElementById('editSchoolName').value = s.name || '';
                    document.getElementById('editEmisCode').value = s.code || '';
                    document.getElementById('editSchoolProvince').value = s.province_name || '';
                    document.getElementById('editSchoolDistrict').value = s.district_name || '';
                    
                    document.getElementById('editPhone').value = s.telephone_no || s.mobile_no || '';
                    document.getElementById('editEmail').value = s.email_address || '';
                    document.getElementById('editAddress').value = s.postal_address || s.physical_address || '';
                }
            });
        
        document.getElementById('editSchoolModal').style.display = 'flex';
    }
    
    function closeEditSchoolInfoModal() {
        document.getElementById('editSchoolModal').style.display = 'none';
        editingSchoolId = null;
    }
    
    function saveSchoolDetails() {
        // Clear previous errors
        $('.error').removeClass('error');
        $('.error-message').remove();
        
        const schoolId = document.getElementById('editSchoolId').value;
        const schoolName = document.getElementById('editSchoolName').value.trim();
        const emisCode = document.getElementById('editEmisCode').value.trim();
        
        let hasError = false;
        
        // Validate required fields
        if (!schoolName) {
            document.getElementById('editSchoolName').classList.add('error');
            document.getElementById('editSchoolName').insertAdjacentHTML('afterend', '<div class="error-message">School name is required</div>');
            hasError = true;
        }
        
        if (!emisCode) {
            document.getElementById('editEmisCode').classList.add('error');
            document.getElementById('editEmisCode').insertAdjacentHTML('afterend', '<div class="error-message">EMIS code is required</div>');
            hasError = true;
        }
        
        if (hasError) {
            showToast('Please fill all required fields', 'error');
            return;
        }
        
        const payload = {
            school_id: schoolId,
            name: schoolName,
            code: emisCode,
            telephone_no: document.getElementById('editPhone').value.trim(),
            email_address: document.getElementById('editEmail').value.trim(),
            postal_address: document.getElementById('editAddress').value.trim()
        };
        
        fetch(`${BASE_URL}/schoolmanagement/update-school-details`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('School details updated successfully');
                closeEditSchoolInfoModal();
                
                // Update local data
                const schoolIndex = consolidateSchools.findIndex(s => s.school_id == schoolId);
                if (schoolIndex !== -1) {
                    consolidateSchools[schoolIndex].school_name = schoolName;
                    consolidateSchools[schoolIndex].emis = emisCode;
                    renderConsolidateSchools();
                }
            } else {
                showToast(data.message || 'Failed to update school', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error. Try again.', 'error');
        });
    }
    
    // Consolidate Schools
    function consolidateSchoolsFunc() {
        if (consolidateSchools.length < 2) {
            showToast('Please select at least 2 schools to consolidate', 'error');
            return;
        }
        
        const motherSchool = consolidateSchools[0];
        const childSchoolIds = consolidateSchools.slice(1).map(s => s.school_id);
        
        if (!confirm(`Are you sure you want to consolidate ${childSchoolIds.length} school(s) into "${motherSchool.school_name}"?\n\nAll beneficiaries from the selected schools will be transferred to this Final Active School.`)) {
            return;
        }
        
        const payload = {
            mother_school_id: motherSchool.school_id,
            child_school_ids: childSchoolIds
        };
        
        const btn = document.getElementById('consolidateBtn');
        const originalText = btn.textContent;
        btn.textContent = 'Consolidating...';
        btn.disabled = true;
        
        fetch(`${BASE_URL}/schoolmanagement/consolidate-schools`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            btn.textContent = originalText;
            btn.disabled = false;
            
            if (data.success) {
                showToast(`Successfully consolidated ${childSchoolIds.length} school(s)`);
                
                // Clear consolidation and reload
                consolidateSchools = [];
                renderConsolidateSchools();
                loadConsolidationSchools();
            } else {
                showToast(data.message || 'Consolidation failed', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            btn.textContent = originalText;
            btn.disabled = false;
            showToast('Network error. Try again.', 'error');
        });
    }
    
    // Close modal on outside click
    document.getElementById('editSchoolModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditSchoolInfoModal();
        }
    });
</script>
