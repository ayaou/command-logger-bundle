<?php

namespace Ayaou\CommandLoggerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CommandLoggerBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}