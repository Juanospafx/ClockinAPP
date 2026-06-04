<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../funciones/time.php';
require_once __DIR__ . '/GeofenceService.php';
require_once __DIR__ . '/ProjectService.php';

class AttendanceService {
    public static function fetchRecords(
        ?int $userId,
        ?int $limit,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $searchText = null
    ): array {
        $pdo = get_pdo();

        $hasEntrySource = self::columnExists($pdo, 'attendance_records', 'entry_source');
        $hasManualReason = self::columnExists($pdo, 'attendance_records', 'manual_reason');
        $hasCreatedBy = self::columnExists($pdo, 'attendance_records', 'created_by');
        $hasLateReason = self::columnExists($pdo, 'attendance_records', 'late_reason');

        $selectEntrySource = $hasEntrySource ? 'ar.entry_source' : "NULL AS entry_source";
        $selectManualReason = $hasManualReason ? 'ar.manual_reason' : "NULL AS manual_reason";
        $selectCreatedBy = $hasCreatedBy ? 'ar.created_by' : "NULL AS created_by";
        $selectCreatedByUsername = $hasCreatedBy ? 'admin_u.username AS created_by_username' : "NULL AS created_by_username";
        $selectLateReason = $hasLateReason ? 'ar.late_reason AS late_reason' : "NULL AS late_reason";

        $sql = "SELECT ar.id, ar.user_id, u.username, ar.location, ar.type, ar.original_time, ar.rounded_time,
                       ar.total_duration, ar.lunch_duration, ar.created_at, ar.project_qr_id, pq.project_id, p.name AS project_name,
                       CASE
                           WHEN ar.type = 'exit' THEN ar.original_time
                           WHEN ar.type = 'entry' THEN ar.original_time
                           ELSE ar.original_time
                       END AS entry_time,
                       CASE
                           WHEN ar.type = 'exit' THEN ar.rounded_time
                           WHEN ar.type = 'entry' THEN (
                               SELECT x.rounded_time
                               FROM attendance_records x
                               WHERE x.user_id = ar.user_id
                                 AND x.type = 'exit'
                                 AND DATE(x.original_time) = DATE(ar.original_time)
                                 AND x.original_time >= ar.original_time
                               ORDER BY x.original_time ASC, x.id ASC
                               LIMIT 1
                           )
                           ELSE ar.rounded_time
                       END AS exit_time,
                       {$selectEntrySource}, {$selectManualReason}, {$selectCreatedBy},
                       {$selectCreatedByUsername}, {$selectLateReason}
                FROM attendance_records ar
                JOIN users u ON ar.user_id = u.id
                LEFT JOIN project_qrs pq ON ar.project_qr_id = pq.id
                LEFT JOIN projects p ON pq.project_id = p.id";

        if ($hasCreatedBy) {
            $sql .= ' LEFT JOIN users admin_u ON ar.created_by = admin_u.id';
        }

        $params = [];
        $whereClauses = [];

        if ($userId !== null) {
            $whereClauses[] = 'ar.user_id = ?';
            $params[] = $userId;
        }

        if ($fromDate !== null) {
            $whereClauses[] = 'ar.original_time >= ?';
            $params[] = $fromDate . ' 00:00:00';
        }

        if ($toDate !== null) {
            $whereClauses[] = 'ar.original_time < ?';
            $params[] = date('Y-m-d 00:00:00', strtotime($toDate . ' +1 day'));
        }

        if ($searchText !== null && $searchText !== '') {
            $searchColumns = ['u.username', 'p.name', 'ar.location', 'ar.type'];
            if ($hasEntrySource) $searchColumns[] = 'ar.entry_source';
            $whereClauses[] = '(' . implode(' LIKE ? OR ', $searchColumns) . ' LIKE ?)';
            foreach ($searchColumns as $_) $params[] = '%' . $searchText . '%';
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        $sql .= ' ORDER BY ar.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function exportReport(string $format, array $filters): array {
        if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
            throw new InvalidArgumentException('Unsupported attendance export format.');
        }

        $rows = self::fetchRecords(
            $filters['user_id'] ?? null,
            $filters['limit'] ?? null,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['search'] ?? null
        );
        $reportRows = array_map([self::class, 'formatExportRow'], $rows);
        $extension = $format === 'excel' ? 'xlsx' : $format;

        if ($format === 'csv') {
            $content = self::buildCsv($reportRows);
        } elseif ($format === 'excel') {
            $content = self::buildXlsx($reportRows);
        } else {
            $content = self::buildPdf($reportRows, $filters);
        }

        return [
            'filename' => 'attendance_records_' . date('Ymd') . '.' . $extension,
            'content' => $content,
        ];
    }

