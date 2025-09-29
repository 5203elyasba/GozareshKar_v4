document.addEventListener('DOMContentLoaded', function() {
    const dayTypeSelect = document.getElementById('day_type');
    const timeIntervalsSection = document.getElementById('time-intervals-section');
    const addIntervalBtn = document.getElementById('add-interval');
    const timeContainer = document.getElementById('time-intervals-container');
    const fetchDateBtn = document.getElementById('fetch-date-btn');

    // Function to generate the HTML for a new time entry row
    function createTimeRow() {
        const newRow = document.createElement('div');
        newRow.classList.add('row', 'g-2', 'mb-2', 'align-items-center', 'time-interval-row');

        let hourOptions = '<option value="">ساعت</option>';
        for (let h = 0; h <= 23; h++) {
            const h_padded = String(h).padStart(2, '0');
            hourOptions += `<option value="${h_padded}">${h_padded}</option>`;
        }

        let minuteOptions = '<option value="">دقیقه</option>';
        for (let m = 0; m <= 59; m++) {
            const m_padded = String(m).padStart(2, '0');
            minuteOptions += `<option value="${m_padded}">${m_padded}</option>`;
        }

        newRow.innerHTML = `
            <div class="col-5">
                <label class="form-label small">ساعت ورود</label>
                <div class="input-group">
                    <select name="start_hour[]" class="form-select">${hourOptions}</select>
                    <select name="start_minute[]" class="form-select">${minuteOptions}</select>
                </div>
            </div>
            <div class="col-5">
                <label class="form-label small">ساعت خروج</label>
                <div class="input-group">
                    <select name="end_hour[]" class="form-select">${hourOptions}</select>
                    <select name="end_minute[]" class="form-select">${minuteOptions}</select>
                </div>
            </div>
            <div class="col-auto d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-danger remove-interval">-</button>
            </div>
        `;
        return newRow;
    }

    // Function to show or hide the time entry section based on the selected day type
    function toggleTimeSection() {
        if (!dayTypeSelect || !timeIntervalsSection) return;
        const selectedType = dayTypeSelect.value;
        const workDayTypes = ['work', 'friday_work', 'official_holiday_work'];

        if (workDayTypes.includes(selectedType)) {
            timeIntervalsSection.style.display = 'block';
        } else {
            timeIntervalsSection.style.display = 'none';
        }
    }

    // Function to manage the visibility of the "remove" buttons
    function updateRemoveButtons() {
        if (!timeContainer) return;
        const rows = timeContainer.querySelectorAll('.time-interval-row');
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-interval');
            if (removeBtn) {
                removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
            }
        });
    }

    // --- Event Listeners ---
    if (dayTypeSelect) {
        dayTypeSelect.addEventListener('change', toggleTimeSection);
    }

    if (addIntervalBtn) {
        addIntervalBtn.addEventListener('click', () => {
            timeContainer.appendChild(createTimeRow());
            updateRemoveButtons();
        });
    }

    if (timeContainer) {
        timeContainer.addEventListener('click', e => {
            if (e.target && e.target.classList.contains('remove-interval')) {
                e.target.closest('.time-interval-row').remove();
                updateRemoveButtons();
            }
        });
    }

    if (fetchDateBtn) {
        fetchDateBtn.addEventListener('click', () => {
            const year = document.querySelector('input[name="log_year"]').value;
            const month = String(document.querySelector('select[name="log_month"]').value).padStart(2, '0');
            const day = String(document.querySelector('input[name="log_day"]').value).padStart(2, '0');
            window.location.href = `index.php?date=${year}/${month}/${day}`;
        });
    }

    // --- Initial setup on page load ---
    toggleTimeSection();
    updateRemoveButtons();
});