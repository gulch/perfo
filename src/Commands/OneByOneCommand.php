<?php

declare(strict_types=1);

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
        $this->output_helper->outputWelcomeMessage($output, $this->getApplication());

        $requests_count = $input->getOption('requests');

        $output->writeln("Doing {$requests_count} requests one by one...");

        $timestamp = \microtime(true); // current timestamp in seconds

        $curl_handlers = [];

        for ($i = 0; $i < $requests_count; ++$i) {

            $curl_handler = new CurlHandler($input);

            $curl_handler->execute();

            $curl_handlers[$i] = $curl_handler;
        }

        $output->write("\n\n");

        $info = $curl_handlers[0]->getInfo();

        $this->output_helper->outputGeneralInfo($input, $output, $info);

        $output->writeln('Execution time: ' . sprintf('%2.3f', \microtime(true) - $timestamp) . ' seconds');

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $output->write("\n\n");

            $this->outputServerTimings($curl_handlers, $output);
        }

        $output->write("\n\n");

        $this->outputTimings($curl_handlers, $output);

        $output->write("\n\n");

        return self::SUCCESS;
    }
}
