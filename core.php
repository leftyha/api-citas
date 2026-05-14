<?php
declare(strict_types=1);

require_once __DIR__ . '/azure_sql_helper.php';

function cfg(string $k, $d = null) { $v = getenv($k); return $v === false ? $d : $v; }
function db(): PDO {
    static $pdo = null; if ($pdo instanceof PDO) return $pdo;
    $host=(string)cfg('BOOKING_DB_HOST',DB_SERVER_NAME); $port=(string)cfg('BOOKING_DB_PORT','1433');
    $name=(string)cfg('BOOKING_DB_NAME',DB_DATABASE_NAME); $user=(string)cfg('BOOKING_DB_USER',getenv('DB_USERNAME')?:'sa'); $pass=(string)cfg('BOOKING_DB_PASSWORD',getenv('DB_PASSWORD')?:'');
    $server = strpos($host, ',') !== false ? $host : ($host . ',' . $port);
    $pdo = new PDO("sqlsrv:Server=$server;Database=$name;TrustServerCertificate=1;Encrypt=0",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    return $pdo;
}
function json_in(): array { $d=json_decode(file_get_contents('php://input')?:'',true); return is_array($d)?$d:[]; }
function ok($data=null,string $msg='OK',int $code=200): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'message'=>$msg,'data'=>$data],JSON_UNESCAPED_UNICODE); exit(); }
function fail(string $code,string $msg,int $http=400,array $errors=[]): void { http_response_code($http); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'code'=>$code,'message'=>$msg,'errors'=>$errors],JSON_UNESCAPED_UNICODE); exit(); }
function require_fields(array $input,array $fields): void { foreach($fields as $f){ if(empty($input[$f])) fail('VALIDATION_ERROR',"$f es obligatorio"); } }

function license_or_fail(string $idLiceEncr = ''): array {
    if ($idLiceEncr === '') fail('VALIDATION_ERROR', 'id_lice_encr es obligatorio');
    $table = (string) cfg('BOOKING_TABLE_LICENSES', 't_licencia');
    $safe = str_replace("'", "''", $idLiceEncr);
    $rows = ejecutarQueryAzureSQLServerV2("SELECT TOP 1 id_lice, id_lice_encr, ISNULL(booking_enabled,1) AS booking_enabled FROM {$table} WHERE id_lice_encr = '{$safe}'", true);
    if (!$rows) fail('LICENSE_NOT_FOUND','Licencia no encontrada',404);
    $r = (array) $rows[0];
    if (!(bool)($r['booking_enabled'] ?? 1)) fail('LICENSE_INACTIVE','Licencia inactiva',422);
    return ['id'=>(int)$r['id_lice'],'id_lice_encr'=>(string)($r['id_lice_encr'] ?? $idLiceEncr)];
}

