document.addEventListener('DOMContentLoaded', function() {

    const dayInput = document.querySelector('input[name="log_day"]');
    const monthInput = document.querySelector('select[name="log_month"]');
    const yearInput = document.querySelector('input[name="log_year"]');
    const workContainer = document.getElementById('time-intervals-container');
    const addWorkBtn = document.getElementById('add-interval');
    const mainForm = document.getElementById('log-form');
    const overtimeCheckbox = document.getElementById('is_overtime');
    const submitBtn = mainForm ? mainForm.querySelector('button[type="submit"]') : null;

    // --- Date Input Number Spinners ---
    function setupCustomNumberInputs() {
        document.querySelectorAll('.custom-number-input').forEach(container => {
            const input = container.querySelector('input[type="text"]');
            if (!input) return;
            const decBtn = container.querySelector('.btn-decrement');
            const incBtn = container.querySelector('.btn-increment');
            if (!decBtn || !incBtn) return;

            const updateValue = (amount) => {
                let currentVal = parseInt(input.value, 10);
                if (isNaN(currentVal)) currentVal = 1;
                let min = 1, max = 31;
                if (input.name.includes('year')) { min = 1390; max = 1500; }
                let newVal = currentVal + amount;
                if (newVal > max) newVal = min;
                if (newVal < min) newVal = max;
                input.value = newVal;
            };
            decBtn.addEventListener('click', () => updateValue(-1));
            incBtn.addEventListener('click', () => updateValue(1));
        });
    }
    if (dayInput) setupCustomNumberInputs();

    // --- Add/Remove Work Intervals ---
    function updateRemoveButtons() {
        if (!workContainer) return;
        const rows = workContainer.querySelectorAll('.time-interval-row');
        rows.forEach((row) => {
            const removeBtn = row.querySelector('.remove-interval');
            if (removeBtn) removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
        });
    }

    if (addWorkBtn && workContainer) {
        addWorkBtn.addEventListener('click', () => {
            const newInterval = document.createElement('div');
            newInterval.classList.add('row', 'g-2', 'mb-2', 'align-items-center', 'time-interval-row');
            newInterval.innerHTML = `
                <input type="hidden" name="log_id[]" value="">
                 <div class="col input-group">
                    <label class="form-label w-100">ساعت ورود</label>
                    <input type="text" class="form-control time-input" name="start_time[]" placeholder="--:--" readonly>
                    <span class="input-group-text status-icon"></span>
                </div>
                <div class="col input-group">
                    <label class="form-label w-100">ساعت خروج</label>
                    <input type="text" class="form-control time-input" name="end_time[]" placeholder="--:--" readonly>
                    <span class="input-group-text status-icon"></span>
                </div>
                <div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
            `;
            workContainer.appendChild(newInterval);
            // The new init function will need to be called here.
            // initNewTimePicker(newInterval);
            updateRemoveButtons();
        });
    }

    if (workContainer) {
        workContainer.addEventListener('click', (e) => {
            if (e.target && e.target.classList.contains('remove-interval')) {
                const row = e.target.closest('.time-interval-row');
                const logIdInput = row.querySelector('input[name="log_id[]"]');
                const logId = logIdInput.value;

                // If logId is empty, it's a new, unsaved row. Just remove it.
                if (!logId) {
                    row.remove();
                    validateTimeIntervals();
                    updateRemoveButtons();
                    return;
                }

                // If logId exists, we need to delete it from the database.
                if (confirm('آیا از حذف این بازه زمانی مطمئن هستید؟')) {
                    fetch('delete_log_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ log_id: logId })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            row.remove();
                            validateTimeIntervals();
                            updateRemoveButtons();
                        } else {
                            alert('خطا در حذف: ' + data.message);
                        }
                    })
                    .catch(err => {
                        alert('خطای شبکه در هنگام حذف.');
                    });
                }
            }
        });
        updateRemoveButtons();
    }

    // --- "Fetch Date" Button ---
    const fetchDateBtn = document.getElementById('fetch-date-btn');
    if (fetchDateBtn) {
        fetchDateBtn.addEventListener('click', () => {
            if (!dayInput || !monthInput || !yearInput) return;
            const dateStr = `${yearInput.value}/${String(monthInput.value).padStart(2, '0')}/${String(dayInput.value).padStart(2, '0')}`;
            window.location.href = 'index.php?date=' + dateStr;
        });
    }

    // --- Live Interval Validation ---
    function validateTimeIntervals() {
        if (!workContainer) return;
        const rows = workContainer.querySelectorAll('.time-interval-row');
        let isValid = true;
        let intervals = [];

        rows.forEach(row => {
            const startInput = row.querySelector('input[name="start_time[]"]');
            const endInput = row.querySelector('input[name="end_time[]"]');
            row.classList.remove('is-invalid');

            if (startInput.value && endInput.value) {
                if (endInput.value <= startInput.value) {
                    row.classList.add('is-invalid');
                    isValid = false;
                } else {
                    intervals.push({ start: startInput.value, end: endInput.value, row: row });
                }
            }
        });

        if (isValid && intervals.length > 1) {
            intervals.sort((a, b) => a.start.localeCompare(b.start));
            for (let i = 1; i < intervals.length; i++) {
                if (intervals[i].start < intervals[i-1].end) {
                    intervals[i].row.classList.add('is-invalid');
                    intervals[i-1].row.classList.add('is-invalid');
                    isValid = false;
                }
            }
        }

        if (submitBtn) {
            submitBtn.disabled = !isValid;
            submitBtn.title = isValid ? '' : 'لطفا خطاهای موجود در بازه های زمانی را برطرف کنید.';
        }
    }

    if (workContainer) {
        validateTimeIntervals();
    }

    // --- Overtime Checkbox AJAX ---
    if (overtimeCheckbox) {
        overtimeCheckbox.addEventListener('change', function() {
            const is_checked = this.checked;
            const jalaliDate = `${yearInput.value}/${String(monthInput.value).padStart(2,'0')}/${String(dayInput.value).padStart(2,'0')}`;

            fetch('update_day_property.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    log_date: jalaliDate,
                    is_checked: is_checked
                })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    // Revert the checkbox and show an error
                    this.checked = !is_checked;
                    alert('خطا در بروزرسانی وضعیت اضافه‌کار: ' + data.message);
                }
                // On success, do nothing - the UI state is already correct.
            })
            .catch(err => {
                this.checked = !is_checked;
                alert('خطای شبکه در هنگام بروزرسانی وضعیت اضافه‌کار.');
            });
        });
    }

    // --- iOS Time Picker Logic ---
    const modal = document.getElementById('time-picker-modal');
    const closeBtn = document.getElementById('time-picker-close');
    const saveBtn = document.getElementById('time-picker-save');
    let activeInput = null;
    let hourSelector = null;
    let minuteSelector = null;

    function createTimeSource(max, pad = 2) {
        let source = [];
        for (let i = 0; i < max; i++) {
            const val = String(i).padStart(pad, '0');
            source.push({ value: i, text: val });
        }
        return source;
    }

    const hourSource = createTimeSource(24);
    const minuteSource = createTimeSource(60);

    function initTimePicker() {
        if (hourSelector) hourSelector.destroy();
        if (minuteSelector) minuteSelector.destroy();

        hourSelector = new IosSelector({
            el: '#time-picker-hour',
            type: 'infinite',
            source: hourSource,
            count: 20,
        });

        minuteSelector = new IosSelector({
            el: '#time-picker-minute',
            type: 'infinite',
            source: minuteSource,
            count: 20,
        });
    }

    function showPicker(inputElement) {
        activeInput = inputElement;
        const currentTime = activeInput.value;
        let [currentHour, currentMinute] = [new Date().getHours(), new Date().getMinutes()];

        if (currentTime && currentTime.includes(':')) {
            const parts = currentTime.split(':');
            currentHour = parseInt(parts[0], 10);
            currentMinute = parseInt(parts[1], 10);
        }

        initTimePicker();

        setTimeout(() => {
            hourSelector.select(currentHour);
            minuteSelector.select(currentMinute);
            modal.classList.add('show');
        }, 10);
    }

    function hidePicker() {
        modal.classList.remove('show');
        activeInput = null;
    }

    function saveTime() {
        if (!activeInput) return;

        const hour = String(hourSelector.value).padStart(2, '0');
        const minute = String(minuteSelector.value).padStart(2, '0');
        const newTime = `${hour}:${minute}`;

        activeInput.value = newTime;

        // --- Trigger AJAX Save ---
        const row = activeInput.closest('.time-interval-row');
        const statusIcon = activeInput.parentElement.querySelector('.status-icon');
        const logIdInput = row.querySelector('input[name="log_id[]"]');
        const type = activeInput.name.includes('start') ? 'start' : 'end';
        const jalaliDate = `${yearInput.value}/${String(monthInput.value).padStart(2,'0')}/${String(dayInput.value).padStart(2,'0')}`;

        statusIcon.innerHTML = '...';

        fetch('save_time_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                log_id: logIdInput.value,
                log_date: jalaliDate,
                time: newTime,
                type: type
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusIcon.innerHTML = '✔️';
                if (data.log_id && !logIdInput.value) {
                    logIdInput.value = data.log_id;
                }
            } else {
                statusIcon.innerHTML = '❌';
                alert('خطا در ذخیره: ' + data.message);
            }
            validateTimeIntervals();
        })
        .catch(err => {
            statusIcon.innerHTML = '❌';
            alert('خطای شبکه.');
            validateTimeIntervals();
        });

        hidePicker();
    }

    // Event listeners for picker UI
    if (modal) {
        document.body.addEventListener('click', function(e) {
            if (e.target.classList.contains('time-input')) {
                showPicker(e.target);
            }
        });

        closeBtn.addEventListener('click', hidePicker);
        saveBtn.addEventListener('click', saveTime);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hidePicker();
            }
        });
    }

    // Call validation on page load
    validateTimeIntervals();
});
