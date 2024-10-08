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
                'content-encoding',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Set Content-Encoding',
                'gzip, deflate, br, zstd',
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            )->addOption(
                'browser-user-agent',
                'b',
                InputOption::VALUE_NONE,
                'Set real browser User-Agent',
            )->addOption(
                'http1',
                null,
                InputOption::VALUE_NONE,
                'Send request via HTTP 1.1 protocol',
            )->addOption(
                'http2',
                null,
                InputOption::VALUE_NONE,
                'Send request via HTTP/2 protocol',
            )->addOption(
                'http3',
                null,
                InputOption::VALUE_NONE,
                'Send request via HTTP/3 protocol',
            )->addOption(
                'detail',
                null,
                InputOption::VALUE_NONE,
                'Show more details',
            )->addOption(
                'reuse',
                null,
                InputOption::VALUE_NONE,
                'Reuse connection',
            );
    }
}
