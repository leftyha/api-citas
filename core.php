<?php
declare(strict_types=1);

function cfg(string $k, $d = null)
{
    $v = getenv($k);
    return $v === false ? $d : $v;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = cfg("BOOKING_DB_DRIVER", "sqlsrv");
    $host = cfg("BOOKING_DB_HOST", "tcp:y8xavxasp6.database.windows.net");
    $port = cfg("BOOKING_DB_PORT", "1433");
    $name = cfg("BOOKING_DB_NAME", "bdoptol");
    $user = cfg("BOOKING_DB_USER", "sa");
    $pass = cfg("BOOKING_DB_PASSWORD", "");

    $dsn = $driver === "mysql"
        ? "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4"
        : "sqlsrv:Server=$host,$port;Database=$name;TrustServerCertificate=1;Encrypt=0;LoginTimeout=30";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_in(): array
{
    $raw = file_get_contents("php://input") ?: "";
    if ($raw === "") {
        return [];
    }

    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function ok($data = null, string $msg = "OK", int $code = 200): void
{
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode(["ok" => true, "message" => $msg, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit();
}

function fail(string $code, string $msg, int $http = 400, array $errors = []): void
{
    http_response_code($http);
    header("Content-Type: application/json");
    echo json_encode([
        "ok" => false,
        "code" => $code,
        "message" => $msg,
        "errors" => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function require_fields(array $input, array $fields): void
{
    foreach ($fields as $field) {
        if (empty($input[$field])) {
            fail("VALIDATION_ERROR", "$field es obligatorio");
        }
    }
}

function admin_guard(): void
{
    $auth = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
    $token = trim((string) cfg("BOOKING_ADMIN_TOKEN", ""));

    if ($token === "") {
        fail("CONFIG_ERROR", "BOOKING_ADMIN_TOKEN no configurado", 500);
    }

    if (!preg_match("/Bearer\s+(.+)/i", $auth, $m) || !hash_equals($token, trim($m[1]))) {
        fail("UNAUTHORIZED", "Token administrativo inválido", 401);
    }
}

function token_issue(int $id, string $license): string
{
    $sec = (string) cfg("BOOKING_TOKEN_SECRET", "dev-secret");
    $exp = time() + (int) cfg("BOOKING_TOKEN_TTL_SECONDS", "2592000");
    $p = base64_encode(json_encode([
        "appointmentId" => $id,
        "licenseUuid" => $license,
        "exp" => $exp,
    ]));
    $sig = hash_hmac("sha256", $p, $sec);

    return $p . "." . $sig;
}

function token_parse(string $t): array
{
    $sec = (string) cfg("BOOKING_TOKEN_SECRET", "dev-secret");
    $parts = explode(".", $t, 2);
    if (count($parts) !== 2) {
        fail("INVALID_TOKEN", "Token inválido", 401);
    }

    [$p, $sig] = $parts;
    if (!hash_equals(hash_hmac("sha256", $p, $sec), $sig)) {
        fail("INVALID_TOKEN", "Token inválido", 401);
    }

    $d = json_decode(base64_decode($p) ?: "", true);
    if (!is_array($d) || ($d["exp"] ?? 0) < time()) {
        fail("INVALID_TOKEN", "Token vencido", 401);
    }

    return $d;
}

function license_or_fail(string $uuid): array
{
    $st = db()->prepare("SELECT TOP 1 license_id, license_uuid, booking_enabled FROM booking_licenses WHERE license_uuid=:u");
    $st->execute(["u" => $uuid]);
    $r = $st->fetch();

    if (!$r) {
        fail("LICENSE_NOT_FOUND", "Licencia no encontrada", 404);
    }
    if (!(bool) $r["booking_enabled"]) {
        fail("LICENSE_INACTIVE", "Licencia inactiva", 422);
    }

    return [
        "id" => (int) $r["license_id"],
        "uuid" => (string) $r["license_uuid"],
    ];
}

function appointment_public(int $id): ?array
{
    $st = db()->prepare("SELECT TOP 1 a.*,l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id WHERE a.appointment_id=:id");
    $st->execute(["id" => $id]);
    $r = $st->fetch();

    if (!$r) {
        return null;
    }

    $doc = (string) $r["customer_document"];
    $phone = preg_replace("/\D+/", "", (string) $r["customer_phone"]) ?? "";

    return [
        "appointmentId" => (int) $r["appointment_id"],
        "licenseUuid" => (string) $r["license_uuid"],
        "status" => (string) $r["status"],
        "startAt" => date(DATE_ATOM, strtotime((string) $r["start_at"])),
        "endAt" => date(DATE_ATOM, strtotime((string) $r["end_at"])),
        "durationMinutes" => (int) $r["duration_minutes"],
        "serviceType" => $r["service_type"],
        "customer" => [
            "documentMasked" => str_repeat("*", max(0, strlen($doc) - 3)) . substr($doc, -3),
            "phoneMasked" => str_repeat("*", max(0, strlen($phone) - 3)) . substr($phone, -3),
        ],
    ];
}

function run(string $route): void
{
    try {
        if ($route === "availability") {
            $u = (string) ($_GET["licenseUuid"] ?? "");
            $date = (string) ($_GET["date"] ?? "");
            $dur = (int) ($_GET["durationMinutes"] ?? 30);
            if (!$u || !$date) {
                fail("VALIDATION_ERROR", "licenseUuid y date son obligatorios");
            }

            license_or_fail($u);
            $st = db()->prepare("SELECT start_at,end_at,status FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id WHERE l.license_uuid=:u AND CAST(a.start_at AS DATE)=:d");
            $st->execute(["u" => $u, "d" => $date]);
            $occ = $st->fetchAll();

            $dw = (int) date("N", strtotime($date));
            if ($dw === 7) {
                ok(["slots" => []]);
            }

            $start = strtotime($date . " 09:00:00");
            $end = strtotime($date . ($dw === 6 ? " 13:00:00" : " 17:00:00"));
            $slots = [];
            for ($c = $start; $c + $dur * 60 <= $end; $c += $dur * 60) {
                $ce = $c + $dur * 60;
                $busy = false;

                foreach ($occ as $o) {
                    if (!in_array($o["status"], ["pending", "confirmed"], true)) {
                        continue;
                    }

                    $os = strtotime((string) $o["start_at"]);
                    $oe = strtotime((string) $o["end_at"]);
                    if ($c < $oe && $ce > $os) {
                        $busy = true;
                        break;
                    }
                }

                if (!$busy) {
                    $slots[] = [
                        "startAt" => date(DATE_ATOM, $c),
                        "endAt" => date(DATE_ATOM, $ce),
                    ];
                }
            }

            ok(["slots" => $slots]);
        }

        if ($route === "appointments_create") {
            $in = json_in();
            require_fields($in, ["licenseUuid", "startAt", "customerDocument", "customerName", "customerPhone"]);

            $lic = license_or_fail((string) $in["licenseUuid"]);
            $dur = (int) ($in["durationMinutes"] ?? 30);
            $start = new DateTimeImmutable((string) $in["startAt"]);
            $end = $start->modify("+$dur minutes");

            $pdo = db();
            $pdo->beginTransaction();

            $c = $pdo->prepare("SELECT TOP 1 appointment_id,customer_document FROM booking_appointments WHERE license_id=:l AND start_at=:s AND status IN ('pending','confirmed')");
            $c->execute(["l" => $lic["id"], "s" => $start->format("Y-m-d H:i:sP")]);
            $ex = $c->fetch();

            if ($ex) {
                if (strtolower((string) $ex["customer_document"]) === strtolower((string) $in["customerDocument"])) {
                    $id = (int) $ex["appointment_id"];
                } else {
                    $pdo->rollBack();
                    fail("SLOT_CONFLICT", "Horario no disponible", 409);
                }
            } else {
                $q = $pdo->prepare("INSERT INTO booking_appointments (license_id,customer_document,customer_name,customer_phone,customer_email,start_at,end_at,duration_minutes,service_type,professional_id,notes,status) OUTPUT INSERTED.appointment_id VALUES (:l,:d,:n,:p,:e,:s,:en,:du,:st,:pr,:no,'pending')");
                $q->execute([
                    "l" => $lic["id"],
                    "d" => trim((string) $in["customerDocument"]),
                    "n" => trim((string) $in["customerName"]),
                    "p" => trim((string) $in["customerPhone"]),
                    "e" => $in["customerEmail"] ?? null,
                    "s" => $start->format("Y-m-d H:i:sP"),
                    "en" => $end->format("Y-m-d H:i:sP"),
                    "du" => $dur,
                    "st" => $in["serviceType"] ?? null,
                    "pr" => $in["professionalId"] ?? null,
                    "no" => $in["notes"] ?? null,
                ]);
                $id = (int) $q->fetchColumn();
            }

            $pdo->commit();
            $tok = token_issue($id, $lic["uuid"]);

            ok([
                "appointment" => [
                    "appointmentToken" => $tok,
                    "startAt" => $start->format(DATE_ATOM),
                    "endAt" => $end->format(DATE_ATOM),
                    "durationMinutes" => $dur,
                    "status" => "pending",
                ],
            ], "Cita creada", 201);
        }

        if ($route === "appointments_public_get") {
            $in = json_in();
            $t = (string) ($_GET["appointmentToken"] ?? ($in["appointmentToken"] ?? ""));
            if (!$t) {
                fail("VALIDATION_ERROR", "appointmentToken obligatorio");
            }

            $p = token_parse($t);
            $a = appointment_public((int) $p["appointmentId"]);
            if (!$a || $a["licenseUuid"] !== $p["licenseUuid"]) {
                fail("APPOINTMENT_NOT_FOUND", "Cita no encontrada", 404);
            }

            $a["appointmentToken"] = $t;
            ok(["appointment" => $a]);
        }

        if (strpos($route, "admin_") === 0) {
            admin_guard();
            $pdo = db();

            if ($route === "admin_appointments_list") {
                $w = [];
                $pa = [];
                foreach ([
                    "date" => "CAST(a.start_at AS DATE)=:date",
                    "status" => "a.status=:status",
                    "professionalId" => "a.professional_id=:professionalId",
                    "customerDocument" => "a.customer_document=:customerDocument",
                ] as $k => $sql) {
                    if (!empty($_GET[$k])) {
                        $w[] = $sql;
                        $pa[$k] = $_GET[$k];
                    }
                }

                $sql = "SELECT a.*,l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id"
                    . ($w ? " WHERE " . implode(" AND ", $w) : "")
                    . " ORDER BY a.start_at ASC";
                $st = $pdo->prepare($sql);
                $st->execute($pa);
                ok(["appointments" => $st->fetchAll()]);
            }

            if ($route === "admin_appointments_get") {
                $id = (int) ($_GET["appointmentId"] ?? 0);
                if ($id <= 0) {
                    fail("VALIDATION_ERROR", "appointmentId inválido");
                }

                $st = $pdo->prepare("SELECT TOP 1 a.*,l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id WHERE a.appointment_id=:id");
                $st->execute(["id" => $id]);
                $r = $st->fetch();
                if (!$r) {
                    fail("APPOINTMENT_NOT_FOUND", "Cita no encontrada", 404);
                }

                ok(["appointment" => $r]);
            }

            if ($route === "admin_appointments_update") {
                $in = json_in();
                $id = (int) ($in["appointmentId"] ?? 0);
                if ($id <= 0) {
                    fail("VALIDATION_ERROR", "appointmentId inválido");
                }
                if (isset($in["status"])) {
                    fail("VALIDATION_ERROR", "status no permitido aquí");
                }

                $fields = [];
                $pa = ["id" => $id];
                foreach ([
                    "startAt" => "start_at",
                    "durationMinutes" => "duration_minutes",
                    "serviceType" => "service_type",
                    "professionalId" => "professional_id",
                    "customerDocument" => "customer_document",
                    "customerName" => "customer_name",
                    "customerPhone" => "customer_phone",
                    "customerEmail" => "customer_email",
                    "notes" => "notes",
                ] as $k => $col) {
                    if (array_key_exists($k, $in)) {
                        $fields[] = "$col=:$k";
                        $pa[$k] = $in[$k];
                    }
                }

                if (!$fields) {
                    fail("VALIDATION_ERROR", "Sin campos para actualizar");
                }

                $sql = "UPDATE booking_appointments SET " . implode(",", $fields) . " WHERE appointment_id=:id";
                $pdo->prepare($sql)->execute($pa);
                ok(["appointmentId" => $id], "Cita actualizada");
            }

            if ($route === "admin_appointments_confirm" || $route === "admin_appointments_cancel") {
                $in = json_in();
                $id = (int) ($in["appointmentId"] ?? 0);
                if ($id <= 0) {
                    fail("VALIDATION_ERROR", "appointmentId inválido");
                }

                $to = $route === "admin_appointments_confirm" ? "confirmed" : "cancelled";
                $pdo->prepare("UPDATE booking_appointments SET status=:s WHERE appointment_id=:id")
                    ->execute(["s" => $to, "id" => $id]);
                ok(["appointmentId" => $id, "status" => $to], "Estado actualizado");
            }
        }

        fail("NOT_FOUND", "Endpoint no encontrado", 404);
    } catch (Throwable $e) {
        fail("INTERNAL_ERROR", $e->getMessage(), 500);
    }
}
