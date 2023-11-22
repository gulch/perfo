<?php

declare(strict_types=1);

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Perfo\Parsers\ServerTimingHeaderParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OneCommand extends Command
{
    private OutputHelper $outputHelper;

    protected function configure(): void
    {
        $this->setName('one')
            ->setDescription('One Request Performance Measure')
            ->setHelp('This command allows you to send request to web app')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL',
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            )->addOption(
                'output-headers',
                'z',
                InputOption::VALUE_NONE,
            )->addOption(
                'force-http3',
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $curl_handler = new CurlHandler($input);

        $curl_handler->execute();
        
        $headers = $curl_handler->getHeaders();

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $info = $curl_handler->getInfo();
        
        $this->outputHelper->outputGeneralInfo($input, $output, $info);

        if ($input->getOption('output-headers')) {
            
            \ksort($headers); // sort headers array by header name

            $output->write("\n\n");

            $this->outputHelper->outputHeaders($output, $headers);
        }    

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $output->write("\n\n");

            $server_timing_header = isset($headers['server-timing']) ? $headers['server-timing'] : '';

            $parsed_timings = [];

            $parser = new ServerTimingHeaderParser();

            if (true == \is_array($server_timing_header)) {
                foreach ($server_timing_header as $st) {
                    $parsed_timings = [
                        ...$parsed_timings,
                        $parser->parse($st),
                    ];
                }
            } else {
                $parsed_timings = $parser->parse($server_timing_header);
            }

            $this->outputHelper->outputServerTiming($output, $parsed_timings);
        }

        $output->write("\n\n");

        $this->outputHelper->outputTiming($output, $info);

        $output->write("\n\n");

        return self::SUCCESS;
    }
}
