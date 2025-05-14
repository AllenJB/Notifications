<?php
declare(strict_types=1);

namespace AllenJB\Notifications;

class NotificationFactory implements NotificationFactoryInterface
{
    protected static function levelFromPhpSeverity(int $severity): string
    {
        $levels = [
            E_STRICT => 'warning',
            E_NOTICE => 'warning',
            E_WARNING => 'warning',
            E_USER_NOTICE => 'warning',
            E_USER_WARNING => 'warning',
        ];
        if (defined('E_STRICT')) {
            $levels[E_STRICT] = 'warning';
        }

        return ($levels[$severity] ?? 'error');
    }

    public static function fromThrowable(\Throwable $throwable, ?string $loggerName = null): Notification
    {
        $nLevel = "error";
        if ($throwable instanceof \ErrorException) {
            $nLevel = self::levelFromPhpSeverity($throwable->getSeverity());
        }
        return new Notification($nLevel, ($loggerName ?? "None"), null, $throwable);
    }
}
