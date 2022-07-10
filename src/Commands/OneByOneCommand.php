<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OneByOneCommand extends AbstractManyCommand
{
    protected function configure(): void
    {
        $this->setName('oo')
            ->setDescription('One By One Requests Performance Measure')
            ->setHelp('This command allows you to send requests to web app one by one');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write("\n\n");

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $output->write("\n\n");

        $requests_count = $input->getOption('requests');

        $output->writeln("Doing {$requests_count} requests one by one...");

        $timestamp = \microtime(true); // current timestamp in seconds

        $curl_handlers = [];

        for ($i = 0; $i < $requests_count; ++$i) {
            
            $handler = new CurlHandler($input->getArgument('url'));
            
            $handler->execute();

            $curl_handlers[$i] = $handler;
        }

        $output->writeln('Execution time: ' . sprintf('%2.3f', \microtime(true) - $timestamp) . ' seconds');

        $output->write("\n\n");

        $this->outputTimings($curl_handlers, $output);

        $output->write("\n\n");

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $this->outputServerTimings($curl_handlers, $output);
        }

        $output->write("\n\n");

        return self::SUCCESS;
    }
}
