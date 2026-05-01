<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forrest &mdash; Stats</title>
    <link rel="stylesheet" href="/forrest/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

    <header>
        <h1>Forrest</h1>
        <nav class="header-nav">
            <a href="/forrest/">Calendar</a>
            <a href="/forrest/stats/" class="active">Stats</a>
        </nav>
        <div class="auth-wrap">
            <a id="auth-btn" href="/forrest/login.php" class="auth-btn"></a>
        </div>
    </header>

    <main class="stats-main">

        <div id="stats-loading" class="stats-loading">Loading stats&hellip;</div>

        <div id="stats-content" class="hidden">

            <section class="stat-cards">
                <div class="stat-card" data-accent="green">
                    <div class="stat-icon">🏃</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-all-time">—</div>
                        <div class="stat-label">All-Time Miles</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="blue">
                    <div class="stat-icon">📅</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-this-year">—</div>
                        <div class="stat-label">Miles This Year</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="orange">
                    <div class="stat-icon">🗓️</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-this-month">—</div>
                        <div class="stat-label">Miles This Month</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="purple">
                    <div class="stat-icon">🔢</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-total-runs">—</div>
                        <div class="stat-label">Total Runs</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="red">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-longest">—</div>
                        <div class="stat-label">Longest Run</div>
                        <div class="stat-sub" id="s-longest-date"></div>
                    </div>
                </div>
                <div class="stat-card" data-accent="teal">
                    <div class="stat-icon">📊</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-avg">—</div>
                        <div class="stat-label">Avg Miles / Run</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="amber">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-current-streak">—</div>
                        <div class="stat-label">Current Streak</div>
                    </div>
                </div>
                <div class="stat-card" data-accent="indigo">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-body">
                        <div class="stat-value" id="s-best-streak">—</div>
                        <div class="stat-label">Best Streak</div>
                    </div>
                </div>
            </section>

            <section class="charts">
                <div class="chart-card">
                    <h2 id="monthly-chart-title">Monthly Miles</h2>
                    <div class="chart-wrap">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h2>Last 12 Weeks</h2>
                    <div class="chart-wrap">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </section>

        </div>

    </main>

    <script>
    const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function fmt(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(iso) {
        const [y, m, d] = iso.split('-');
        return `${MONTHS[parseInt(m,10)-1]} ${parseInt(d,10)}, ${y}`;
    }

    function set(id, val) {
        document.getElementById(id).textContent = val;
    }

    async function loadSession() {
        const res  = await fetch('/forrest/api/session.php');
        const data = await res.json();
        const btn  = document.getElementById('auth-btn');
        if (data.authenticated) {
            btn.textContent = 'Sign out';
            btn.href = '/forrest/logout.php';
        } else {
            btn.textContent = 'Sign in';
            btn.href = '/forrest/login.php';
        }
    }

    async function loadStats() {
        const res  = await fetch('/forrest/api/stats.php');
        const s    = await res.json();

        set('s-all-time',      fmt(s.total_all_time) + ' mi');
        set('s-this-year',     fmt(s.total_this_year) + ' mi');
        set('s-this-month',    fmt(s.total_this_month) + ' mi');
        set('s-total-runs',    s.total_runs.toLocaleString());
        set('s-avg',           fmt(s.avg_miles) + ' mi');
        set('s-current-streak', s.current_streak + (s.current_streak === 1 ? ' day' : ' days'));
        set('s-best-streak',   s.longest_streak + (s.longest_streak === 1 ? ' day' : ' days'));

        if (s.longest_run) {
            set('s-longest', fmt(s.longest_run.miles) + ' mi');
            set('s-longest-date', fmtDate(s.longest_run.date));
        } else {
            set('s-longest', '—');
        }

        buildMonthlyChart(s.monthly_miles);
        buildWeeklyChart(s.weekly);

        document.getElementById('stats-loading').classList.add('hidden');
        document.getElementById('stats-content').classList.remove('hidden');
    }

    function buildMonthlyChart(data) {
        const now       = new Date();
        const curMonth  = now.getMonth(); // 0-indexed
        const year      = now.getFullYear();

        document.getElementById('monthly-chart-title').textContent = `Monthly Miles — ${year}`;

        const colors = data.map((_, i) =>
            i === curMonth ? '#1e6b32' : '#4d9e60'
        );
        const borderColors = data.map((_, i) =>
            i === curMonth ? '#145228' : '#3a8a4d'
        );

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: MONTHS,
                datasets: [{
                    label: 'Miles',
                    data: data,
                    backgroundColor: colors,
                    borderColor: borderColors,
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: chartOptions('Miles'),
        });
    }

    function buildWeeklyChart(weeks) {
        const now     = new Date();
        // ISO week: monday of current week
        const day     = now.getDay() || 7;
        const monday  = new Date(now);
        monday.setDate(now.getDate() - day + 1);
        monday.setHours(0,0,0,0);

        const labels = weeks.map(w => w.label);
        const data   = weeks.map(w => w.miles);
        const colors = weeks.map((w, i) =>
            i === weeks.length - 1 ? '#1a5f9e' : '#5b8fc7'
        );

        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Miles',
                    data,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c === '#1a5f9e' ? '#154d82' : '#4a7ab2'),
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: chartOptions('Miles'),
        });
    }

    function chartOptions(yLabel) {
        return {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toFixed(2)} mi`,
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#4a6e4a', font: { size: 11 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#e0e8e0' },
                    ticks: {
                        color: '#4a6e4a',
                        font: { size: 11 },
                        callback: v => v + ' mi',
                    }
                }
            }
        };
    }

    loadSession();
    loadStats();
    </script>

</body>
</html>
