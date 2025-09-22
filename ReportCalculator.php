<?php
class ReportCalculator {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function calculateForUser(int $user_id, ?string $start_date = null, ?string $end_date = null) {
        try {
            // Fetch user base data
            $user_sql = "SELECT daily_hours_goal, annual_leave_days FROM users WHERE id = :id";
            $user_stmt = $this->pdo->prepare($user_sql);
            $user_stmt->execute(['id' => $user_id]);
            $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_data) {
                return ['error' => 'User not found'];
            }

            // --- Build dynamic queries based on date range ---
            $date_condition_time = '';
            $date_condition_leave = '';
            $params = ['user_id' => $user_id];

            if ($start_date && $end_date) {
                $date_condition_time = "AND log_date BETWEEN :start_date AND :end_date";
                $date_condition_leave = "AND leave_date BETWEEN :start_date AND :end_date";
                $params['start_date'] = $start_date;
                $params['end_date'] = $end_date;
            }

            // Fetch all time logs within the range
            $time_sql = "SELECT log_date, start_time, end_time, log_type FROM time_logs WHERE user_id = :user_id $date_condition_time";
            $time_stmt = $this->pdo->prepare($time_sql);
            $time_stmt->execute($params);
            $time_logs = $time_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all leave logs (use a different params array for leave query)
            $leave_params = ['user_id' => $user_id];
            if ($start_date && $end_date) {
                $leave_params['start_date'] = $start_date;
                $leave_params['end_date'] = $end_date;
            }
            $leave_sql = "SELECT COUNT(*) as count FROM leave_logs WHERE user_id = :user_id $date_condition_leave";
            $leave_stmt = $this->pdo->prepare($leave_sql);
            $leave_stmt->execute($leave_params);
            $leave_count = $leave_stmt->fetchColumn();

            // --- Calculations ---
            $total_work_seconds = 0;
            $total_break_seconds = 0;
            $days_worked = count(array_unique(array_column($time_logs, 'log_date')));

            foreach ($time_logs as $log) {
                $start = new DateTime($log['start_time']);
                $end = new DateTime($log['end_time']);
                $diff_seconds = $end->getTimestamp() - $start->getTimestamp();

                if ($log['log_type'] === 'work') {
                    $total_work_seconds += $diff_seconds;
                } else {
                    $total_break_seconds += $diff_seconds;
                }
            }

            $net_work_seconds = $total_work_seconds - $total_break_seconds;

            $total_hours_goal = $days_worked * $user_data['daily_hours_goal'] * 3600;
            $overtime_undertim_seconds = $net_work_seconds - $total_hours_goal;

            $remaining_leave_days = $user_data['annual_leave_days'] - $leave_count;

            return [
                'success' => true,
                'total_work_hours' => round($net_work_seconds / 3600, 2),
                'total_overtime_undertim_hours' => round($overtime_undertim_seconds / 3600, 2),
                'remaining_leave_days' => $remaining_leave_days,
                'days_worked' => $days_worked
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Calculation error: ' . $e->getMessage()];
        }
    }
}
?>