    private static function formatExportRow(array $row): array {
        $clockIn = (string)($row['entry_time'] ?? $row['original_time'] ?? '');
        $clockOut = (string)($row['exit_time'] ?? '');
        $status = $row['type'] === 'entry'
            ? (!empty($row['late_reason']) ? 'Late' : 'On time')
            : ($row['type'] === 'absence' ? 'Absence' : ucfirst((string)$row['type']));

        return [
            (string)($row['username'] ?? ''),
            (string)($row['project_name'] ?? 'No project') ?: 'No project',
            $clockIn,
            $clockOut,
            self::formatMinutes($row['total_duration'] ?? null),
            $status,
            $clockIn !== '' ? substr($clockIn, 0, 10) : '',
            (string)($row['created_at'] ?? ''),
        ];
    }

    private static function formatMinutes($minutes): string {
        if ($minutes === null || $minutes === '') return '';
        $total = max(0, (int)$minutes);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private static function exportHeaders(): array {
        return ['User', 'Project', 'Clock In', 'Clock Out', 'Worked Hours', 'Status', 'Date', 'Created At'];
    }

    private static function buildCsv(array $rows): string {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::exportHeaders());
        foreach ($rows as $row) fputcsv($stream, $row);
        rewind($stream);
        return (string)stream_get_contents($stream);
    }

