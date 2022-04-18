<?php

namespace crmpbx\logger;


use crmpbx\commutator\Commutator;

class Logger
{
    public \Closure $callback;

    public string $service;
    private string $route;

    private string $companySid;
    private string $eventSid;

    private array $data = [];
    private array $fileData = [];

    public function __construct(string $service = null, \Closure $callback = null)
    {
        if ($service)
            $this->service = $service;
        if ($callback)
            $this->callback = $callback;

        $this->eventSid = 'EV'.md5(time().rand(0,999));
        $this->companySid = 'CO'.str_repeat('0', 32);
        $this->route = self::parseRoute($_SERVER['REQUEST_URI']);
    }

    public function init(string $route, string $companySid, string $eventSid = null): void
    {
        $this->route = self::parseRoute($route);
        $this->companySid = $companySid;
        if($eventSid)
            $this->eventSid = $eventSid;
    }

    private function getCommutator(): Commutator
    {
        $commutator = call_user_func($this->callback);
        if($commutator instanceof Commutator)
            return $commutator;

        //TODO Throw Exception
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
        if($data instanceof \Throwable)
            $data = $this->mapException($data);

        $data = ['data' => $data];
        if (count($timer))
            $data['timer'] = $timer;

        $this->data[$this->service][$this->route][$checkpoint][] = $data;
    }

    public function addInFile(string $event, array|\Throwable $data, $asArray = false): void
    {
        if ($data instanceof \Throwable)
            $data = self::mapException($data);

        if ($asArray)
            $this->fileData[$event][] = $data;
        else
            $this->fileData[$event] = $data;
    }

    public function writeInFileSystem(string $format = 'json'): void
    {
        if(empty($this->fileData))
            return;

        $format = '.'.$format
        $dir = '../runtime/logs/'.$this->companySid;
        if (!is_dir($dir))
            mkdir($dir);

        $logData = file_exists($dir.'/'.$this->eventSid.$format)
            ? json_decode(file_get_contents($dir.'/'.$this->eventSid.$format), true)
            : [];

        $logData[$this->service][$this->route][] = $this->fileData;

        file_put_contents($dir.'/'.$this->eventSid.$format, json_encode($logData));
    }

    private function mapException(\Throwable $e): array
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
        $this->getCommutator()->send('log', 'POST', '/api/log', [
            'CompanySid' => $this->companySid ?? 'base',
            'EventSid' => $this->eventSid ?? 'EV' . md5(time() . rand(0, 999)),
            'Data' => $this->data
        ]);
    }
}
