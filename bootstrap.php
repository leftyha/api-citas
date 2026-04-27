<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Booking\\';
    $baseDir = __DIR__ . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use Booking\Booking\AppointmentService;
use Booking\Booking\AppointmentValidator;
use Booking\Booking\AvailabilityService;
use Booking\Booking\LicenseService;
use Booking\Config\Config;
use Booking\Config\DeploymentGuard;
use Booking\Config\Dotenv;
use Booking\Database\DatabaseClient;
use Booking\Observability\Logger;
use Booking\Repository\AppointmentRepository;
use Booking\Repository\LicenseRepository;
use Booking\Security\AdminAuth;
use Booking\Security\AppointmentTokenService;
use Booking\Security\RateLimiter;

Dotenv::load(__DIR__ . '/.env');
$config = Config::load(__DIR__ . '/src/Config/config.php');
DeploymentGuard::assertSecure($config);
$db = new DatabaseClient($config['database']);
$licenseRepository = new LicenseRepository($db);
$appointmentRepository = new AppointmentRepository($db);
$tokenService = new AppointmentTokenService($config['security']['appointment_token']);
$validator = new AppointmentValidator($config['booking']);

return [
    'config' => $config,
    'db' => $db,
    'logger' => new Logger($config['observability'] ?? []),
    'rateLimiter' => new RateLimiter($config['security']['rate_limit'], $db),
    'adminAuth' => new AdminAuth($config['security']['admin']),
    'licenseService' => new LicenseService($licenseRepository),
    'availabilityService' => new AvailabilityService($appointmentRepository, $licenseRepository, $validator),
    'appointmentService' => new AppointmentService(
        $appointmentRepository,
        $licenseRepository,
        $tokenService,
        $validator,
        $db
    ),
];
