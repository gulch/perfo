<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function trim, count, explode;

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
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $curlHandler = new CurlHandler($input->getArgument('url'));

        $curlHandler->execute();
        
        $info = $curlHandler->getInfo();

        $headers = $curlHandler->getHeaders();

        $output->write("\n\n");

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $output->write("\n\n");
        
        $this->outputHelper->outputGeneralInfo($input, $output, $info);

        if ($input->getOption('output-headers')) {
            // sort headers array by header name
            \ksort($headers);

            $output->write("\n\n");

            $this->outputHelper->outputHeaders($output, $headers);
        }    

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $output->write("\n\n");

            $server_timing_header = isset($headers['server-timing']) ? $headers['server-timing'] : '';

            $parsed_timings = [];

            if (true == \is_array($server_timing_header)) {
                foreach ($server_timing_header as $st) {
                    $parsed_timings = [
                        ...$parsed_timings,
                        $this->parseServerTiming($st),
                    ];
                }
            } else {
                $parsed_timings = $this->parseServerTiming($server_timing_header);
            }

            $this->outputHelper->outputServerTiming($output, $parsed_timings);
        }

        $output->write("\n\n");

        $this->outputHelper->outputTiming($output, $info);

        $output->write("\n\n");

        return self::SUCCESS;
    }

    /* 
     * parse Server-Timing header(s)
     * https://www.w3.org/TR/server-timing
     * 
     * example -> Server-Timing: miss,db;dur=53,app;dur=47.2,cache;desc="Cache Read";dur=23.2
     */
    private function parseServerTiming(string $header): array
    {
        $header = trim($header);

        if ('' === $header) return [];

        $result_array = [];

        foreach (explode(',', $header) as $item) {
            $params = explode(';', $item);

            $timing['name'] = $params[0];

            for ($i = 1, $c = count($params); $i < $c; ++$i) {
                [$paramName, $paramValue] = explode('=', $params[$i]);

                $paramName = trim($paramName);

                if (!$paramName) continue;

                $paramValue = trim($paramValue);
                $paramValue = trim($paramValue, '"');

                $timing[$paramName] = $paramValue;
            }

            $result_array[] = $timing;
        }

        return $result_array;
    }
}