    private static function buildXlsx(array $rows): string {
        $headers = self::exportHeaders();
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = min(45, max($widths[$index], strlen((string)$value)));
            }
        }

        $cols = '';
        foreach ($widths as $index => $width) {
            $cols .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . max(12, $width + 3) . '" customWidth="1"/>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>' . $cols . '</cols><sheetData>';
        $sheet .= '<row r="1">';
        foreach ($headers as $index => $header) {
            $sheet .= '<c r="' . self::excelColumn($index) . '1" t="inlineStr" s="1"><is><t>' . self::xml($header) . '</t></is></c>';
        }
        $sheet .= '</row>';
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $sheet .= '<row r="' . $excelRow . '">';
            foreach ($row as $columnIndex => $value) {
                $cell = self::excelColumn($columnIndex) . $excelRow;
                if (in_array($columnIndex, [2, 3, 7], true) && $value !== '') {
                    $sheet .= '<c r="' . $cell . '" s="3"><v>' . self::excelSerialDate((string)$value) . '</v></c>';
                } elseif ($columnIndex === 6 && $value !== '') {
                    $sheet .= '<c r="' . $cell . '" s="2"><v>' . self::excelSerialDate((string)$value) . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . self::xml((string)$value) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        return self::zipStore([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Attendance Records" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    private static function buildPdf(array $rows, array $filters): string {
        $pages = array_chunk($rows, 18) ?: [[]];
        $streams = [];
        foreach ($pages as $pageIndex => $pageRows) {
            $stream = "q 1 1 1 rg 0 0 841.89 595.28 re f Q\n";
            $stream .= self::pdfText('Attendance Tracking Report', 30, 557, 18, true);
            $stream .= self::pdfText('Generated On: ' . date('Y-m-d H:i:s'), 30, 535, 9);
            $stream .= self::pdfText('Applied Filters: ' . self::formatFiltersForReport($filters), 30, 517, 8);
            $headers = ['User', 'Project', 'Clock In', 'Clock Out', 'Worked', 'Status', 'Date'];
            $x = [30, 145, 265, 390, 515, 585, 670];
            $widths = [110, 115, 120, 120, 65, 80, 105];
            $y = 485;
            $stream .= "q 0.12 0.16 0.22 rg 30 " . ($y - 4) . " 780 22 re f Q\n";
            foreach ($headers as $i => $header) $stream .= self::pdfText($header, $x[$i] + 3, $y + 2, 8, true, [1, 1, 1]);
            $y -= 25;
            foreach ($pageRows as $row) {
                $values = array_slice($row, 0, 7);
                $stream .= "q 0.86 0.88 0.92 rg 30 " . ($y - 4) . " 780 0.5 re f Q\n";
                foreach ($values as $i => $value) $stream .= self::pdfText(self::truncateForPdf((string)$value, $widths[$i]), $x[$i] + 3, $y, 7);
                $y -= 22;
            }
            if (!$pageRows) $stream .= self::pdfText('No attendance records found for the selected filters.', 30, 450, 10);
            $stream .= self::pdfText('Page ' . ($pageIndex + 1) . ' of ' . count($pages), 745, 25, 8);
            $streams[] = $stream;
        }
        return self::renderPdfDocument($streams);
    }

    private static function formatFiltersForReport(array $filters): string {
        $parts = [];
        if (!empty($filters['from'])) $parts[] = 'From: ' . $filters['from'];
        if (!empty($filters['to'])) $parts[] = 'To: ' . $filters['to'];
        if (!empty($filters['user_id'])) $parts[] = 'User ID: ' . $filters['user_id'];
        if (!empty($filters['search'])) $parts[] = 'Search: ' . $filters['search'];
        if (!empty($filters['view_mode'])) $parts[] = 'View: ' . ucfirst($filters['view_mode']);
        if (!empty($filters['focus_date'])) $parts[] = 'Focus Date: ' . $filters['focus_date'];
        return $parts ? implode(' | ', $parts) : 'All records';
    }

    private static function excelColumn(int $index): string {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) $name = chr(65 + (($index - 1) % 26)) . $name;
        return $name;
    }

    private static function excelSerialDate(string $value): float {
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : ($timestamp / 86400) + 25569;
    }

    private static function xml(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function zipStore(array $files): string {
        $local = $central = '';
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

    private static function truncateForPdf(string $value, int $width): string {
        $limit = max(8, (int)floor($width / 4.5));
        return strlen($value) <= $limit ? $value : substr($value, 0, $limit - 3) . '...';
    }

    private static function pdfText(string $text, float $x, float $y, int $size = 10, bool $bold = false, array $rgb = [0.08, 0.10, 0.13]): string {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return sprintf("BT /%s %d Tf %.2F %.2F %.2F rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $escaped);
    }

    private static function renderPdfDocument(array $streams): string {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageIds = [];
        $nextId = 3;
        foreach ($streams as $stream) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 841.89 595.28] /Resources << /Font << /F1 __F1__ 0 R /F2 __F2__ 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        $f1 = $nextId++;
        $f2 = $nextId++;
        foreach ($pageIds as $pageId) $objects[$pageId] = str_replace(['__F1__', '__F2__'], [(string)$f1, (string)$f2], $objects[$pageId]);
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($id) => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[$f1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$f2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $maxId = max(array_keys($objects));
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        return $pdf . "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    public static function getSummary(?int $userId): array {
        $pdo = get_pdo();
        $subQuery = "SELECT user_id, DATE(original_time) as work_date, MAX(total_duration) as max_duration 
                     FROM attendance_records 
                     WHERE total_duration IS NOT NULL 
                     GROUP BY user_id, DATE(original_time)";

        $sql = "SELECT DATE_FORMAT(work_date, '%Y-%m') as work_month, SUM(max_duration) as total_minutes 
                FROM ({$subQuery}) as daily_maxes";
        $params = [];
        if ($userId) {
            $sql .= " WHERE user_id = ?";
            $params[] = $userId;
        }
        $sql .= " GROUP BY work_month ORDER BY work_month ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = array_column($results, 'work_month');
        $data = array_map(function ($minutes) {
            return round(((int)$minutes) / 60, 2);
        }, array_column($results, 'total_minutes'));

        return ['labels' => $labels, 'data' => $data];
    }

    public static function getDashboardMetrics(): array {
        $pdo = get_pdo();

        $activeEmployeesToday = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM attendance_records WHERE type = 'entry' AND DATE(original_time) = CURRENT_DATE()")->fetchColumn();
        $clockInsToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance_records WHERE type = 'entry' AND DATE(original_time) = CURRENT_DATE()")->fetchColumn();
        $absencesToday = (int)$pdo->query("SELECT COUNT(*) FROM absences WHERE CURRENT_DATE() BETWEEN date_start AND date_end")->fetchColumn();

        $weeklyAvgStmt = $pdo->query("SELECT AVG(daily_hours) FROM (
            SELECT user_id, DATE(original_time) AS d, MAX(COALESCE(total_duration, 0))/60.0 AS daily_hours
            FROM attendance_records
            WHERE total_duration IS NOT NULL AND YEARWEEK(original_time, 1) = YEARWEEK(CURRENT_DATE(), 1)
            GROUP BY user_id, DATE(original_time)
        ) t");
        $avgHoursWeek = round((float)($weeklyAvgStmt->fetchColumn() ?: 0), 2);

        return [
            'active_employees_today' => $activeEmployeesToday,
            'clockins_today' => $clockInsToday,
            'absences_today' => $absencesToday,
            'avg_hours_week' => $avgHoursWeek,
        ];
    }

    public static function createRecord(int $currentUserId, string $currentUserRole, array $data): array {
        $pdo = get_pdo();
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : $currentUserId;
        $location = trim((string)($data['location'] ?? ''));
        $type = (string)($data['type'] ?? '');
        $projectQrId = isset($data['project_qr_id']) ? (int)$data['project_qr_id'] : null;
        $projectIdFromClient = isset($data['project_id']) ? (int)$data['project_id'] : null;
        $clientTimeIso = $data['client_time_iso'] ?? null;
        $clientTimeLocal = isset($data['client_time_local']) ? trim((string)$data['client_time_local']) : '';
        $lateReason = isset($data['late_reason']) ? trim((string)$data['late_reason']) : '';

        if (!$userId || !$location || !$type) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing required fields.'], 'status' => 400];
        }
        if (!$clientTimeIso) {
            return ['error' => ['code' => 'validation_error', 'message' => 'client_time_iso is required.'], 'status' => 400];
        }
        if ($currentUserRole !== 'admin' && $userId !== $currentUserId) {
            return ['error' => ['code' => 'forbidden', 'message' => 'You cannot create records for another user.'], 'status' => 403];
        }

        try {
            $originalTime = new DateTime($clientTimeIso);
            $originalTime->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid client_time_iso format.'], 'status' => 400];
        }
        $roundedTime = round_time_to_next_quarter(clone $originalTime);

        if ($projectQrId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM project_qrs WHERE id = ?');
            $stmt->execute([$projectQrId]);
            if (!$stmt->fetch()) {
                $projectQrId = null;
            }
        }
        if ($projectQrId === null && $projectIdFromClient) {
            $stmt = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$projectIdFromClient]);
            $foundQrId = $stmt->fetchColumn();
            if ($foundQrId) {
                $projectQrId = (int)$foundQrId;
            }
        }

        // --- Late check based on QR schedule (entry + 10 min tolerance) ---
        if ($type === 'entry' && $projectQrId !== null && self::columnExists($pdo, 'project_qrs', 'entry_time_required')) {
            $qrStmt = $pdo->prepare('SELECT entry_time_required FROM project_qrs WHERE id = ? LIMIT 1');
            $qrStmt->execute([$projectQrId]);
            $entryTimeRequired = (string)($qrStmt->fetchColumn() ?: '');

            if ($entryTimeRequired !== '' && preg_match('/^\d{2}:\d{2}/', $entryTimeRequired)) {
                $referenceLocal = null;
                if ($clientTimeLocal !== '') {
                    $referenceLocal = new DateTime($clientTimeLocal);
                } else {
                    $referenceLocal = clone $originalTime;
                }

                $day = $referenceLocal->format('Y-m-d');
                $deadline = new DateTime($day . ' ' . substr($entryTimeRequired, 0, 5) . ':00');
                $deadline->modify('+10 minutes');

                if ($referenceLocal > $deadline && $lateReason === '') {
                    $lateMinutes = (int)floor(($referenceLocal->getTimestamp() - $deadline->getTimestamp()) / 60);
                    return [
                        'error' => [
                            'code' => 'late_reason_required',
                            'message' => 'Llegaste tarde. Debes escribir una justificación.',
                            'details' => ['late_minutes' => max(1, $lateMinutes)]
                        ],
                        'status' => 422
                    ];
                }
            }
        }

        // --- Geofence validation ---
        $resolvedProjectId = $projectIdFromClient;
        if (!$resolvedProjectId && $projectQrId !== null) {
            $pqStmt = $pdo->prepare('SELECT project_id FROM project_qrs WHERE id = ?');
            $pqStmt->execute([$projectQrId]);
            $resolvedProjectId = (int)($pqStmt->fetchColumn() ?: 0);
        }

        if ($resolvedProjectId) {
            $project = ProjectService::getProject($resolvedProjectId);
            if ($project && $project['latitude'] !== null && $project['longitude'] !== null) {
                $userCoords = GeofenceService::parseLocation($location);
                if ($userCoords === null) {
                    return [
                        'error' => [
                            'code' => 'geofence_error',
                            'message' => 'No se pudo determinar tu ubicación. La geolocalización es obligatoria para registrar asistencia en este proyecto.'
                        ],
                        'status' => 400
                    ];
                }

                $geoCheck = GeofenceService::checkPosition(
                    $userCoords[0],
                    $userCoords[1],
                    (float)$project['latitude'],
                    (float)$project['longitude'],
                    (int)$project['geofence_radius']
                );

                if (!$geoCheck['inside']) {
                    return [
                        'error' => [
                            'code' => 'outside_geofence',
                            'message' => 'No estás dentro del área permitida para este proyecto. Acércate a la ubicación asignada para poder registrar tu entrada/salida.',
                            'details' => [
                                'distance_meters' => $geoCheck['distance'],
                                'allowed_radius'  => $geoCheck['radius'],
                            ]
                        ],
                        'status' => 403
                    ];
                }
            }
        }
        // --- End geofence validation ---

        $pdo->beginTransaction();
        try {
            if ($type === 'entry') {
                $openTimer = self::findOpenTimerEntry($pdo, $userId, true);
                if ($openTimer) {
                    $pdo->rollBack();
                    return ['error' => ['code' => 'conflict', 'message' => 'Ya existe un temporizador activo.'], 'status' => 409];
                }
                if (self::columnExists($pdo, 'attendance_records', 'late_reason')) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, status, late_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, 'entry', $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $projectQrId, 1, ($lateReason !== '' ? $lateReason : null)]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, 'entry', $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $projectQrId, 1]);
                }
            } else {
                $openTimer = self::findOpenTimerEntry($pdo, $userId, true);
                if (!$openTimer) {
                    $pdo->rollBack();
                    return ['error' => ['code' => 'conflict', 'message' => 'No se encontró un temporizador activo para actualizar.'], 'status' => 409];
                }

                $currentProjectQrId = $projectQrId ?? $openTimer['project_qr_id'];
                $newStatus = self::mapTimerStatusFromType($type);

                if ($type === 'exit') {
                    $metrics = self::calculateTimerMetrics($pdo, $openTimer, $originalTime);
                    $sessionDurationMinutes = (int)round($metrics['duration_seconds'] / 60);

                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, total_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId, $sessionDurationMinutes]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ?, total_duration = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $sessionDurationMinutes, $openTimer['id']]);
                } elseif ($type === 'end_lunch') {
                    $lunchDurationMinutes = null;
                    $startLunchStmt = $pdo->prepare("SELECT original_time FROM attendance_records WHERE user_id = ? AND type = 'start_lunch' AND original_time >= ? ORDER BY original_time DESC LIMIT 1");
                    $startLunchStmt->execute([$userId, $openTimer['original_time']]);
                    if ($startLunchTimeStr = $startLunchStmt->fetchColumn()) {
                        $startLunchTime = new DateTime($startLunchTimeStr, new DateTimeZone('UTC'));
                        $lunchDurationSeconds = $originalTime->getTimestamp() - $startLunchTime->getTimestamp();
                        $lunchDurationMinutes = (int)round($lunchDurationSeconds / 60);
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id, lunch_duration) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId, $lunchDurationMinutes]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $openTimer['id']]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance_records (user_id, location, type, original_time, rounded_time, project_qr_id) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$userId, $location, $type, $originalTime->format('Y-m-d H:i:s'), $roundedTime->format('Y-m-d H:i:s'), $currentProjectQrId]);

                    $updateStmt = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE id = ?');
                    $updateStmt->execute([$newStatus, $openTimer['id']]);
                }
            }

            $pdo->commit();
            return ['data' => ['message' => 'Record ' . $type . ' processed successfully.']];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => ['code' => 'server_error', 'message' => 'Server error: ' . $e->getMessage()], 'status' => 500];
        }
    }

    public static function updateRecord(string $currentUserRole, array $data): array {
        if ($currentUserRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can update records.'], 'status' => 403];
        }

        $recordId = isset($data['id']) ? (int)$data['id'] : null;
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $location = trim((string)($data['location'] ?? ''));
        $type = (string)($data['type'] ?? '');
        $originalTimeStr = $data['original_time'] ?? null;
        $roundedTimeStr = $data['rounded_time'] ?? null;
        $totalDuration = isset($data['total_duration']) ? (int)$data['total_duration'] : null;
        $lunchDuration = isset($data['lunch_duration']) ? (int)$data['lunch_duration'] : null;
        $projectQrId = isset($data['project_qr_id']) && $data['project_qr_id'] !== null ? (int)$data['project_qr_id'] : null;
        $projectId = isset($data['project_id']) && $data['project_id'] !== null ? (int)$data['project_id'] : null;

        if (!$recordId || !$userId || !$location || !$type || !$originalTimeStr || !$roundedTimeStr) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing required fields for update.'], 'status' => 400];
        }

        $pdo = get_pdo();

        if ($projectId) {
            $stmtProjectQr = $pdo->prepare('SELECT id FROM project_qrs WHERE project_id = ? ORDER BY id DESC LIMIT 1');
            $stmtProjectQr->execute([$projectId]);
            $resolvedProjectQrId = $stmtProjectQr->fetchColumn();
            if ($resolvedProjectQrId) {
                $projectQrId = (int)$resolvedProjectQrId;
            }
        }

        // If the UI sends entry/exit times, map them into original/rounded.
        $entryTimeStr = isset($data['entry_time']) ? trim((string)$data['entry_time']) : '';
        $exitTimeStr = isset($data['exit_time']) ? trim((string)$data['exit_time']) : '';

        if ($entryTimeStr !== '' && $exitTimeStr !== '') {
            $originalTimeStr = $entryTimeStr;
            $roundedTimeStr = $exitTimeStr;
        }

        // Normalize to UTC storage format (Y-m-d H:i:s), accepting ISO8601 with explicit Z/offset.
        try {
            $entryDt = new DateTimeImmutable((string)$originalTimeStr);
            $exitDt = new DateTimeImmutable((string)$roundedTimeStr);
        } catch (Throwable $e) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Invalid datetime format.'], 'status' => 400];
        }

        $entryDt = $entryDt->setTimezone(new DateTimeZone('UTC'));
        $exitDt = $exitDt->setTimezone(new DateTimeZone('UTC'));
        $originalTimeStr = $entryDt->format('Y-m-d H:i:s');
        $roundedTimeStr = $exitDt->format('Y-m-d H:i:s');

        // Recompute whenever both timestamps are present to avoid stale/blank/zero values.
        $entryTs = $entryDt->getTimestamp();
        $exitTs = $exitDt->getTimestamp();
        if ($entryTs === false || $exitTs === false || $exitTs <= $entryTs) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Exit time must be greater than entry time.'], 'status' => 400];
        }

        $gross = (int)round(($exitTs - $entryTs) / 60);

        // Only auto-discount lunch (12:00–13:00) for shifts >= 6 hours that fully contain the lunch window.
        // Short shifts or shifts that only partially overlap lunch should not be penalized.
        $computedLunch = 0;
        $MIN_HOURS_FOR_AUTO_LUNCH = 6;
        if ($gross >= ($MIN_HOURS_FOR_AUTO_LUNCH * 60)) {
            $day = date('Y-m-d', $entryTs);
            $lunchStartTs = strtotime($day . ' 12:00:00');
            $lunchEndTs = strtotime($day . ' 13:00:00');

            // Only discount if the shift fully contains the 12:00-13:00 window
            if ($entryTs <= $lunchStartTs && $exitTs >= $lunchEndTs) {
                $computedLunch = 60; // Full 1-hour lunch
            }
        }

        $totalDuration = max(0, $gross - $computedLunch);
        $lunchDuration = $computedLunch;

        $stmt = $pdo->prepare(
            'UPDATE attendance_records
             SET user_id = ?, location = ?, type = ?, original_time = ?, rounded_time = ?, total_duration = ?, lunch_duration = ?, project_qr_id = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $userId,
            $location,
            $type,
            $originalTimeStr,
            $roundedTimeStr,
            $totalDuration,
            $lunchDuration,
            $projectQrId,
            $recordId
        ]);

        return ['data' => ['message' => 'Record updated successfully.']];
    }

    public static function deleteRecord(string $currentUserRole, array $data): array {
        if ($currentUserRole !== 'admin') {
            return ['error' => ['code' => 'forbidden', 'message' => 'Only administrators can delete records.'], 'status' => 403];
        }
        $recordId = isset($data['id']) ? (int)$data['id'] : null;
        if (!$recordId) {
            return ['error' => ['code' => 'validation_error', 'message' => 'Missing record ID for deletion.'], 'status' => 400];
        }
        $pdo = get_pdo();
        $stmt = $pdo->prepare('DELETE FROM attendance_records WHERE id = ?');
        $stmt->execute([$recordId]);
        return ['data' => ['message' => 'Record deleted successfully.']];
    }

    public static function recalcDailyDuration(int $userId, string $date): int {
        $pdo = get_pdo();
        $totalDuration = self::calculateDailyWorkDuration($pdo, $userId, $date);
        $stmt = $pdo->prepare(
            "UPDATE attendance_records
             SET total_duration = ?
             WHERE user_id = ? AND DATE(original_time) = ? AND type = 'exit'"
        );
        $stmt->execute([$totalDuration, $userId, $date]);
        return $totalDuration;
    }

    public static function calculateDailyWorkDuration(PDO $pdo, int $userId, string $date, ?array $pendingEvent = null): int {
        $utc = new DateTimeZone('UTC');
        $stmt = $pdo->prepare(
            "SELECT type, original_time FROM attendance_records
             WHERE user_id = ? AND DATE(original_time) = ?
             ORDER BY original_time ASC, id ASC"
        );
        $stmt->execute([$userId, $date]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($pendingEvent) {
            $events[] = $pendingEvent;
            usort($events, function ($a, $b) {
                return strtotime($a['original_time']) - strtotime($b['original_time']);
            });
        }

        if (empty($events)) {
            return 0;
        }

        $elapsedSeconds = 0;
        $segmentStart = null;

        foreach ($events as $event) {
            $eventTime = new DateTime($event['original_time'], $utc);

            switch ($event['type']) {
                case 'entry':
                case 'resume':
                case 'end_lunch':
                    if ($segmentStart === null) {
                        $segmentStart = $eventTime;
                    }
                    break;
                case 'exit':
                case 'pause':
                case 'start_lunch':
                    if ($segmentStart !== null) {
                        $elapsedSeconds += $eventTime->getTimestamp() - $segmentStart->getTimestamp();
                        $segmentStart = null;
                    }
                    break;
            }
        }

        return (int)round($elapsedSeconds / 60);
    }

    public static function mapTimerStatusFromType(string $type): ?int {
        switch ($type) {
            case 'entry':
            case 'resume':
            case 'end_lunch':
                return 1;
            case 'pause':
            case 'start_lunch':
                return 2;
            case 'exit':
                return 3;
            default:
                return null;
        }
    }

    public static function findOpenTimerEntry(PDO $pdo, int $userId, bool $forUpdate = false): ?array {
        $sql = "SELECT * FROM attendance_records WHERE user_id = :user_id AND type = 'entry' AND status IN (1, 2) ORDER BY original_time DESC, id DESC LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function getTimerEntryById(PDO $pdo, int $timerId, bool $forUpdate = false): ?array {
        $sql = "SELECT * FROM attendance_records WHERE id = :id AND type = 'entry' LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $timerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$key];
    }

    public static function calculateTimerMetrics(PDO $pdo, array $entryRow, ?DateTime $referenceTime = null): array {
        $utc = new DateTimeZone('UTC');
        $reference = $referenceTime ? clone $referenceTime : new DateTime('now', $utc);
        if ($reference->getTimezone()->getName() !== 'UTC') {
            $reference->setTimezone($utc);
        }

        $stmt = $pdo->prepare(
            "SELECT id, type, original_time FROM attendance_records
             WHERE user_id = :user_id AND original_time >= :start_time
             ORDER BY original_time ASC, id ASC"
        );
        $stmt->execute([
            'user_id' => $entryRow['user_id'],
            'start_time' => $entryRow['original_time']
        ]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $elapsedSeconds = 0;
        $segmentStart = null;

        foreach ($events as $event) {
            $eventTime = new DateTime($event['original_time'], $utc);
            if ((int)$event['id'] !== (int)$entryRow['id'] && $event['type'] === 'entry') {
                break;
            }

            switch ($event['type']) {
                case 'entry':
                case 'resume':
                case 'end_lunch':
                    if ($segmentStart === null) {
                        $segmentStart = $eventTime;
                    }
                    break;
                case 'pause':
                case 'start_lunch':
                case 'exit':
                    if ($segmentStart !== null) {
                        $elapsedSeconds += max(0, $eventTime->getTimestamp() - $segmentStart->getTimestamp());
                        $segmentStart = null;
                    }
                    if ($event['type'] === 'exit') {
                        break 2;
                    }
                    break;
            }
        }

        $runningSince = null;
        if ($segmentStart !== null) {
            $elapsedSeconds += max(0, $reference->getTimestamp() - $segmentStart->getTimestamp());
            $runningSince = clone $segmentStart;
        }

        return [
            'duration_seconds' => (int)$elapsedSeconds,
            'running_since' => $runningSince,
        ];
    }
}
