<?php
declare(strict_types=1);

namespace AllenJB\Notifications;

interface LoggingServiceInterface
{

    /**
     * @param LoggingServiceEvent $event
     * @param bool $includeSessionData Include session specific data ($_SESSION, $_REQUEST) - useful to exclude when parsing error logs
     * @return bool Successful?
     */
    public function send(LoggingServiceEvent $event, $includeSessionData = true): bool;

}
