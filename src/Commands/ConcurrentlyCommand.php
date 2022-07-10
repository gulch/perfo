<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Commands\AbstractManyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConcurrentlyCommand extends AbstractManyCommand
{
    protected function configure(): void
    {
        $this->setName('cc')
            ->setDescription('Concurrently Requests Performance Measure')
            ->setHelp('This command allows you to send concurrently requests to web app');
        
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requests_count = $input->getOption('requests');

        $cmh = \curl_multi_init();

        $curl_handlers = [];

        for ($i = 0; $i < $requests_count; ++$i) {

            $curl_handlers[$i] = new CurlHandler($input->getArgument('url'));

            \curl_multi_add_handle(
                $cmh,
                $curl_handlers[$i]->getHandle()
            );
        }

        $output->write("\n\n");

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $output->write("\n\n");

        $output->writeln("Doing {$requests_count} concurrent requests...");

        $timestamp = \microtime(true); // current timestamp in seconds

        do {
            \curl_multi_exec($cmh, $running);
        } while ($running > 0);

        $output->writeln('Execution time: ' . sprintf('%2.3f', \microtime(true) - $timestamp) . ' seconds');

        $output->write("\n\n");

        $this->outputTimings($curl_handlers, $output);

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $output->write("\n\n");

            $this->outputServerTimings($curl_handlers, $output);
        }

        $output->write("\n\n");

        return self::SUCCESS;
    }
}
