<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/NotificationService.php';

class AbsenceService
{
    private const VALID_REASONS = ['familiar', 'enfermedad', 'vacaciones', 'sin_justificacion'];
    private const VALID_STATUSES = ['pendiente', 'aprobado', 'rechazado'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    /**
     * Create an absence report.
     */
    public static function createAbsence(int $actorUserId, string $actorRole, array $data, ?array $file = null): array
    {
        $targetUserId = $actorUserId;
        if ($actorRole === 'admin' && isset($data['user_id']) && $data['user_id'] !== '') {
            $targetUserId = (int)$data['user_id'];
        }

        if ($targetUserId <= 0) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Usuario inválido para registrar ausencia.'], 'status' => 400];
        }

        if (self::isAdminUser($targetUserId)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Los administradores no requieren registro de asistencia/ausencia.'], 'status' => 400];
        }

        $projectId = isset($data['project_id']) && $data['project_id'] !== '' ? (int)$data['project_id'] : null;
        $dateStart = trim($data['date_start'] ?? '');
        $dateEnd   = trim($data['date_end'] ?? $dateStart);
        $reason    = trim($data['reason'] ?? '');
        $notes     = trim($data['notes'] ?? '');

        // Validation
        if ($dateStart === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'La fecha de inicio es obligatoria.'], 'status' => 400];
        }
        if ($dateEnd === '') {
            $dateEnd = $dateStart;
        }
        if ($dateEnd < $dateStart) {
            return ['error' => ['code' => 'validation_error', 'message' => 'La fecha final no puede ser anterior a la fecha de inicio.'], 'status' => 400];
        }
        if (!in_array($reason, self::VALID_REASONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Razón inválida. Opciones: ' . implode(', ', self::VALID_REASONS)], 'status' => 400];
        }

        // Handle file upload
        $evidencePath = null;
        if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadResult = self::handleEvidenceUpload($file, $targetUserId);
            if (isset($uploadResult['error'])) {
                return $uploadResult;
            }
            $evidencePath = $uploadResult['path'];
        }

        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO absences (user_id, project_id, date_start, date_end, reason, notes, evidence_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$targetUserId, $projectId, $dateStart, $dateEnd, $reason, $notes ?: null, $evidencePath]);

        $absenceId = (int)$pdo->lastInsertId();

        $uStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$targetUserId]);
        $username = (string)($uStmt->fetchColumn() ?: ('Usuario #' . $targetUserId));
        NotificationService::notifyAdmins('absence_reported', "{$username} reportó una ausencia ({$dateStart}).", $absenceId);

        return ['data' => [
            'message' => 'Reporte de ausencia enviado exitosamente.',
            'id'      => $absenceId,
        ]];
    }

    /**
     * List absences with optional filters.
     */
    public static function listAbsences(array $filters = []): array
    {
        $pdo = get_pdo();

        $sql = 'SELECT a.id, a.user_id, u.username, a.project_id, p.name AS project_name,
                       a.date_start, a.date_end, a.reason, a.notes, a.evidence_path,
                       a.status, a.reviewed_by, ru.username AS reviewed_by_username,
                       a.reviewed_at, a.created_at
                FROM absences a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN projects p ON a.project_id = p.id
                LEFT JOIN users ru ON a.reviewed_by = ru.id';

        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['project_id'])) {
            $conditions[] = 'a.project_id = ?';
            $params[] = (int)$filters['project_id'];
        }
        if (!empty($filters['reason']) && in_array($filters['reason'], self::VALID_REASONS, true)) {
            $conditions[] = 'a.reason = ?';
            $params[] = $filters['reason'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $conditions[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'a.date_start >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'a.date_end <= ?';
            $params[] = $filters['date_to'];
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY a.created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int)$filters['limit']);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update the status of an absence (approve/reject). Admin only.
     */
    public static function reviewAbsence(int $absenceId, string $status, int $reviewerId): array
    {
        if (!in_array($status, ['aprobado', 'rechazado'], true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Estado inválido. Use: aprobado o rechazado.'], 'status' => 400];
        }

        $pdo = get_pdo();

        $check = $pdo->prepare('SELECT id, status FROM absences WHERE id = ?');
        $check->execute([$absenceId]);
        $absence = $check->fetch(PDO::FETCH_ASSOC);

        if (!$absence) {
            return ['error' => ['code' => 'not_found', 'message' => 'Reporte de ausencia no encontrado.'], 'status' => 404];
        }

        $stmt = $pdo->prepare(
            'UPDATE absences SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $reviewerId, $absenceId]);

        $ownerStmt = $pdo->prepare('SELECT user_id FROM absences WHERE id = ? LIMIT 1');
        $ownerStmt->execute([$absenceId]);
        $ownerId = (int)$ownerStmt->fetchColumn();

        $reviewerStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $reviewerStmt->execute([$reviewerId]);
        $reviewerName = (string)($reviewerStmt->fetchColumn() ?: 'Admin');

        $label = $status === 'aprobado' ? 'aprobado' : 'rechazado';
        if ($ownerId > 0) {
            NotificationService::create($ownerId, 'absence_reviewed', "Tu ausencia fue {$label} por {$reviewerName}.", $absenceId);
        }

        return ['data' => ['message' => "Reporte de ausencia {$label} exitosamente."]];
    }

    /**
     * Get absence summary (days grouped by reason) for a user within a date range.
     */
    public static function getSummary(array $filters = []): array
    {
        $pdo = get_pdo();

        $sql = 'SELECT a.user_id, u.username, a.reason,
                       SUM(DATEDIFF(a.date_end, a.date_start) + 1) AS total_days
                FROM absences a
                JOIN users u ON a.user_id = u.id
                WHERE a.status != ?';
        $params = ['rechazado'];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND a.date_start >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND a.date_end <= ?';
            $params[] = $filters['date_to'];
        }

        $sql .= ' GROUP BY a.user_id, u.username, a.reason ORDER BY u.username, a.reason';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by user
        $grouped = [];
        foreach ($rows as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user_id'  => $uid,
                    'username' => $row['username'],
                    'reasons'  => [],
                    'total_days' => 0,
                ];
            }
            $days = (int)$row['total_days'];
            $grouped[$uid]['reasons'][$row['reason']] = $days;
            $grouped[$uid]['total_days'] += $days;
        }

        // Ensure all reasons are present
        foreach ($grouped as &$userData) {
            foreach (self::VALID_REASONS as $r) {
                if (!isset($userData['reasons'][$r])) {
                    $userData['reasons'][$r] = 0;
                }
            }
        }

        return array_values($grouped);
    }



    /**
     * Auto-mark unexcused absences for users without attendance/justification on a date.
     */
    public static function autoMarkUnexcusedForDate(string $date, int $adminId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Fecha inválida. Formato esperado: YYYY-MM-DD'], 'status' => 400];
        }

        $pdo = get_pdo();

        $usersStmt = $pdo->query("
            SELECT u.id
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.name <> 'admin'
        ");
        $userIds = array_map('intval', $usersStmt->fetchAll(PDO::FETCH_COLUMN));

        $inserted = 0;
        $skippedWithAttendance = 0;
        $skippedWithAbsence = 0;

        $attendanceStmt = $pdo->prepare("
            SELECT 1
            FROM attendance_records
            WHERE user_id = ?
              AND DATE(original_time) = ?
            LIMIT 1
        ");

        $absenceStmt = $pdo->prepare("
            SELECT 1
            FROM absences
            WHERE user_id = ?
              AND ? BETWEEN date_start AND date_end
              AND status <> 'rechazado'
            LIMIT 1
        ");

        $insertStmt = $pdo->prepare("
            INSERT INTO absences (user_id, project_id, date_start, date_end, reason, notes, status, reviewed_by, reviewed_at)
            VALUES (?, NULL, ?, ?, 'sin_justificacion', ?, 'aprobado', ?, NOW())
        ");

        foreach ($userIds as $userId) {
            $attendanceStmt->execute([$userId, $date]);
            if ($attendanceStmt->fetchColumn()) {
                $skippedWithAttendance++;
                continue;
            }

            $absenceStmt->execute([$userId, $date]);
            if ($absenceStmt->fetchColumn()) {
                $skippedWithAbsence++;
                continue;
            }

            $insertStmt->execute([
                $userId,
                $date,
                $date,
                'Ausencia registrada automáticamente por falta de asistencia y sin justificación.' ,
                $adminId
            ]);
            $inserted++;
        }

        return ['data' => [
            'date' => $date,
            'processed_users' => count($userIds),
            'inserted_absences' => $inserted,
            'skipped_with_attendance' => $skippedWithAttendance,
            'skipped_with_existing_absence' => $skippedWithAbsence,
            'message' => "Ausencias automáticas procesadas para {$date}."
        ]];
    }

    private static function isAdminUser(int $userId): bool
    {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (string)$stmt->fetchColumn() === 'admin';
    }

    /**
     * Edit an absence (admin only).
     */
    public static function updateAbsence(int $absenceId, array $data, int $adminId): array
    {
        $pdo = get_pdo();

        $check = $pdo->prepare('SELECT id FROM absences WHERE id = ? LIMIT 1');
        $check->execute([$absenceId]);
        if (!$check->fetchColumn()) {
            return ['error' => ['code' => 'not_found', 'message' => 'Reporte no encontrado.'], 'status' => 404];
        }

        $projectId = isset($data['project_id']) && $data['project_id'] !== '' ? (int)$data['project_id'] : null;
        $dateStart = trim((string)($data['date_start'] ?? ''));
        $dateEnd = trim((string)($data['date_end'] ?? $dateStart));
        $reason = trim((string)($data['reason'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $status = trim((string)($data['status'] ?? ''));

        if ($dateStart === '' || $dateEnd === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'Las fechas son obligatorias.'], 'status' => 400];
        }
        if ($dateEnd < $dateStart) {
            return ['error' => ['code' => 'validation_error', 'message' => 'La fecha final no puede ser anterior a la inicial.'], 'status' => 400];
        }
        if (!in_array($reason, self::VALID_REASONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Razón inválida.'], 'status' => 400];
        }
        if ($status !== '' && !in_array($status, self::VALID_STATUSES, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Estado inválido.'], 'status' => 400];
        }

        if ($status === '') {
            $stmt = $pdo->prepare('UPDATE absences SET project_id = ?, date_start = ?, date_end = ?, reason = ?, notes = ? WHERE id = ?');
            $stmt->execute([$projectId, $dateStart, $dateEnd, $reason, ($notes !== '' ? $notes : null), $absenceId]);
        } else {
            $stmt = $pdo->prepare('UPDATE absences SET project_id = ?, date_start = ?, date_end = ?, reason = ?, notes = ?, status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
            $stmt->execute([$projectId, $dateStart, $dateEnd, $reason, ($notes !== '' ? $notes : null), $status, $adminId, $absenceId]);
        }

        return ['data' => ['message' => 'Reporte de ausencia actualizado.']];
    }

    /**
     * Delete an absence (admin only, or own if still pending).
     */
    public static function deleteAbsence(int $absenceId, int $userId, string $role): array
    {
        $pdo = get_pdo();

        $check = $pdo->prepare('SELECT id, user_id, status, evidence_path FROM absences WHERE id = ?');
        $check->execute([$absenceId]);
        $absence = $check->fetch(PDO::FETCH_ASSOC);

        if (!$absence) {
            return ['error' => ['code' => 'not_found', 'message' => 'Reporte no encontrado.'], 'status' => 404];
        }

        // Users can only delete their own pending reports
        if ($role !== 'admin') {
            if ((int)$absence['user_id'] !== $userId) {
                return ['error' => ['code' => 'forbidden', 'message' => 'No puedes eliminar reportes de otros usuarios.'], 'status' => 403];
            }
            if ($absence['status'] !== 'pendiente') {
                return ['error' => ['code' => 'forbidden', 'message' => 'Solo puedes eliminar reportes pendientes.'], 'status' => 403];
            }
        }

        // Delete evidence file if exists
        if ($absence['evidence_path']) {
            $fullPath = APP_ROOT . DIRECTORY_SEPARATOR . $absence['evidence_path'];
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM absences WHERE id = ?');
        $stmt->execute([$absenceId]);

        return ['data' => ['message' => 'Reporte de ausencia eliminado.']];
    }

    // --- Private helpers ---

    private static function handleEvidenceUpload(array $file, int $userId): array
    {
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['error' => ['code' => 'validation_error', 'message' => 'El archivo es demasiado grande. Máximo 10 MB.'], 'status' => 400];
        }

        $originalName = basename($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', self::ALLOWED_EXTENSIONS)], 'status' => 400];
        }

        $uploadDir = APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'absences';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeName = sprintf('%d_%s_%s.%s', $userId, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['error' => ['code' => 'server_error', 'message' => 'Error al guardar el archivo.'], 'status' => 500];
        }

        return ['path' => 'uploads/absences/' . $safeName];
    }
}
