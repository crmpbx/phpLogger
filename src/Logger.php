<?php

namespace crmpbx\logger;


use crmpbx\commutator\Commutator;

class Logger
{
    private Commutator $commutator;

    private bool $isInit = false;

    public string $service;
    private string $route;

    private string $companySid;
    private string $eventSid;

    private array $data;

    public function init(Commutator $commutator, string $route, string $companySid, string $eventSid): void
    {
        $this->commutator = $commutator;
        $this->route = self::parseRoute($route);
        $this->companySid = $companySid;
        $this->eventSid = $eventSid;
        $this->isInit = true;
    }

    private static function parseRoute(string $target): string
    {
        if(str_ends_with($target, '/'))
            $target = substr($target, 0, strlen($target)-1);

        return str_replace('/', '_', $target);
    }

    public function add(string $checkpoint, array $data, array $timer = []): void
    {
        if(!$this->isInit)
            return;

        $log = ['data' => $data];
        if (count($timer))
            $log['timer'] = $timer;

        $this->data[$this->service][$this->route][$checkpoint][] = $log;
    }

    public function send(): void
    {
        if(!$this->isInit)
            return;

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