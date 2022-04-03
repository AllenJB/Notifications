<?php
declare(strict_types=1);

namespace AllenJB\Notifications;

interface LoggingServiceInterface
{

    /**
     * @return bool Successful?
     */
    public function send(Notification $notification): bool;

}
