<?php
declare(strict_types=1);

use AllenJB\Notifications\ErrorHandler;
use AllenJB\Notifications\NotificationFactory;
use AllenJB\Notifications\Notifications;
use AllenJB\Notifications\Services\DumpToFile;
use AllenJB\Notifications\TriggerError;

require_once(__DIR__ .'/../../vendor/autoload.php');

$filePath = __DIR__ .'/data/'. $argv[1];
$notificationStore = new DumpToFile($filePath);

$notifications = new Notifications([$notificationStore]);
$notificationFactory = new NotificationFactory();
ErrorHandler::setup(__DIR__ . '../', $notifications, $notificationFactory);
ErrorHandler::setSoftMemoryLimitPercentage(50);
ErrorHandler::setupHandlers();

ini_set('log_errors', '0');
ini_set('memory_limit', '2M');

TriggerError::oom();

die();
