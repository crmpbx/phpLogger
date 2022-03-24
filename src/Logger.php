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

    public function setEventSid($sid)
    {
        $this->eventSid = $sid;
    }

    private static function parseRoute(string $target): string
    {
        if (str_ends_with($target, '/'))
            $target = substr($target, 0, strlen($target)-1);

        return str_replace('/', '_', $target);
    }

    public function add(string $checkpoint, array|\Throwable $data, array $timer = []): void
    {
        if (!$this->isInit)
            return;

        if($data instanceof \Throwable)
            $data = $this->mapException($data);

        $log = ['data' => $data];
        if (count($timer))
            $log['timer'] = $timer;

        $this->data[$this->service][$this->route][$checkpoint][] = $log;
    }

    public function addInFile(string $event, array|\Throwable $data):void
    {
        $dir = '../runtime/logs/'.$this->companySid;
        if (!is_dir($dir))
            mkdir($dir);

        $logData = file_exists($dir.'/'.$this->eventSid.'.txt')
            ? json_decode(file_get_contents($dir.'/'.$this->eventSid.'.txt'))
            : [];

        if ($data instanceof \Throwable)
            $data = self::mapException($data);

        $logData[$this->route][$event][] = $data;

        file_put_contents($dir.'/'.$this->eventSid.'.txt', json_encode($logData));
    }

    private function mapException(\Throwable $e)
    {
        if ($e->getPrevious() instanceof \Throwable)
            $this->mapException($e->getPrevious());

        return [
            "code" => $e->getCode(),
            "file" => $e->getFile(),
            "line" => $e->getLine(),
            "name" => 'Exception',
            "time" => time(),
            "type" => $e::class,
            "message" => $e->getMessage(),
            "stack-trace" => preg_split('/#\d*\s/', $e->getTraceAsString(), -1, PREG_SPLIT_NO_EMPTY),
        ];
    }

    public function send(): void
    {
        if (!$this->isInit)
            return;

        $this->commutator->send('log', 'POST', '/api/log', [
            'CompanySid' => $this->companySid ?? 'base',
            'EventSid' => $this->eventSid ?? 'EV' . md5(time() . rand(0, 999)),
            'Data' => $this->data
        ]);
    }
}