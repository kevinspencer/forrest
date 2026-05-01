<?php

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$pdo = get_pdo();

// Totals
$total_all_time  = (float) $pdo->query('SELECT COALESCE(SUM(miles),0) FROM runs')->fetchColumn();
$stmt = $pdo->prepare('SELECT COALESCE(SUM(miles),0) FROM runs WHERE YEAR(run_date)=YEAR(CURDATE())');
$stmt->execute(); $total_this_year = (float) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COALESCE(SUM(miles),0) FROM runs WHERE YEAR(run_date)=YEAR(CURDATE()) AND MONTH(run_date)=MONTH(CURDATE())');
$stmt->execute(); $total_this_month = (float) $stmt->fetchColumn();
$total_runs = (int) $pdo->query('SELECT COUNT(*) FROM runs')->fetchColumn();

// Longest run
$longest = $pdo->query('SELECT run_date, miles FROM runs ORDER BY miles DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

// Avg per run
$avg_miles = $total_runs > 0 ? $total_all_time / $total_runs : 0;

// Monthly miles for current year (all 12 months)
$stmt = $pdo->prepare(
    'SELECT MONTH(run_date) AS m, SUM(miles) AS miles
     FROM runs WHERE YEAR(run_date) = YEAR(CURDATE())
     GROUP BY MONTH(run_date)'
);
$stmt->execute();
$monthly = array_fill(1, 12, 0.0);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthly[(int)$row['m']] = (float)$row['miles'];
}

// Last 12 weeks (Mon–Sun ISO weeks)
$stmt = $pdo->prepare(
    'SELECT YEARWEEK(run_date, 3) AS wk, MIN(run_date) AS week_start, SUM(miles) AS miles
     FROM runs
     WHERE run_date >= DATE_SUB(CURDATE(), INTERVAL 84 DAY)
     GROUP BY wk ORDER BY wk'
);
$stmt->execute();
$db_weeks = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $db_weeks[$row['wk']] = ['label' => $row['week_start'], 'miles' => (float)$row['miles']];
}

$weeks_out = [];
for ($i = 11; $i >= 0; $i--) {
    $monday = new DateTime("monday -$i weeks");
    $wk_key = $monday->format('oW');
    $weeks_out[] = [
        'label' => $monday->format('M j'),
        'miles' => isset($db_weeks[$wk_key]) ? $db_weeks[$wk_key]['miles'] : 0.0,
    ];
}

// Streaks
$dates     = $pdo->query('SELECT run_date FROM runs ORDER BY run_date')->fetchAll(PDO::FETCH_COLUMN);
$date_set  = array_flip($dates);
$longest_streak  = 0;
$current_streak  = 0;

if (!empty($dates)) {
    $streak = 1;
    $longest_streak = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $diff = (new DateTime($dates[$i]))->diff(new DateTime($dates[$i - 1]))->days;
        $streak = ($diff === 1) ? $streak + 1 : 1;
        $longest_streak = max($longest_streak, $streak);
    }

    // Current streak: walk back from today; if today has no run, try from yesterday
    foreach (['today', 'yesterday'] as $start) {
        $check = new DateTime($start);
        $n = 0;
        while (isset($date_set[$check->format('Y-m-d')])) {
            $n++;
            $check->modify('-1 day');
        }
        if ($n > 0) { $current_streak = $n; break; }
    }
}

echo json_encode([
    'total_all_time'  => round($total_all_time, 2),
    'total_this_year' => round($total_this_year, 2),
    'total_this_month'=> round($total_this_month, 2),
    'total_runs'      => $total_runs,
    'longest_run'     => $longest ? ['miles' => (float)$longest['miles'], 'date' => $longest['run_date']] : null,
    'avg_miles'       => round($avg_miles, 2),
    'current_streak'  => $current_streak,
    'longest_streak'  => $longest_streak,
    'monthly_miles'   => array_values($monthly),
    'weekly'          => $weeks_out,
]);
