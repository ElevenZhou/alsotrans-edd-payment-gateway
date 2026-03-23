<?php

require  __DIR__.'/Config/ATPConfig.php';
require  __DIR__.'/Payment/Iframe.php';
require  __DIR__.'/Callback/CallbackService.php';

class IframeClient
{
    public $config;
    public $iframe;
    public $callback;

    public function __construct($config,$mode = 'live')
    {
        $this->config = new ATPConfig($config,$mode);
        $this->iframe = new Iframe($this->config);
        $this->callback = new CallbackService($this->config);
    }

}