document.addEventListener('DOMContentLoaded', function() {

    // --- Time Input Masking ---
    function applyTimeMask(element) {
        if (!element) return;
        try { IMask(element, { mask: 'HH:MM', blocks: { HH: { mask: IMask.MaskedRange, from: 0, to: 23 }, MM: { mask: IMask.MaskedRange, from: 0, to: 59 } }, lazy: false });
        } catch (e) { console.error("IMask could not be initialized.", e); }
    }
    document.querySelectorAll('.time-input').forEach(applyTimeMask);

    // --- Date Input & Validation Logic ---
    const dayInput = document.querySelector('input[name="log_day"]');
    const monthInput = document.querySelector('select[name="log_month"]');
    const yearInput = document.querySelector('input[name="log_year"]');

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
                if (input.name.includes('month')) {
                    max = 12;
                } else if (input.name.includes('year')) {
                    min = 1390; max = 1500;
                }

                let newVal = currentVal + amount;

                // Rollover logic
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
    const addWorkBtn = document.getElementById('add-interval');
    const workContainer = document.getElementById('time-intervals-container');

    function updateRemoveButtons() {
        const rows = workContainer.querySelectorAll('.time-interval-row');
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-interval');
            if (removeBtn) {
                removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
            }
        });
    }

    function checkIntervalLimit() {
        if (!workContainer || !addWorkBtn) return;
        const rowCount = workContainer.querySelectorAll('.time-interval-row').length;
        addWorkBtn.disabled = rowCount >= 3;
    }

    if (addWorkBtn && workContainer) {
        addWorkBtn.addEventListener('click', () => {
            if (workContainer.querySelectorAll('.time-interval-row').length >= 3) return;
            const newInterval = document.createElement('div');
            newInterval.classList.add('row', 'g-2', 'mb-2', 'align-items-center', 'time-interval-row');
            newInterval.innerHTML = `
                <input type="hidden" name="log_id[]" value="">
                <div class="col"><label class="form-label">ساعت ورود</label><input type="text" class="form-control time-input" name="start_time[]"></div>
                <div class="col-auto"><button type="button" class="btn btn-outline-primary btn-sm btn-log-now" data-type="start">ثبت</button></div>
                <div class="col"><label class="form-label">ساعت خروج</label><input type="text" class="form-control time-input" name="end_time[]"></div>
                <div class="col-auto"><button type="button" class="btn btn-outline-primary btn-sm btn-log-now" data-type="end">ثبت</button></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
            `;
            workContainer.appendChild(newInterval);
            newInterval.querySelectorAll('.time-input').forEach(applyTimeMask);
            updateRemoveButtons();
            checkIntervalLimit();
        });
    }

    workContainer.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('remove-interval')) {
            const row = e.target.closest('.time-interval-row');
            const logIdInput = row.querySelector('input[name="log_id[]"]');

            // If the row was never saved to the DB, just remove it from the DOM
            if (!logIdInput || !logIdInput.value) {
                row.remove();
                updateRemoveButtons();
                checkIntervalLimit();
                return;
            }

            // If it exists in the DB, we need to delete it via AJAX
            if (confirm('آیا از حذف این بازه زمانی مطمئن هستید؟')) {
                fetch('delete_time_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ log_id: logIdInput.value })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        updateRemoveButtons();
                        checkIntervalLimit();
                    } else {
                        alert('خطا در حذف: ' + data.message);
                    }
                })
                .catch(err => alert('خطای شبکه.'));
            }
        }
    });
    if (workContainer) {
        updateRemoveButtons();
        checkIntervalLimit();
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
    const mainForm = document.getElementById('log-form');
    const submitBtn = mainForm ? mainForm.querySelector('button[type="submit"]') : null;

    function validateTimeIntervals() {
        if (!workContainer) return;
        const rows = workContainer.querySelectorAll('.time-interval-row');
        let isValid = true;
        let intervals = [];

        // First pass: collect and validate individual rows
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

        // Second pass: check for overlaps
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
        workContainer.addEventListener('change', (e) => {
            if (e.target && e.target.classList.contains('time-input')) {
                validateTimeIntervals();
            }
        });
        validateTimeIntervals(); // Initial validation on page load
    }


    // --- "Log Now" AJAX Logic ---
    workContainer.addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn-log-now')) return;

        const button = e.target;
        const row = button.closest('.time-interval-row');
        const logIdInput = row.querySelector('input[name="log_id[]"]');
        const type = button.dataset.type;
        const timeInput = (type === 'start') ? row.querySelector('input[name="start_time[]"]') : row.querySelector('input[name="end_time[]"]');

        if (!timeInput.value.match(/^\d{2}:\d{2}$/)) { alert('فرمت زمان باید HH:MM باشد.'); return; }

        const jalaliDate = `${yearInput.value}/${String(monthInput.value).padStart(2,'0')}/${String(dayInput.value).padStart(2,'0')}`;

        button.disabled = true;
        button.textContent = '...';

        fetch('save_time_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                log_id: logIdInput.value,
                log_date: jalaliDate,
                time: timeInput.value,
                type: type
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                button.textContent = 'ثبت شد';
                button.classList.replace('btn-outline-primary', 'btn-success');
                timeInput.readOnly = true;
                if (data.log_id && !logIdInput.value) {
                    logIdInput.value = data.log_id;
                }
            } else {
                alert('خطا: ' + data.message);
                button.disabled = false;
                button.textContent = 'ثبت';
            }
        })
        .catch(err => {
            alert('خطای شبکه.');
            button.disabled = false;
            button.textContent = 'ثبت';
        });
    });
});
