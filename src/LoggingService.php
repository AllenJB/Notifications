<?php

namespace AllenJB\Notifications;

class LoggingService
{

    protected static $instance = null;

    protected $client;

    protected $user;

    protected $appEnvironment;

    protected $appVersion;

    protected $publicDSN = null;


    /**
     * @return static|null
     */
    public static function getInstance()
    {
        return static::$instance;
    }


    public static function setInstance(LoggingService $instance)
    {
        static::$instance = $instance;
    }


    public function __construct(string $sentryDSN, string $appEnvironment, ?string $appVersion, array $globalTags, ?string $publicDSN = null)
    {
        $this->appEnvironment = $appEnvironment;
        $this->appVersion = $appVersion;
        $this->publicDSN = $publicDSN;

        $globalTags['sapi'] = PHP_SAPI;

        $ravenOptions = [
            'release' => $appVersion,
            'environment' => $appEnvironment,
            'tags' => $globalTags,
            'processors' => [],
        ];
        $this->client = new \Raven_Client($sentryDSN, $ravenOptions);

        // Ensure the LoggingServiceEvent class is loaded - this should help prevent logging from failing in cases
        // where available memory might be low
        new LoggingServiceEvent("preloading");
    }


    public function setUser(array $user = null)
    {
        if (is_array($user)) {
            foreach ($user as $key => $value) {
                if (!is_scalar($value)) {
                    throw new \InvalidArgumentException("User data array may only contain scalar values and may not contain arrays ({$key})");
                }
            }

            if (empty($user)) {
                $user = null;
            }
        }

        $this->user = $user;
    }


    public function getUser() : ?array
    {
        return $this->user;
    }


    /**
     * @param LoggingServiceEvent $event
     * @param bool $includeSessionData Include session specific data ($_SESSION, $_REQUEST) - useful to exclude when parsing error logs
     * @return null|string Event ID (if successful)
     */
    public function send(LoggingServiceEvent $event, $includeSessionData = true)
    {
        if ($this->client === null) {
            return null;
        }

        // user is not null or empty array (we know user is either array or null)
        if (!empty($event->getUser())) {
            $this->client->user_context($event->getUser());
        }

        $data = [];
        if ($event->getTimeStamp() !== null) {
            $dt = new \DateTime($event->getTimeStamp()->format('c'));
            $dt->setTimezone(new \DateTimeZone("UTC"));
            $data['timestamp'] = $dt->format('Y-m-d\TH:i:s\Z');
        }
        if (($event->getFingerprint() ?? "") !== null) {
            $data['fingerprint'] = ['{{default}}', $event->getFingerprint()];
        }
        if ($event->getLevel() !== null) {
            $data['level'] = $event->getLevel();
        }
        if ($event->getLogger() !== null) {
            $data['logger'] = $event->getLogger();
        }
        if (!empty($this->user)) {
            $data['user'] = $this->user;
        }
        if (!empty($event->getUser())) {
            $data['user'] = $event->getUser();
        }
        if (!empty($event->getTags())) {
            $data['tags'] = $event->getTags();
        }
        if (!empty($event->getContext())) {
            $data['extra'] = $event->getContext();
        }
        $data['extra']['_SERVER'] = $_SERVER;
        if ($includeSessionData) {
            $data['extra']['_REQUEST'] = $_REQUEST;
            if (isset($_SESSION)) {
                $data['extra']['_SESSION'] = $_SESSION;
            }
        }

        if ($event->getException() !== null) {
            if ($event->getMessage() !== null) {
                $data['message'] = $event->getMessage();
            }
            $this->client->captureException($event->getException(), $data);
        } else {
            $includeStackTrace = !$event->getExcludeStackTrace();
            $this->client->captureMessage($event->getMessage(), [], $data, $includeStackTrace);
        }

        $lastError = $this->client->getLastError();
        if ($lastError !== null) {
            $msg = "Logging Service Error"
                ."\nMessage: ". $lastError;
            Notifications::email($msg, "Error Logging Service Error");
            return null;
        }

        return $this->client->getLastEventID();
    }


    public function generateBrowserJS() : string
    {
        if (($this->publicDSN ?? "") === "") {
            return "";
        }

        $loggingService = LoggingService::getInstance();
        $user = null;
        if ($loggingService !== null) {
            $user = $loggingService->getUser();
        }

        $retval = "
        <script src=\"//cdn.ravenjs.com/3.26.4/raven.min.js\" crossorigin=\"anonymous\"></script>
        <script type=\"text/javascript\">
            Raven.config('{$this->publicDSN}', {
                release: ". $this->jsonEncode($this->appVersion) .",
                environment: ". $this->jsonEncode($this->appEnvironment) .",
                serverName: ". $this->jsonEncode(gethostname()) .",
            }).install();";

        if ($user !== null) {
            $retval .= "
                Raven.setUserContext({
                    email: ". $this->jsonEncode($user["email"] ?? "") .",
                    id: ". $this->jsonEncode($user["id"] ?? "") .",
                    username: ". $this->jsonEncode($user["username"] ?? "") .",
                });";
        }

        $retval .= "
        </script>
        ";

        return $retval;
    }


    protected function jsonEncode($value, $options = JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS) : string
    {
        $retval = json_encode($value, $options);

        if ($retval === false) {
            $msg = "Failed to encode as JSON";
            if (function_exists('json_last_error_msg')) {
                $msg .= ': ' . json_last_error_msg();
            }

            throw new \InvalidArgumentException($msg, json_last_error());
        }

        return $retval;
    }

}
