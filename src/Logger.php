<?php

namespace crmpbx\logger;


use crmpbx\commutator\Commutator;

class Logger
{
    public Commutator $commutator;

    public string $service;
    public string $route;

    private string $companySid;
    private string $eventSid;

    private array $data;

    public function __construct($service = '', $route = '', $config = [])
    {
        $this->commutator = new Commutator($config);
        $this->service = $service;
        $this->route = self::parseRoute($route);
    }

    public function init(string $companySid, string $eventSid): void
    {
        $this->companySid = $companySid;
        $this->eventSid = $eventSid;
    }

    private static function parseRoute(string $target): string
    {
        if(str_ends_with($target, '/'))
            $target = substr($target, 0, strlen($target)-1);

        return str_replace('/', '_', $target);
    }

    public function add(string $checkpoint, array $data, array $timer = []): void
    {
        $log = ['data' => $data];
        if (count($timer))
            $log['timer'] = $timer;

        $this->data[$this->service][$this->route][$checkpoint][] = $log;
    }

    public function send(): void
    {
        $this->commutator->send('log', 'POST', '/api/log', [
            'CompanySid' => $this->companySid ?? 'base',
            'EventSid' => $this->eventSid ?? 'EV' . md5(time() . rand(0, 999)),
            'Data' => $this->data
        ]);
    }

    public function addError(\Throwable $exception): void
    {
        if ($exception->getPrevious() instanceof \Throwable)
            $this->addError($exception->getPrevious());

        $data = [
            "code" => $exception->getCode(),
            "file" => $exception->getFile(),
            "line" => $exception->getLine(),
            "name" => 'Exception',
            "time" => time(),
            "type" => $exception::class,
            "message" => $exception->getMessage(),
            "stack-trace" => preg_split('/#\d*\s/', $exception->getTraceAsString(), -1, PREG_SPLIT_NO_EMPTY),
        ];

        $this->add('exception', $data);
    }
}