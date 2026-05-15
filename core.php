<?php

declare(strict_types=1);

require_once __DIR__ . '/azure_sql_helper.php';

function cfg(string $k, $d = null)
{
    $v = getenv($k);
    return $v === false ? $d : $v;
}

function json_in(): array
{
    $d = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($d) ? $d : [];
}

function ok($data = null, string $msg = 'OK', int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');

    echo json_encode([
        'ok' => true,
        'message' => $msg,
        'data' => normalize_json_value($data),
    ], JSON_UNESCAPED_UNICODE);

    exit();
}

function fail(string $code, string $msg, int $http = 400, array $errors = []): void
{
    http_response_code($http);
    header('Content-Type: application/json');

    echo json_encode([
        'ok' => false,
        'code' => $code,
        'message' => $msg,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);

    exit();
}

function require_fields(array $input, array $fields): void
{
    foreach ($fields as $f) {
        if (!isset($input[$f]) || $input[$f] === '') {
            fail('VALIDATION_ERROR', "$f es obligatorio");
        }
    }
}

function sql_str(?string $v): string
{
    if ($v === null) {
        return 'NULL';
    }

    return "N'" . str_replace("'", "''", $v) . "'";
}

function sql_int($v): string
{
    if (is_int($v)) {
        return (string)$v;
    }

    if (is_string($v) && preg_match('/^-?\d+$/', $v)) {
        return (string)((int)$v);
    }

    throw new InvalidArgumentException('Valor entero inválido');
}

function sql_table(string $v): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $v)) {
        throw new InvalidArgumentException('Nombre de tabla inválido');
    }

    return $v;
}

function sql_date(string $v): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);

    if (!$d || $d->format('Y-m-d') !== $v) {
        throw new InvalidArgumentException('Fecha inválida');
    }

    return sql_str($v);
}

function sql_datetime(DateTimeInterface $v): string
{
    return sql_str($v->format('Y-m-d H:i:s'));
}

function normalize_json_value($v)
{
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d H:i:s');
    }

    if (is_array($v)) {
        $out = [];

        foreach ($v as $k => $val) {
            $out[$k] = normalize_json_value($val);
        }

        return $out;
    }

    if (is_object($v)) {
        return normalize_json_value((array)$v);
    }

    return $v;
}

function db_select(string $sql, bool $onlyRead = true): array
{
    $rows = ejecutarQueryAzureSQLServerV2($sql, $onlyRead);

    if (!is_array($rows)) {
        return [];
    }

    return array_map(static function ($r) {
        return normalize_json_value((array)$r);
    }, $rows);
}

function db_exec(string $sql): void
{
    ejecutarQueryAzureSQLServerV2($sql . '; SELECT 1 AS ok;', false);
}

function booking_licenses_table(): string
{
    return sql_table((string)cfg('BOOKING_TABLE_LICENSES', 't_licencia'));
}

function booking_appointments_table(): string
{
    return sql_table((string)cfg('BOOKING_TABLE_APPOINTMENTS', 't_cita'));
}

function license_or_fail(string $idLiceEncr = ''): array
{
    if ($idLiceEncr === '') {
        fail('VALIDATION_ERROR', 'id_lice_encr es obligatorio');
    }

    $table = booking_licenses_table();

    $rows = db_select(
        "
        SELECT TOP 1
            id_lice,
            id_lice_encr,
            ISNULL(booking_enabled, 1) AS booking_enabled
        FROM {$table}
        WHERE id_lice_encr = " . sql_str($idLiceEncr)
    );

    if (!$rows) {
        fail('LICENSE_NOT_FOUND', 'Licencia no encontrada', 404);
    }

    $r = $rows[0];

    if (!(bool)($r['booking_enabled'] ?? 1)) {
        fail('LICENSE_INACTIVE', 'Licencia inactiva', 422);
    }

    return [
        'id' => (int)$r['id_lice'],
        'id_lice_encr' => (string)($r['id_lice_encr'] ?? $idLiceEncr),
    ];
}

