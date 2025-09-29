document.addEventListener('DOMContentLoaded', function() {
    const dayTypeSelect = document.getElementById('day_type');
    const timeIntervalsSection = document.getElementById('time-intervals-section');
    const addIntervalBtn = document.getElementById('add-interval');
    const timeContainer = document.getElementById('time-intervals-container');
    const fetchDateBtn = document.getElementById('fetch-date-btn');

    // Function to show or hide the time entry section based on the selected day type
    function toggleTimeSection() {
        if (!dayTypeSelect || !timeIntervalsSection) return;
        const selectedType = dayTypeSelect.value;
        // Show for 'work' and 'friday_work', hide for others ('leave', 'official_holiday')
        if (selectedType === 'work' || selectedType === 'friday_work') {
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
                // Show remove button only if there is more than one row
                removeBtn.style.display = (rows.length > 1) ? 'inline-block' : 'none';
            }
        });
    }

    // Event listener for the day type dropdown
    if (dayTypeSelect) {
        dayTypeSelect.addEventListener('change', toggleTimeSection);
    }

    // Event listener for the "add interval" button
    if (addIntervalBtn) {
        addIntervalBtn.addEventListener('click', () => {
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'g-2', 'mb-2', 'align-items-center', 'time-interval-row');
            newRow.innerHTML = `
                <div class="col"><label class="form-label small">ساعت ورود</label><input type="text" class="form-control" name="start_time[]" pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$" placeholder="مثلا: 09:00"></div>
                <div class="col"><label class="form-label small">ساعت خروج</label><input type="text" class="form-control" name="end_time[]" pattern="^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$" placeholder="مثلا: 17:30"></div>
                <div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-sm btn-danger remove-interval">-</button></div>
            `;
            timeContainer.appendChild(newRow);
            updateRemoveButtons();
        });
    }

    // Event listener for removing an interval (delegated to the container)
    if (timeContainer) {
        timeContainer.addEventListener('click', e => {
            if (e.target && e.target.classList.contains('remove-interval')) {
                e.target.closest('.time-interval-row').remove();
                updateRemoveButtons();
            }
        });
    }

    // Event listener for the "Fetch Date" button
    if (fetchDateBtn) {
        fetchDateBtn.addEventListener('click', () => {
            const yearInput = document.querySelector('input[name="log_year"]');
            const monthInput = document.querySelector('select[name="log_month"]');
            const dayInput = document.querySelector('input[name="log_day"]');

            if (yearInput && monthInput && dayInput) {
                const year = yearInput.value;
                const month = String(monthInput.value).padStart(2, '0');
                const day = String(dayInput.value).padStart(2, '0');
                // Redirect to the same page with the new date as a query parameter
                window.location.href = 'index.php?date=' + year + '/' + month + '/' + day;
            }
        });
    }

    // Initial setup on page load
    toggleTimeSection();
    updateRemoveButtons();
});