function run(string $route): void {
    $appointmentsTable = (string) cfg('BOOKING_TABLE_APPOINTMENTS', 't_cita');
    try {
        if ($route === 'availability') {
            $encr=(string)($_GET['id_lice_encr'] ?? ''); $date=(string)($_GET['date'] ?? '');
            if (!$date) fail('VALIDATION_ERROR','date es obligatorio');
            $lic=license_or_fail($encr);

            $st=db()->prepare("SELECT fech_cita FROM {$appointmentsTable} WHERE id_lice=:l AND CAST(fech_cita AS DATE)=:d");
            $st->execute(['l'=>$lic['id'],'d'=>$date]); $occ=$st->fetchAll();

            $start=strtotime($date.' 09:00:00'); $end=strtotime($date.' 17:00:00'); $dur=30; $slots=[];
            for($c=$start;$c+$dur*60<=$end;$c+=$dur*60){
                $busy=false;
                foreach($occ as $o){ if(strtotime((string)$o['fech_cita'])===$c){$busy=true;break;} }
                if(!$busy)$slots[]=['startAt'=>date(DATE_ATOM,$c),'endAt'=>date(DATE_ATOM,$c+$dur*60)];
            }
            ok(['licenseId'=>$lic['id'],'slots'=>$slots]);
        }

        if ($route === 'appointments_create') {
            $in=json_in(); require_fields($in,['id_lice_encr','startAt','customerDocument']);
            $lic=license_or_fail((string)$in['id_lice_encr']);
            $start=new DateTimeImmutable((string)$in['startAt']);
            $doc=(string)$in['customerDocument'];

            // idempotencia exacta: misma licencia + documento + horario
            $st=db()->prepare("SELECT TOP 1 id_cita, estu, fech_cita FROM {$appointmentsTable} WHERE id_lice=:l AND cedu_paci=:d AND fech_cita=:f ORDER BY id_cita DESC");
            $st->execute(['l'=>$lic['id'],'d'=>$doc,'f'=>$start->format('Y-m-d H:i:s')]);
            $existing=$st->fetch();
            if ($existing) ok(['licenseId'=>$lic['id'],'appointment'=>$existing],'Cita existente reutilizada');

            // no duplicidad de horario para otro documento
            $st2=db()->prepare("SELECT TOP 1 id_cita FROM {$appointmentsTable} WHERE id_lice=:l AND fech_cita=:f AND cedu_paci<>:d");
            $st2->execute(['l'=>$lic['id'],'f'=>$start->format('Y-m-d H:i:s'),'d'=>$doc]);
            if ($st2->fetch()) fail('SLOT_NOT_AVAILABLE','El horario ya está ocupado',409);

            $q=db()->prepare("INSERT INTO {$appointmentsTable} (id_lice, estu, cedu_paci, fech_cita, fech_crea, opto, id_empl_opto) VALUES (:l,:e,:d,:fc,:fr,:o,:io)");
            $q->execute([
                'l'=>$lic['id'],
                'e'=>(string)($in['status'] ?? 'pending'),
                'd'=>$doc,
                'fc'=>$start->format('Y-m-d H:i:s'),
                'fr'=>(new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                'o'=>$in['opto'] ?? null,
                'io'=>$in['id_empl_opto'] ?? null,
            ]);
            ok(['licenseId'=>$lic['id']], 'Cita creada', 201);
        }

        if ($route === 'appointments_list') {
            $input = array_merge($_GET, json_in());
            $lic = license_or_fail((string)($input['id_lice_encr'] ?? ''));
            $st = db()->prepare("SELECT id_cita, id_lice, estu, cedu_paci, fech_cita, fech_crea, opto, id_empl_opto FROM {$appointmentsTable} WHERE id_lice=:l ORDER BY fech_cita ASC");
            $st->execute(['l'=>$lic['id']]);
            ok(['licenseId'=>$lic['id'], 'appointments'=>$st->fetchAll()]);
        }

        if ($route === 'appointments_update') {
            $in = json_in();
            $id = (int)($in['appointmentId'] ?? 0);
            if ($id <= 0) fail('VALIDATION_ERROR','appointmentId inválido');
            $lic = license_or_fail((string)($in['id_lice_encr'] ?? ''));

            $st = db()->prepare("SELECT TOP 1 id_cita FROM {$appointmentsTable} WHERE id_cita=:id AND id_lice=:l");
            $st->execute(['id'=>$id, 'l'=>$lic['id']]);
            if (!$st->fetch()) fail('APPOINTMENT_NOT_FOUND','Cita no encontrada',404);

            db()->prepare("UPDATE {$appointmentsTable} SET estu=:e, fech_cita=:fc, opto=:o, id_empl_opto=:io WHERE id_cita=:id AND id_lice=:l")
                ->execute([
                    'e'=>$in['status'] ?? 'pending',
                    'fc'=>(new DateTimeImmutable((string)($in['startAt'] ?? 'now')))->format('Y-m-d H:i:s'),
                    'o'=>$in['opto'] ?? null,
                    'io'=>$in['id_empl_opto'] ?? null,
                    'id'=>$id,
                    'l'=>$lic['id'],
                ]);
            ok(['appointmentId'=>$id, 'licenseId'=>$lic['id']], 'Cita actualizada');
        }

        if ($route === 'appointments_delete') {
            $in = json_in();
            $id = (int)($in['appointmentId'] ?? ($_GET['appointmentId'] ?? 0));
            if ($id <= 0) fail('VALIDATION_ERROR','appointmentId inválido');
            $lic = license_or_fail((string)($in['id_lice_encr'] ?? $_GET['id_lice_encr'] ?? ''));

            $st = db()->prepare("DELETE FROM {$appointmentsTable} WHERE id_cita=:id AND id_lice=:l");
            $st->execute(['id'=>$id, 'l'=>$lic['id']]);
            if ($st->rowCount() === 0) fail('APPOINTMENT_NOT_FOUND','Cita no encontrada',404);
            ok(['appointmentId'=>$id, 'licenseId'=>$lic['id']], 'Cita eliminada');
        }

        fail('NOT_FOUND','Endpoint no encontrado',404);
    } catch (Throwable $e) { fail('INTERNAL_ERROR',$e->getMessage(),500); }
}
