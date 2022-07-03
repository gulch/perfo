<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConcurrentlyCommand extends Command
{
    private OutputHelper $outputHelper;

    protected function configure(): void
    {
        $this->setName('cc')
            ->setDescription('Concurrently Requests Performance Measure')
            ->setHelp('This command allows you to send concurrently requests to web app')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL',
            )->addOption(
                'requests',
                'r',
                InputOption::VALUE_REQUIRED,
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = [];

        $cmh = \curl_multi_init();

        $curl_handlers = [];

        $requests_count = $input->getOption('requests');

        for ($i = 0; $i < $requests_count; ++$i) {

            $curl_handlers[$i] = new CurlHandler($input->getArgument('url'));
            
            \curl_multi_add_handle(
                $cmh, 
                $curl_handlers[$i]->getHandle()
            );
        }

        do {
            \curl_multi_exec($cmh, $running);
        } while ($running > 0);

        $output->write("\n\n");

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $output->write("\n\n");

        foreach ($curl_handlers as $ch) {

            /** @var CurlHandler $ch */
            $info = $ch->getInfo();

            
            $this->outputHelper->outputGeneralInfo($input, $output, $info);
            $this->outputHelper->outputTiming($output, $info);
            $output->write("\n\n");
        }

        //print_r($headers);

        return self::SUCCESS;
    }
}
