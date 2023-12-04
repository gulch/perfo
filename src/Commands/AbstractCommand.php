<?php

declare(strict_types=1);

namespace Perfo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL',
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            )->addOption(
                'browser-user-agent',
                'b',
                InputOption::VALUE_NONE,
                'Set real browser user-agent',
            )->addOption(
                'force-http3',
                null,
                InputOption::VALUE_NONE,
                'Force usage of HTTP/3 protocol',
            );
    }
}