function appointment_exists(int $appointmentId, int $licenseId, string $appointmentsTable): bool
{
    $rows = db_select(
        "
        SELECT TOP 1 id_cita
        FROM {$appointmentsTable}
        WHERE id_cita = " . sql_int($appointmentId) . "
        AND id_lice = " . sql_int($licenseId)
    );

    return (bool)$rows;
}

function slot_is_occupied(
    int $licenseId,
    DateTimeInterface $start,
    string $appointmentsTable,
    ?int $excludeAppointmentId = null,
    ?string $excludeDocument = null
): bool {
    $where = "
        id_lice = " . sql_int($licenseId) . "
        AND fech_cita = " . sql_datetime($start);

    if ($excludeAppointmentId !== null) {
        $where .= "
            AND id_cita <> " . sql_int($excludeAppointmentId);
    }

    if ($excludeDocument !== null) {
        $where .= "
            AND cedu_paci <> " . sql_str($excludeDocument);
    }

    $rows = db_select("
        SELECT TOP 1 id_cita
        FROM {$appointmentsTable}
        WHERE {$where}
    ");

    return (bool)$rows;
}

function parse_start_at(string $value): DateTimeImmutable
{
    if (trim($value) === '') {
        fail('VALIDATION_ERROR', 'startAt inválido');
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Exception $e) {
        fail('VALIDATION_ERROR', 'startAt inválido');
    }

    throw new RuntimeException('startAt inválido');
}

function run(string $route): void
{
    try {
        $appointmentsTable = booking_appointments_table();

        if ($route === 'availability') {
            $encr = (string)($_GET['id_lice_encr'] ?? '');
            $date = (string)($_GET['date'] ?? '');

            if ($date === '') {
                fail('VALIDATION_ERROR', 'date es obligatorio');
            }

            sql_date($date);

            $lic = license_or_fail($encr);

            $occ = db_select(
                "
                SELECT fech_cita
                FROM {$appointmentsTable}
                WHERE id_lice = " . sql_int($lic['id']) . "
                AND CAST(fech_cita AS DATE) = " . sql_date($date)
            );

            $start = strtotime($date . ' 09:00:00');
            $end = strtotime($date . ' 17:00:00');

            if ($start === false || $end === false) {
                fail('VALIDATION_ERROR', 'date inválido');
            }

            $dur = 30;
            $slots = [];

            for ($c = $start; $c + $dur * 60 <= $end; $c += $dur * 60) {
                $busy = false;

                foreach ($occ as $o) {
                    $fechaCita = (string)($o['fech_cita'] ?? '');

                    if ($fechaCita !== '' && strtotime($fechaCita) === $c) {
                        $busy = true;
                        break;
                    }
                }

                if (!$busy) {
                    $slots[] = [
                        'startAt' => date(DATE_ATOM, $c),
                        'endAt' => date(DATE_ATOM, $c + $dur * 60),
                    ];
                }
            }

            ok([
                'licenseId' => $lic['id'],
                'slots' => $slots,
            ]);
        }

        if ($route === 'appointments_create') {
            $in = json_in();

            require_fields($in, ['id_lice_encr', 'startAt', 'customerDocument']);

            $lic = license_or_fail((string)$in['id_lice_encr']);
            $start = parse_start_at((string)$in['startAt']);
            $doc = (string)$in['customerDocument'];

            $existing = db_select("
                SELECT TOP 1
                    id_cita,
                    estu,
                    fech_cita
                FROM {$appointmentsTable}
                WHERE id_lice = " . sql_int($lic['id']) . "
                AND cedu_paci = " . sql_str($doc) . "
                AND fech_cita = " . sql_datetime($start) . "
                ORDER BY id_cita DESC
            ");

            if ($existing) {
                ok([
                    'licenseId' => $lic['id'],
                    'appointment' => $existing[0],
                ], 'Cita existente reutilizada');
            }

            if (slot_is_occupied($lic['id'], $start, $appointmentsTable, null, $doc)) {
                fail('SLOT_NOT_AVAILABLE', 'El horario ya está ocupado', 409);
            }

            $idEmplOpto = $in['id_empl_opto'] ?? null;
            $idEmplOptoSql = $idEmplOpto === null ? 'NULL' : sql_int($idEmplOpto);

            db_exec("
                INSERT INTO {$appointmentsTable}
                    (
                        id_lice,
                        estu,
                        cedu_paci,
                        fech_cita,
                        fech_crea,
                        opto,
                        id_empl_opto
                    )
                VALUES
                    (
                        " . sql_int($lic['id']) . ",
                        " . sql_str((string)($in['status'] ?? 'pending')) . ",
                        " . sql_str($doc) . ",
                        " . sql_datetime($start) . ",
                        " . sql_datetime(new DateTimeImmutable('now')) . ",
                        " . sql_str(isset($in['opto']) ? (string)$in['opto'] : null) . ",
                        {$idEmplOptoSql}
                    )
            ");

            ok([
                'licenseId' => $lic['id'],
            ], 'Cita creada', 201);
        }

        if ($route === 'appointments_list') {
            $input = array_merge($_GET, json_in());

            $lic = license_or_fail((string)($input['id_lice_encr'] ?? ''));

            $appointments = db_select("
                SELECT
                    id_cita,
                    id_lice,
                    estu,
                    cedu_paci,
                    fech_cita,
                    fech_crea,
                    opto,
                    id_empl_opto
                FROM {$appointmentsTable}
                WHERE id_lice = " . sql_int($lic['id']) . "
                ORDER BY fech_cita ASC
            ");

            ok([
                'licenseId' => $lic['id'],
                'appointments' => $appointments,
            ]);
        }

        if ($route === 'appointments_update') {
            $in = json_in();

            require_fields($in, ['id_lice_encr', 'appointmentId', 'startAt']);

            $id = (int)($in['appointmentId'] ?? 0);

            if ($id <= 0) {
                fail('VALIDATION_ERROR', 'appointmentId inválido');
            }

            $lic = license_or_fail((string)($in['id_lice_encr'] ?? ''));

            if (!appointment_exists($id, $lic['id'], $appointmentsTable)) {
                fail('APPOINTMENT_NOT_FOUND', 'Cita no encontrada', 404);
            }

            $start = parse_start_at((string)$in['startAt']);

            if (slot_is_occupied($lic['id'], $start, $appointmentsTable, $id, null)) {
                fail('SLOT_NOT_AVAILABLE', 'El horario ya está ocupado', 409);
            }

            $idEmplOpto = $in['id_empl_opto'] ?? null;
            $idEmplOptoSql = $idEmplOpto === null ? 'NULL' : sql_int($idEmplOpto);

            db_exec("
                UPDATE {$appointmentsTable}
                SET
                    estu = " . sql_str((string)($in['status'] ?? 'pending')) . ",
                    fech_cita = " . sql_datetime($start) . ",
                    opto = " . sql_str(isset($in['opto']) ? (string)$in['opto'] : null) . ",
                    id_empl_opto = {$idEmplOptoSql}
                WHERE id_cita = " . sql_int($id) . "
                AND id_lice = " . sql_int($lic['id']) . "
            ");

            ok([
                'appointmentId' => $id,
                'licenseId' => $lic['id'],
            ], 'Cita actualizada');
        }

        if ($route === 'appointments_delete') {
            $in = json_in();

            $id = (int)($in['appointmentId'] ?? ($_GET['appointmentId'] ?? 0));

            if ($id <= 0) {
                fail('VALIDATION_ERROR', 'appointmentId inválido');
            }

            $lic = license_or_fail((string)($in['id_lice_encr'] ?? $_GET['id_lice_encr'] ?? ''));

            if (!appointment_exists($id, $lic['id'], $appointmentsTable)) {
                fail('APPOINTMENT_NOT_FOUND', 'Cita no encontrada', 404);
            }

            db_exec("
                DELETE FROM {$appointmentsTable}
                WHERE id_cita = " . sql_int($id) . "
                AND id_lice = " . sql_int($lic['id']) . "
            ");

            ok([
                'appointmentId' => $id,
                'licenseId' => $lic['id'],
            ], 'Cita eliminada');
        }

        fail('NOT_FOUND', 'Endpoint no encontrado', 404);
    } catch (Throwable $e) {
        fail('INTERNAL_ERROR', $e->getMessage(), 500);
    }
}
