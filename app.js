const API = 'api/runs.php';

const state = {
    year: null,
    month: null,
    runs: {},   // keyed by 'YYYY-MM-DD'
};

// --- Init ---

function init() {
    const now = new Date();
    state.year  = now.getFullYear();
    state.month = now.getMonth() + 1;

    document.getElementById('prev-month').addEventListener('click', prevMonth);
    document.getElementById('next-month').addEventListener('click', nextMonth);
    document.getElementById('cancel-btn').addEventListener('click', closeModal);
    document.getElementById('delete-btn').addEventListener('click', deleteRun);
    document.getElementById('run-form').addEventListener('submit', saveRun);
    document.getElementById('modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    loadMonth();
}

// --- Month navigation ---

function prevMonth() {
    state.month--;
    if (state.month < 1) { state.month = 12; state.year--; }
    loadMonth();
}

function nextMonth() {
    state.month++;
    if (state.month > 12) { state.month = 1; state.year++; }
    loadMonth();
}

// --- Data loading ---

async function loadMonth() {
    const res  = await fetch(`${API}?year=${state.year}&month=${state.month}`);
    const rows = await res.json();

    state.runs = {};
    for (const row of rows) {
        state.runs[row.run_date] = row;
    }

    renderCalendar();
}

// --- Calendar rendering ---

function renderCalendar() {
    const { year, month } = state;

    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    document.getElementById('month-label').textContent = `${monthNames[month - 1]} ${year}`;

    const grid      = document.getElementById('calendar-grid');
    const firstDay  = new Date(year, month - 1, 1).getDay();   // 0=Sun
    const daysInMo  = new Date(year, month, 0).getDate();
    const today     = new Date();
    const isThisMonth = today.getFullYear() === year && today.getMonth() + 1 === month;

    grid.innerHTML = '';

    // Empty cells before the 1st
    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'day empty';
        grid.appendChild(empty);
    }

    for (let d = 1; d <= daysInMo; d++) {
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const run     = state.runs[dateStr] || null;

        const cell = document.createElement('div');
        cell.className = 'day';
        cell.dataset.date = dateStr;

        if (isThisMonth && d === today.getDate()) {
            cell.classList.add('today');
        }

        if (run) {
            cell.classList.add(milesTier(parseFloat(run.miles)));
        }

        const numEl = document.createElement('div');
        numEl.className = 'day-number';
        numEl.textContent = d;
        cell.appendChild(numEl);

        if (run) {
            const milesEl = document.createElement('div');
            milesEl.className = 'day-miles';
            milesEl.textContent = parseFloat(run.miles) + ' mi';
            cell.appendChild(milesEl);

            if (run.notes) {
                const notesEl = document.createElement('div');
                notesEl.className = 'day-notes';
                notesEl.textContent = run.notes;
                cell.appendChild(notesEl);
            }
        }

        cell.addEventListener('click', () => openModal(dateStr, run));
        grid.appendChild(cell);
    }
}

function milesTier(miles) {
    if (miles >= 6) return 'tier-4';
    if (miles >= 4) return 'tier-3';
    if (miles >= 2) return 'tier-2';
    return 'tier-1';
}

// --- Modal ---

function openModal(dateStr, run) {
    const label = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        weekday: 'long', month: 'long', day: 'numeric'
    });

    document.getElementById('modal-title').textContent = label;
    document.getElementById('run-date').value  = dateStr;
    document.getElementById('miles').value     = run ? run.miles : '';
    document.getElementById('notes').value     = run ? (run.notes || '') : '';

    const deleteBtn = document.getElementById('delete-btn');
    if (run) {
        deleteBtn.classList.remove('hidden');
    } else {
        deleteBtn.classList.add('hidden');
    }

    document.getElementById('modal-overlay').classList.remove('hidden');
    document.getElementById('miles').focus();
}

function closeModal() {
    document.getElementById('modal-overlay').classList.add('hidden');
}

// --- Save ---

async function saveRun(e) {
    e.preventDefault();

    const payload = {
        run_date: document.getElementById('run-date').value,
        miles:    document.getElementById('miles').value,
        notes:    document.getElementById('notes').value,
    };

    const res = await fetch(API, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    });

    if (!res.ok) {
        const err = await res.json();
        alert('Error: ' + err.error);
        return;
    }

    closeModal();
    loadMonth();
}

// --- Delete ---

async function deleteRun() {
    const dateStr = document.getElementById('run-date').value;
    if (!confirm(`Delete run on ${dateStr}?`)) return;

    const res = await fetch(`${API}?run_date=${dateStr}`, { method: 'DELETE' });

    if (!res.ok) {
        const err = await res.json();
        alert('Error: ' + err.error);
        return;
    }

    closeModal();
    loadMonth();
}

// --- Start ---

document.addEventListener('DOMContentLoaded', init);
