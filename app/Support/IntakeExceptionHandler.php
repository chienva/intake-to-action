<?php

namespace App\Support;

use App\Domain\Extraction\InvalidIntakeConfiguration;
use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class IntakeExceptionHandler extends Handler
{
    public function renderForConsole($output, \Throwable $exception)
    {
        if ($exception instanceof InvalidIntakeConfiguration) {
            ($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output)->writeln($exception->getMessage());

            return;
        }
        parent::renderForConsole($output, $exception);
    }
}
