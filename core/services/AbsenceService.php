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
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid user for absence registration.'], 'status' => 400];
        }

        $pdo = get_pdo();

        // Verify target user exists
        $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $userCheck->execute([$targetUserId]);
        if (!$userCheck->fetchColumn()) {
            return ['error' => ['code' => 'not_found', 'message' => 'The specified user does not exist.'], 'status' => 404];
        }

        if (self::isAdminUser($targetUserId)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Admins do not require attendance/absence registration.'], 'status' => 400];
        }

        $projectId = isset($data['project_id']) && $data['project_id'] !== '' ? (int)$data['project_id'] : null;
        $dateStart = trim($data['date_start'] ?? '');
        $dateEnd   = trim($data['date_end'] ?? $dateStart);
        $reason    = trim($data['reason'] ?? '');
        $notes     = trim($data['notes'] ?? '');

        // Validation
        if ($dateStart === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'Start date is required.'], 'status' => 400];
        }
        if ($dateEnd === '') {
            $dateEnd = $dateStart;
        }
        if ($dateEnd < $dateStart) {
            return ['error' => ['code' => 'validation_error', 'message' => 'End date cannot be before start date.'], 'status' => 400];
        }
        if (!in_array($reason, self::VALID_REASONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid reason. Options: ' . implode(', ', self::VALID_REASONS)], 'status' => 400];
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

        $stmt = $pdo->prepare(
            'INSERT INTO absences (user_id, project_id, date_start, date_end, reason, notes, evidence_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$targetUserId, $projectId, $dateStart, $dateEnd, $reason, $notes ?: null, $evidencePath]);

        $absenceId = (int)$pdo->lastInsertId();

        $uStmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$targetUserId]);
        $username = (string)($uStmt->fetchColumn() ?: ('Usuario #' . $targetUserId));
        NotificationService::notifyAdmins('absence_reported', "{$username} reported an absence ({$dateStart}).", $absenceId);

        return ['data' => [
            'message' => 'Absence report submitted successfully.',
            'id'      => $absenceId,
        ]];
    }

    /**
     * List absences with optional filters.
     */
    public static function listAbsences(array $filters = []): array
    {
        [$sql, $params] = self::buildAbsenceListQuery($filters);

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int)$filters['limit']);
        }

        $stmt = get_pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function exportAbsencesToExcel(array $filters = []): array
    {
        $rows = self::listAbsences($filters);
        $headers = ['User', 'Project', 'Reason', 'Status', 'Start Date', 'End Date', 'Created At'];
        $columns = ['username', 'project_name', 'reason', 'status', 'date_start', 'date_end', 'created_at'];
        $widths = array_map('strlen', $headers);
        $sheetRows = [];

        foreach ($rows as $row) {
            $sheetRow = [];
            foreach ($columns as $index => $column) {
                $value = (string)($row[$column] ?? '');
                if ($column === 'project_name' && $value === '') {
                    $value = 'No project';
                }
                if ($column === 'reason') {
                    $value = self::formatReason((string)$row[$column]);
                }
                if ($column === 'status') {
                    $value = self::formatStatus((string)$row[$column]);
                }
                $widths[$index] = min(45, max($widths[$index], strlen($value)));
                $sheetRow[] = $value;
            }
            $sheetRows[] = $sheetRow;
        }

        $content = self::buildXlsx($headers, $sheetRows, $widths);

        return [
            'filename' => 'absence_records_' . date('Ymd') . '.xlsx',
            'content' => $content,
        ];
    }

    public static function exportAbsencesToPdf(array $filters = []): array
    {
        $rows = self::listAbsences($filters);
        $content = self::buildPdf($rows, $filters);

        return [
            'filename' => 'absence_records_' . date('Ymd') . '.pdf',
            'content' => $content,
        ];
    }

    private static function buildAbsenceListQuery(array $filters = []): array
    {
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
        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = '(u.username LIKE ? OR p.name LIKE ? OR a.reason LIKE ? OR a.status LIKE ? OR a.notes LIKE ? OR a.date_start LIKE ? OR a.date_end LIKE ?)';
            array_push($params, $search, $search, $search, $search, $search, $search, $search);
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY a.created_at DESC';

        return [$sql, $params];
    }

    private static function formatReason(string $reason): string
    {
        $labels = [
            'familiar' => 'Family',
            'enfermedad' => 'Illness',
            'vacaciones' => 'Vacation',
            'sin_justificacion' => 'Unexcused',
        ];
        return $labels[$reason] ?? $reason;
    }

    private static function formatStatus(string $status): string
    {
        $labels = [
            'pendiente' => 'Pending',
            'aprobado' => 'Approved',
            'rechazado' => 'Rejected',
        ];
        return $labels[$status] ?? $status;
    }

    private static function excelSerialDate(string $value): float
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return 0;
        }
        return ($timestamp / 86400) + 25569;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function buildXlsx(array $headers, array $rows, array $widths): string
    {
        $cols = '';
        foreach ($widths as $index => $width) {
            $cols .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . max(12, $width + 3) . '" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>' . $cols . '</cols><sheetData>';

        $sheet .= '<row r="1">';
        foreach ($headers as $index => $header) {
            $cell = chr(65 + $index) . '1';
            $sheet .= '<c r="' . $cell . '" t="inlineStr" s="1"><is><t>' . self::xml($header) . '</t></is></c>';
        }
        $sheet .= '</row>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $sheet .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $cell = chr(65 + $columnIndex) . $excelRow;
                if (in_array($columnIndex, [4, 5], true) && $value !== '') {
                    $sheet .= '<c r="' . $cell . '" s="2"><v>' . self::excelSerialDate($value) . '</v></c>';
                } elseif ($columnIndex === 6 && $value !== '') {
                    $sheet .= '<c r="' . $cell . '" s="3"><v>' . self::excelSerialDate($value) . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . self::xml($value) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Absence Records" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        return self::zipStore($files);
    }

    private static function zipStore(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $nameLength = strlen($name);

            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0) . $name;
            $local .= $localHeader . $content;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $size;
        }

        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($local), 0);
    }

    private static function buildPdf(array $rows, array $filters): string
    {
        $pages = [];
        $pageRows = array_chunk($rows, 18);
        if (empty($pageRows)) {
            $pageRows = [[]];
        }
        $pageCount = count($pageRows);

        foreach ($pageRows as $pageIndex => $pageItems) {
            $stream = "q 1 1 1 rg 0 0 841.89 595.28 re f Q\n";
            $stream .= self::pdfText('Absence Records Report', 36, 555, 18, true);
            $stream .= self::pdfText('Generated On: ' . date('Y-m-d H:i:s'), 36, 530, 10);
            $stream .= self::pdfText('Applied Filters: ' . self::formatFiltersForReport($filters), 36, 510, 9);

            $headers = ['User', 'Project', 'Reason', 'Status', 'Start Date', 'End Date'];
            $x = [36, 170, 310, 420, 515, 620];
            $width = [128, 134, 104, 88, 98, 98];
            $y = 475;

            $stream .= "q 0.12 0.16 0.22 rg 36 " . ($y - 4) . " 705 22 re f Q\n";
            foreach ($headers as $index => $header) {
                $stream .= self::pdfText($header, $x[$index] + 4, $y + 2, 9, true, [1, 1, 1]);
            }

            $y -= 25;
            foreach ($pageItems as $row) {
                $stream .= "q 0.86 0.88 0.92 rg 36 " . ($y - 4) . " 705 0.5 re f Q\n";
                $values = [
                    (string)($row['username'] ?? ''),
                    (string)($row['project_name'] ?? 'No project'),
                    self::formatReason((string)($row['reason'] ?? '')),
                    self::formatStatus((string)($row['status'] ?? '')),
                    (string)($row['date_start'] ?? ''),
                    (string)($row['date_end'] ?? ''),
                ];

                foreach ($values as $index => $value) {
                    $stream .= self::pdfText(self::truncateForPdf($value, $width[$index]), $x[$index] + 4, $y, 8);
                }
                $y -= 22;
            }

            if (empty($pageItems)) {
                $stream .= self::pdfText('No absence records found for the selected filters.', 36, 450, 10);
            }

            $stream .= self::pdfText('Page ' . ($pageIndex + 1) . ' of ' . $pageCount, 740, 28, 8);
            $pages[] = $stream;
        }

        return self::renderPdfDocument($pages);
    }

    private static function formatFiltersForReport(array $filters): string
    {
        $parts = [];
        if (!empty($filters['user_id'])) $parts[] = 'User ID: ' . (int)$filters['user_id'];
        if (!empty($filters['project_id'])) $parts[] = 'Project ID: ' . (int)$filters['project_id'];
        if (!empty($filters['reason'])) $parts[] = 'Reason: ' . self::formatReason((string)$filters['reason']);
        if (!empty($filters['status'])) $parts[] = 'Status: ' . self::formatStatus((string)$filters['status']);
        if (!empty($filters['date_from'])) $parts[] = 'From: ' . $filters['date_from'];
        if (!empty($filters['date_to'])) $parts[] = 'To: ' . $filters['date_to'];
        if (!empty($filters['search'])) $parts[] = 'Search: ' . $filters['search'];

        return $parts ? implode(' | ', $parts) : 'All records';
    }

    private static function truncateForPdf(string $value, int $width): string
    {
        $limit = max(8, (int)floor($width / 4.5));
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, max(0, $limit - 3)) . '...';
    }

    private static function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private static function pdfText(string $text, float $x, float $y, int $size = 10, bool $bold = false, array $rgb = [0.08, 0.10, 0.13]): string
    {
        return sprintf(
            "BT /%s %d Tf %.2F %.2F %.2F rg %.2F %.2F Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            self::pdfEscape($text)
        );
    }

    private static function renderPdfDocument(array $pageStreams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];
        $pageObjectIds = [];
        $nextObjectId = 3;

        foreach ($pageStreams as $stream) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /Font << /F1 __FONT_REGULAR__ 0 R /F2 __FONT_BOLD__ 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $fontRegularId = $nextObjectId++;
        $fontBoldId = $nextObjectId++;
        foreach ($pageObjectIds as $pageObjectId) {
            $objects[$pageObjectId] = str_replace(['__FONT_REGULAR__', '__FONT_BOLD__'], [(string)$fontRegularId, (string)$fontBoldId], $objects[$pageObjectId]);
        }

        $kids = array_map(function ($id) {
            return $id . ' 0 R';
        }, $pageObjectIds);
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pageObjectIds) . ' >>';
        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $maxObjectId = max(array_keys($objects));
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxObjectId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * Update the status of an absence (approve/reject). Admin only.
     */
    public static function reviewAbsence(int $absenceId, string $status, int $reviewerId): array
    {
        if (!in_array($status, ['aprobado', 'rechazado'], true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid status. Use: aprobado or rechazado.'], 'status' => 400];
        }

        $pdo = get_pdo();

        $check = $pdo->prepare('SELECT id, status FROM absences WHERE id = ?');
        $check->execute([$absenceId]);
        $absence = $check->fetch(PDO::FETCH_ASSOC);

        if (!$absence) {
            return ['error' => ['code' => 'not_found', 'message' => 'Absence report not found.'], 'status' => 404];
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

        $labelEng = $status === 'aprobado' ? 'approved' : 'rejected';
        if ($ownerId > 0) {
            NotificationService::create($ownerId, 'absence_reviewed', "Your absence was {$labelEng} by {$reviewerName}.", $absenceId);
        }

        return ['data' => ['message' => "Absence report {$labelEng} successfully."]];
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
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid date. Expected format: YYYY-MM-DD'], 'status' => 400];
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
                'Absence registered automatically due to lack of attendance and without justification.' ,
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
            'message' => "Automatic absences processed for {$date}."
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
            return ['error' => ['code' => 'not_found', 'message' => 'Report not found.'], 'status' => 404];
        }

        $projectId = isset($data['project_id']) && $data['project_id'] !== '' ? (int)$data['project_id'] : null;
        $dateStart = trim((string)($data['date_start'] ?? ''));
        $dateEnd = trim((string)($data['date_end'] ?? $dateStart));
        $reason = trim((string)($data['reason'] ?? ''));
        $notes = trim((string)($data['notes'] ?? ''));
        $status = trim((string)($data['status'] ?? ''));

        if ($dateStart === '' || $dateEnd === '') {
            return ['error' => ['code' => 'validation_error', 'message' => 'Dates are required.'], 'status' => 400];
        }
        if ($dateEnd < $dateStart) {
            return ['error' => ['code' => 'validation_error', 'message' => 'End date cannot be before start date.'], 'status' => 400];
        }
        if (!in_array($reason, self::VALID_REASONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid reason.'], 'status' => 400];
        }
        if ($status !== '' && !in_array($status, self::VALID_STATUSES, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid status.'], 'status' => 400];
        }

        if ($status === '') {
            $stmt = $pdo->prepare('UPDATE absences SET project_id = ?, date_start = ?, date_end = ?, reason = ?, notes = ? WHERE id = ?');
            $stmt->execute([$projectId, $dateStart, $dateEnd, $reason, ($notes !== '' ? $notes : null), $absenceId]);
        } else {
            $stmt = $pdo->prepare('UPDATE absences SET project_id = ?, date_start = ?, date_end = ?, reason = ?, notes = ?, status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
            $stmt->execute([$projectId, $dateStart, $dateEnd, $reason, ($notes !== '' ? $notes : null), $status, $adminId, $absenceId]);
        }

        return ['data' => ['message' => 'Absence report updated.']];
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
            return ['error' => ['code' => 'not_found', 'message' => 'Report not found.'], 'status' => 404];
        }

        // Users can only delete their own pending reports
        if ($role !== 'admin') {
            if ((int)$absence['user_id'] !== $userId) {
                return ['error' => ['code' => 'forbidden', 'message' => 'You cannot delete other users reports.'], 'status' => 403];
            }
            if ($absence['status'] !== 'pendiente') {
                return ['error' => ['code' => 'forbidden', 'message' => 'You can only delete pending reports.'], 'status' => 403];
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

        return ['data' => ['message' => 'Absence report deleted.']];
    }

    // --- Private helpers ---

    private static function handleEvidenceUpload(array $file, int $userId): array
    {
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['error' => ['code' => 'validation_error', 'message' => 'File is too large. Maximum 10 MB.'], 'status' => 400];
        }

        $originalName = basename($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['error' => ['code' => 'validation_error', 'message' => 'File type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)], 'status' => 400];
        }

        $uploadDir = APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'absences';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeName = sprintf('%d_%s_%s.%s', $userId, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['error' => ['code' => 'server_error', 'message' => 'Error saving file.'], 'status' => 500];
        }

        return ['path' => 'uploads/absences/' . $safeName];
    }
}
