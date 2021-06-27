<?php
declare(strict_types=1);

namespace AllenJB\Notifications;

interface NotificationFactoryInterface
{

    public static function fromThrowable(\Throwable $throwable, ?string $loggerName = null): Notification;
}
