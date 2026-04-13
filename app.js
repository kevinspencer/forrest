const API = 'api/runs.php';

const state = {
    year: null,
    month: null,
    runs: {},       // keyed by 'YYYY-MM-DD'
    authenticated: false,
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

    loadSession();
}

// --- Session ---

async function loadSession() {
    const res  = await fetch('api/session.php');
    const data = await res.json();
    state.authenticated = data.authenticated;

    const authBtn = document.getElementById('auth-btn');
    if (state.authenticated) {
        authBtn.textContent = 'Sign out';
        authBtn.href = 'logout.php';
    } else {
        authBtn.textContent = 'Sign in';
        authBtn.href = 'login.php';
    }

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
    const { year, month } = state;

    const res  = await fetch(`${API}?year=${year}&month=${month}`);
    const rows = await res.json();

    state.runs = {};
    for (const row of rows) {
        state.runs[row.run_date] = row;
    }

    // Fetch prev month if the 1st doesn't fall on Sunday
    const firstDay = new Date(year, month - 1, 1).getDay();
    if (firstDay > 0) {
        let pm = month - 1, py = year;
        if (pm < 1) { pm = 12; py--; }
        const pr   = await fetch(`${API}?year=${py}&month=${pm}`);
        const prows = await pr.json();
        for (const row of prows) state.runs[row.run_date] = row;
    }

    // Fetch next month if the last row is incomplete
    const daysInMo = new Date(year, month, 0).getDate();
    if ((firstDay + daysInMo) % 7 !== 0) {
        let nm = month + 1, ny = year;
        if (nm > 12) { nm = 1; ny++; }
        const nr   = await fetch(`${API}?year=${ny}&month=${nm}`);
        const nrows = await nr.json();
        for (const row of nrows) state.runs[row.run_date] = row;
    }

    renderCalendar();
}

// --- Calendar rendering ---

function renderCalendar() {
    const { year, month } = state;

    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    document.getElementById('month-label').textContent = `${monthNames[month - 1]} ${year}`;

    const monthPrefix = `${year}-${String(month).padStart(2,'0')}-`;
    const total = Object.values(state.runs)
        .filter(r => r.run_date.startsWith(monthPrefix))
        .reduce((sum, r) => sum + parseFloat(r.miles), 0);
    const totalEl = document.getElementById('month-total');
    totalEl.textContent = total > 0 ? `— ${total % 1 === 0 ? total : total.toFixed(2)} mi` : '';

    const grid      = document.getElementById('calendar-grid');
    const firstDay  = new Date(year, month - 1, 1).getDay();   // 0=Sun
    const daysInMo  = new Date(year, month, 0).getDate();
    const today     = new Date();
    const isThisMonth = today.getFullYear() === year && today.getMonth() + 1 === month;

    grid.innerHTML = '';

    let pos       = firstDay;   // current column (0–6)
    let weekMiles = 0;

    function appendWeekTotal() {
        const cell = document.createElement('div');
        cell.className = 'week-total';
        cell.textContent = weekMiles > 0
            ? (weekMiles % 1 === 0 ? weekMiles : weekMiles.toFixed(2)) + ' mi'
            : '—';
        grid.appendChild(cell);
        weekMiles = 0;
        pos = 0;
    }

    function buildDayCell(dateStr, d, otherMonth) {
        const run  = state.runs[dateStr] || null;
        const cell = document.createElement('div');
        cell.className = 'day' + (otherMonth ? ' other-month' : '');
        cell.dataset.date = dateStr;

        if (!otherMonth && isThisMonth && d === today.getDate()) {
            cell.classList.add('today');
        }

        if (run) {
            cell.classList.add(milesTier(parseFloat(run.miles)));
            weekMiles += parseFloat(run.miles);
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
        return cell;
    }

    // Prev month overflow cells
    let pm = month - 1, py = year;
    if (pm < 1) { pm = 12; py--; }
    const daysInPrevMonth = new Date(py, pm, 0).getDate();

    for (let i = 0; i < firstDay; i++) {
        const d       = daysInPrevMonth - firstDay + 1 + i;
        const dateStr = `${py}-${String(pm).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        grid.appendChild(buildDayCell(dateStr, d, true));
    }

    // Current month cells
    for (let d = 1; d <= daysInMo; d++) {
        const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        grid.appendChild(buildDayCell(dateStr, d, false));

        pos++;
        if (pos === 7) appendWeekTotal();
    }

    // Next month overflow cells
    if (pos > 0) {
        let nm = month + 1, ny = year;
        if (nm > 12) { nm = 1; ny++; }

        for (let i = pos; i < 7; i++) {
            const d       = i - pos + 1;
            const dateStr = `${ny}-${String(nm).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            grid.appendChild(buildDayCell(dateStr, d, true));
        }
        appendWeekTotal();
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
    if (!state.authenticated) return;
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
