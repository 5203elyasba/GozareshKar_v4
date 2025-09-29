document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('log-form');
    const timeContainer = document.getElementById('time-intervals-container');
    const addIntervalBtn = document.getElementById('add-interval');
    const dayTypeSelect = document.getElementById('day_type');
    const dayTypeStatus = document.getElementById('day-type-status');
    const fetchDateBtn = document.getElementById('fetch-date-btn');

    const JALALI_DATE = form.dataset.jalaliDate;

    // --- Helper Functions ---
    function setStatus(element, status) { // status can be 'saving', 'success', 'error'
        if (!element) return;
        if (status === 'saving') {
            element.innerHTML = '...';
            element.className = 'status-icon saving';
        } else if (status === 'success') {
            element.innerHTML = '✔️';
            element.className = 'status-icon success';
            setTimeout(() => { if(element.innerHTML === '✔️') element.innerHTML = ''; }, 2000);
        } else if (status === 'error') {
            element.innerHTML = '❌';
            element.className = 'status-icon error';
        }
    }

    function createTimeRow() {
        // ... (same as before)
    }

    function toggleTimeSection() {
        const timeSection = document.getElementById('time-intervals-section');
        const workDayTypes = ['work', 'friday_work', 'official_holiday_work'];
        timeSection.style.display = workDayTypes.includes(dayTypeSelect.value) ? 'block' : 'none';
    }

    function updateRemoveButtons() {
        const rows = timeContainer.querySelectorAll('.time-interval-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-interval');
            if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        });
    }

    // --- Event Handlers ---

    // 1. Handle Time Entry Changes (Event Delegation)
    timeContainer.addEventListener('change', function(e) {
        if (!e.target.classList.contains('time-select')) return;

        const row = e.target.closest('.time-interval-row');
        const logId = row.dataset.logId;
        const statusIcon = row.querySelector('.status-icon');

        const startHour = row.querySelector('[name="start_hour"]').value;
        const startMinute = row.querySelector('[name="start_minute"]').value;
        const endHour = row.querySelector('[name="end_hour"]').value;
        const endMinute = row.querySelector('[name="end_minute"]').value;

        // Only save if a full time pair is selected
        const type = e.target.dataset.type;
        const value = e.target.value;

        let timeToSave = null;
        let typeToSave = null;

        if (type === 'start' && startHour && startMinute) {
             timeToSave = `${startHour}:${startMinute}`;
             typeToSave = 'start';
        } else if (type === 'end' && endHour && endMinute) {
            timeToSave = `${endHour}:${endMinute}`;
            typeToSave = 'end';
        }

        // If one part of the time is complete, save it
        if (timeToSave && typeToSave) {
            setStatus(statusIcon, 'saving');
            fetch('save_time_entry_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    log_id: logId,
                    log_date_jalali: JALALI_DATE,
                    time: timeToSave,
                    type: typeToSave
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setStatus(statusIcon, 'success');
                    if (data.log_id && !logId) {
                        // This was a new entry, update its log_id in the DOM
                        row.dataset.logId = data.log_id;
                        // Also update the data-log-id for all select elements in this row
                        row.querySelectorAll('.time-select').forEach(sel => sel.dataset.logId = data.log_id);
                    }
                } else {
                    setStatus(statusIcon, 'error');
                    alert(data.message || 'خطا در ذخیره سازی');
                }
            })
            .catch(() => setStatus(statusIcon, 'error'));
        }
    });

    // 2. Handle Day Type Change
    dayTypeSelect.addEventListener('change', function() {
        const newDayType = this.value;
        setStatus(dayTypeStatus, 'saving');

        fetch('update_day_type_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                log_date_jalali: JALALI_DATE,
                day_type: newDayType
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                setStatus(dayTypeStatus, 'success');
                toggleTimeSection();
                // If we change to a non-working day, we might want to visually remove the rows
                if (!['work', 'friday_work', 'official_holiday_work'].includes(newDayType)) {
                    timeContainer.innerHTML = ''; // Clear existing rows
                }
            } else {
                setStatus(dayTypeStatus, 'error');
                alert(data.message || 'خطا در بروزرسانی نوع روز');
            }
        })
        .catch(() => setStatus(dayTypeStatus, 'error'));
    });

    // 3. Add new interval
    addIntervalBtn.addEventListener('click', function() {
        // This part needs to be updated to use the PHP helper function's output via JS
        // For simplicity, we'll just reload the page after adding a dummy row.
        // A better implementation would be a JS template.
        const newRow = document.createElement('div');
        newRow.className = 'row g-3 mb-2 align-items-center time-interval-row';
        newRow.dataset.logId = '';
        newRow.innerHTML = `
            <div class="col-12 col-md-5"><label class="form-label small d-md-none">ورود</label><div class="input-group"><select name="start_hour" class="form-select time-select" data-type="start" data-log-id=""><option value="">-</option>${Array.from({length: 24}, (_, i) => `<option value="${String(i).padStart(2, '0')}">${String(i).padStart(2, '0')}</option>`).join('')}</select><select name="start_minute" class="form-select time-select" data-type="start" data-log-id=""><option value="">-</option>${Array.from({length: 12}, (_, i) => `<option value="${String(i*5).padStart(2, '0')}">${String(i*5).padStart(2, '0')}</option>`).join('')}</select></div></div>
            <div class="col-12 col-md-5"><label class="form-label small d-md-none">خروج</label><div class="input-group"><select name="end_hour" class="form-select time-select" data-type="end" data-log-id=""><option value="">-</option>${Array.from({length: 24}, (_, i) => `<option value="${String(i).padStart(2, '0')}">${String(i).padStart(2, '0')}</option>`).join('')}</select><select name="end_minute" class="form-select time-select" data-type="end" data-log-id=""><option value="">-</option>${Array.from({length: 12}, (_, i) => `<option value="${String(i*5).padStart(2, '0')}">${String(i*5).padStart(2, '0')}</option>`).join('')}</select></div></div>
            <div class="col-12 col-md-2 d-flex justify-content-end align-items-center"><span class="status-icon me-2"></span><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
        `;
        timeContainer.appendChild(newRow);
        updateRemoveButtons();
    });

    // 4. Remove interval
    timeContainer.addEventListener('click', function(e) {
        if (!e.target.classList.contains('remove-interval')) return;
        const row = e.target.closest('.time-interval-row');
        const logId = row.dataset.logId;

        if (logId && confirm('آیا از حذف این بازه زمانی مطمئن هستید؟')) {
            // It's an existing entry, delete from DB
             fetch('delete_log_ajax.php', { // Assuming this file exists from original codebase
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: logId })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    row.remove();
                    updateRemoveButtons();
                } else {
                    alert('خطا در حذف از دیتابیس.');
                }
            })
            .catch(() => alert('خطای شبکه.'));
        } else if (!logId) {
            // It's a new, unsaved row, just remove it from DOM
            row.remove();
            updateRemoveButtons();
        }
    });

    // 5. Fetch Date button
    fetchDateBtn.addEventListener('click', () => {
        const year = document.getElementById('log_year').value;
        const month = String(document.getElementById('log_month').value).padStart(2, '0');
        const day = String(document.getElementById('log_day').value).padStart(2, '0');
        window.location.href = `index.php?date=${year}/${month}/${day}`;
    });

    // --- Initial page setup ---
    toggleTimeSection();
    updateRemoveButtons();
});