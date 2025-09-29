document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('log-form');
    const timeContainer = document.getElementById('time-intervals-container');
    const addIntervalBtn = document.getElementById('add-interval');
    const dayTypeSelect = document.getElementById('day_type');
    const dayTypeStatus = document.getElementById('day-type-status');
    const fetchDateBtn = document.getElementById('fetch-date-btn');

    const JALALI_DATE = form.dataset.jalaliDate;

    // --- Helper Functions ---
    function setStatus(element, status, message = '') {
        if (!element) return;
        element.classList.remove('saving', 'success', 'error');
        if (status === 'saving') {
            element.innerHTML = '...';
            element.classList.add('saving');
        } else if (status === 'success') {
            element.innerHTML = '✔️';
            element.classList.add('success');
            setTimeout(() => { if (element.innerHTML === '✔️') element.innerHTML = ''; }, 2000);
        } else if (status === 'error') {
            element.innerHTML = '❌';
            element.classList.add('error');
            if (message) alert(message);
        }
    }

    function generateSelectOptions(max, step = 1) {
        let options = '<option value="" selected>-</option>';
        for (let i = 0; i < max; i += step) {
            const padded = String(i).padStart(2, '0');
            options += `<option value="${padded}">${padded}</option>`;
        }
        return options;
    }

    function createTimeRowHTML() {
        const hourOptions = generateSelectOptions(24);
        const minuteOptions = generateSelectOptions(60, 1); // 0-59, step 1
        return `
            <div class="col-12 col-md-5">
                <label class="form-label small d-md-none">ورود</label>
                <div class="input-group">
                    <select name="start_hour" class="form-select time-select" data-type="start">${hourOptions}</select>
                    <span class="input-group-text">:</span>
                    <select name="start_minute" class="form-select time-select" data-type="start">${minuteOptions}</select>
                </div>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small d-md-none">خروج</label>
                <div class="input-group">
                    <select name="end_hour" class="form-select time-select" data-type="end">${hourOptions}</select>
                    <span class="input-group-text">:</span>
                    <select name="end_minute" class="form-select time-select" data-type="end">${minuteOptions}</select>
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex justify-content-end align-items-center">
                <span class="status-icon me-2"></span>
                <button type="button" class="btn btn-sm btn-danger remove-interval">-</button>
            </div>
        `;
    }

    function toggleTimeSection() {
        const timeSection = document.getElementById('time-intervals-section');
        const workDayTypes = ['work', 'friday_work', 'official_holiday_work'];
        timeSection.style.display = workDayTypes.includes(dayTypeSelect.value) ? 'block' : 'none';
    }

    function updateRemoveButtons() {
        if (!timeContainer) return;
        const rows = timeContainer.querySelectorAll('.time-interval-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-interval');
            if (removeBtn) removeBtn.style.display = 'inline-block';
        });
        if (rows.length <= 1) {
            const firstRemoveBtn = timeContainer.querySelector('.remove-interval');
            if (firstRemoveBtn) firstRemoveBtn.style.display = 'none';
        }
    }

    // --- Event Handlers ---

    timeContainer.addEventListener('change', function(e) {
        if (!e.target.classList.contains('time-select')) return;

        const row = e.target.closest('.time-interval-row');
        const statusIcon = row.querySelector('.status-icon');

        const startHour = row.querySelector('select[name="start_hour"]').value;
        const startMinute = row.querySelector('select[name="start_minute"]').value;
        const endHour = row.querySelector('select[name="end_hour"]').value;
        const endMinute = row.querySelector('select[name="end_minute"]').value;

        // **CRITICAL CHANGE**: Only trigger save when BOTH start and end times are fully selected.
        if (startHour && startMinute && endHour && endMinute) {
            const startTime = `${startHour}:${startMinute}`;
            const endTime = `${endHour}:${endMinute}`;

            // Basic validation
            if (new Date(`1970/01/01 ${endTime}`) <= new Date(`1970/01/01 ${startTime}`)) {
                setStatus(statusIcon, 'error', 'ساعت خروج باید بعد از ساعت ورود باشد.');
                return;
            }

            setStatus(statusIcon, 'saving');
            fetch('save_time_entry_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    log_id: row.dataset.logId,
                    log_date_jalali: JALALI_DATE,
                    start_time: startTime,
                    end_time: endTime
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setStatus(statusIcon, 'success');
                    if (data.log_id && !row.dataset.logId) {
                        row.dataset.logId = data.log_id;
                    }
                } else {
                    setStatus(statusIcon, 'error', data.message);
                }
            })
            .catch(() => setStatus(statusIcon, 'error', 'خطای شبکه'));
        }
    });

    dayTypeSelect.addEventListener('change', function() {
        setStatus(dayTypeStatus, 'saving');
        fetch('update_day_type_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ log_date_jalali: JALALI_DATE, day_type: this.value })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                setStatus(dayTypeStatus, 'success');
                toggleTimeSection();
                window.location.reload(); // Reload to get fresh state from server
            } else {
                setStatus(dayTypeStatus, 'error', data.message);
            }
        })
        .catch(() => setStatus(dayTypeStatus, 'error', 'خطای شبکه'));
    });

    addIntervalBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'row g-2 mb-3 align-items-center time-interval-row';
        newRow.dataset.logId = '';
        newRow.innerHTML = createTimeRowHTML();
        timeContainer.appendChild(newRow);
        updateRemoveButtons();
    });

    timeContainer.addEventListener('click', function(e) {
        if (!e.target.classList.contains('remove-interval')) return;
        const row = e.target.closest('.time-interval-row');
        const logId = row.dataset.logId;

        if (logId && confirm('آیا از حذف این بازه زمانی مطمئن هستید؟')) {
            fetch('delete_log_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: logId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    updateRemoveButtons();
                } else {
                    alert(data.message || 'خطا در حذف از دیتابیس.');
                }
            })
            .catch(() => alert('خطای شبکه.'));
        } else if (!logId) {
            row.remove();
            updateRemoveButtons();
        }
    });

    fetchDateBtn.addEventListener('click', () => {
        const year = document.getElementById('log_year').value;
        const month = String(document.getElementById('log_month').value).padStart(2, '0');
        const day = String(document.getElementById('log_day').value).padStart(2, '0');
        window.location.href = `index.php?date=${year}/${month}/${day}`;
    });

    // --- Initial page setup ---
    toggleTimeSection();
    updateRemoveButtons();
    if (timeContainer.children.length === 0 && ['work', 'friday_work', 'official_holiday_work'].includes(dayTypeSelect.value)) {
        addIntervalBtn.click();
    }
});