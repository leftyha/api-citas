<?php
declare(strict_types=1);
require __DIR__ . '/api_core.php';
env_load(__DIR__ . '/.env');
cors();

$action = (string) query('action', '');

try {
    if ($action === 'availability') {
        if (method() !== 'GET') fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
        rate_limit('public:availability');
        $license = license_or_fail((string) query('licenseUuid', ''));
        $date = (string) query('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail('VALIDATION_ERROR','date inválida.',400,['field'=>'date']);
        $duration = max(5, (int) query('durationMinutes', 30));
        $busyQ = db()->prepare('SELECT start_at,end_at FROM booking_appointments WHERE license_id = ? AND DATE(start_at)=? AND status IN ("pending","confirmed")');
        $busyQ->execute([(int)$license['license_id'], $date]); $busy = $busyQ->fetchAll();
        $start = new DateTimeImmutable($date . ' ' . cfg('DAY_START', '08:00:00'));
        $end = new DateTimeImmutable($date . ' ' . cfg('DAY_END', '18:00:00'));
        $slots = [];
        for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+' . $duration . ' minutes')) {
            $slotEnd = $cursor->modify('+' . $duration . ' minutes');
            if ($slotEnd > $end) break;
            $free = true;
            foreach ($busy as $b) {
                $bStart = new DateTimeImmutable((string)$b['start_at']);
                $bEnd = new DateTimeImmutable((string)$b['end_at']);
                if (!($slotEnd <= $bStart || $cursor >= $bEnd)) { $free = false; break; }
            }
            if ($free) $slots[] = ['startAt'=>$cursor->format(DateTime::ATOM), 'endAt'=>$slotEnd->format(DateTime::ATOM)];
        }
        ok(['date'=>$date, 'durationMinutes'=>$duration, 'slots'=>$slots], 'Disponibilidad obtenida correctamente.');
    }

    if ($action === 'appointments.create') {
        if (method() !== 'POST') fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
        rate_limit('public:appointments_create');
        $in = json_input();
        foreach (['licenseUuid','startAt','customerDocument','customerName','customerPhone'] as $f) if (empty($in[$f])) fail('VALIDATION_ERROR', $f . ' es requerido.',400,['field'=>$f]);
        $license = license_or_fail((string)$in['licenseUuid']);
        $duration = max(5, (int)($in['durationMinutes'] ?? 30));
        $startAt = new DateTimeImmutable((string)$in['startAt']);
        $endAt = $startAt->modify('+' . $duration . ' minutes');

        $db = db();
        $db->beginTransaction();
        $lock = $db->prepare('SELECT appointment_id, customer_document FROM booking_appointments WHERE license_id=? AND start_at=? AND status IN ("pending","confirmed") LIMIT 1');
        $lock->execute([(int)$license['license_id'], $startAt->format('Y-m-d H:i:s')]);
        $exists = $lock->fetch();
        if ($exists && strtolower((string)$exists['customer_document']) !== strtolower((string)$in['customerDocument'])) { $db->rollBack(); fail('SLOT_CONFLICT','El horario seleccionado ya no está disponible.',409); }
        if ($exists) $appointmentId = (int)$exists['appointment_id']; else {
            $ins = $db->prepare('INSERT INTO booking_appointments (license_id,customer_document,customer_name,customer_phone,customer_email,start_at,end_at,duration_minutes,service_type,professional_id,notes,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,"pending")');
            $ins->execute([(int)$license['license_id'], trim((string)$in['customerDocument']), trim((string)$in['customerName']), trim((string)$in['customerPhone']), $in['customerEmail'] ?? null, $startAt->format('Y-m-d H:i:s'), $endAt->format('Y-m-d H:i:s'), $duration, $in['serviceType'] ?? null, isset($in['professionalId']) ? (int)$in['professionalId'] : null, $in['notes'] ?? null]);
            $appointmentId = (int)$db->lastInsertId();
        }
        $db->commit();
        $token = issue_token($appointmentId, (string)$license['license_uuid']);
        ok(['appointment'=>['appointmentToken'=>$token,'startAt'=>$startAt->format(DateTime::ATOM),'endAt'=>$endAt->format(DateTime::ATOM),'durationMinutes'=>$duration,'serviceType'=>$in['serviceType'] ?? null,'status'=>'pending']], 'Cita creada correctamente.', 201);
    }

    if ($action === 'appointments.get') {
        if (method() !== 'GET') fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
        $token = (string) query('token', '');
        if ($token === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)$/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) $token = trim($m[1]);
        $p = parse_token($token);
        $st = db()->prepare('SELECT a.*, l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id WHERE a.appointment_id=? LIMIT 1');
        $st->execute([(int)$p['appointmentId']]); $a = $st->fetch();
        if (!$a || (string)$a['license_uuid'] !== (string)$p['licenseUuid']) fail('APPOINTMENT_NOT_FOUND','No se encontró una cita válida.',404);
        ok(['appointment'=>['appointmentToken'=>$token,'startAt'=>$a['start_at'],'endAt'=>$a['end_at'],'durationMinutes'=>(int)$a['duration_minutes'],'serviceType'=>$a['service_type'],'status'=>$a['status']]], 'Cita obtenida correctamente.');
    }

    if (strpos($action, 'admin.appointments.') === 0) {
        admin_auth();
        if ($action === 'admin.appointments.list' && method() === 'GET') {
            $where = []; $params = [];
            if (($d = (string)query('date', '')) !== '') { $where[]='DATE(a.start_at)=?'; $params[]=$d; }
            if (($s = (string)query('status', '')) !== '') { $where[]='a.status=?'; $params[]=normalize_status($s); }
            if (($p = (string)query('professionalId', '')) !== '') { $where[]='a.professional_id=?'; $params[]=(int)$p; }
            if (($cd = (string)query('customerDocument', '')) !== '') { $where[]='a.customer_document=?'; $params[]=$cd; }
            $sql = 'SELECT a.*, l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.start_at ASC';
            $st = db()->prepare($sql); $st->execute($params); ok(['appointments'=>$st->fetchAll()], 'Listado de citas obtenido correctamente.');
        }
        if ($action === 'admin.appointments.get' && method() === 'GET') {
            $id = (int) query('appointmentId', 0); if ($id <= 0) fail('VALIDATION_ERROR','appointmentId inválido.',400,['field'=>'appointmentId']);
            $st = db()->prepare('SELECT a.*, l.license_uuid FROM booking_appointments a JOIN booking_licenses l ON l.license_id=a.license_id WHERE a.appointment_id=? LIMIT 1');
            $st->execute([$id]); $row = $st->fetch(); if (!$row) fail('APPOINTMENT_NOT_FOUND','Cita no encontrada.',404); ok($row, 'Cita obtenida correctamente.');
        }
        if ($action === 'admin.appointments.update' && method() === 'PUT') {
            $id = (int) query('appointmentId', 0); $in = json_input(); if ($id <= 0) fail('VALIDATION_ERROR','appointmentId inválido.',400,['field'=>'appointmentId']);
            if (isset($in['status'])) fail('VALIDATION_ERROR','status no se actualiza por este endpoint.',400,['field'=>'status']);
            $map = ['start_at'=>'startAt','duration_minutes'=>'durationMinutes','service_type'=>'serviceType','professional_id'=>'professionalId','notes'=>'notes','customer_name'=>'customerName','customer_phone'=>'customerPhone','customer_email'=>'customerEmail'];
            $set=[];$params=[]; foreach($map as $col=>$k){ if(array_key_exists($k,$in)){ $set[]="$col=?"; $params[]=$in[$k]; }}
            if (!$set) fail('VALIDATION_ERROR','No hay campos para actualizar.',400);
            $params[] = $id; $st = db()->prepare('UPDATE booking_appointments SET ' . implode(',', $set) . ' WHERE appointment_id=?'); $st->execute($params);
            ok(['appointmentId'=>$id], 'Cita actualizada correctamente.');
        }
        if (($action === 'admin.appointments.confirm' || $action === 'admin.appointments.cancel') && method() === 'POST') {
            $id = (int) query('appointmentId', 0); if ($id <= 0) fail('VALIDATION_ERROR','appointmentId inválido.',400,['field'=>'appointmentId']);
            $status = $action === 'admin.appointments.confirm' ? 'confirmed' : 'cancelled';
            $st = db()->prepare('UPDATE booking_appointments SET status=? WHERE appointment_id=?'); $st->execute([$status,$id]);
            ok(['appointmentId'=>$id,'status'=>$status], 'Estado actualizado correctamente.');
        }
        fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
    }

    fail('NOT_FOUND','Ruta no encontrada.',404);
} catch (Throwable $e) {
    fail('INTERNAL_ERROR', 'Error interno.', 500, ['detail'=>$e->getMessage()]);
}
