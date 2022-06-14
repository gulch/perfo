<?php

namespace Perfo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_setopt, strlen, trim, count, explode, is_array;

class OneByOneCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('oo')
            ->setDescription('One By One Requests Performance Measure')
            ->setHelp('This command allows you to send requests to web app one by one')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL',
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // TODO

        return self::SUCCESS;
    }
}
