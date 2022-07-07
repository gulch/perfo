<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Perfo\Helpers\StatHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OneByOneCommand extends Command
{
    private OutputHelper $outputHelper;

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
                'requests',
                'r',
                InputOption::VALUE_REQUIRED,
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $curl_handlers = [];
        $failed_requests = 0;
        $table = [];

        $requests_count = $input->getOption('requests');

        $output->write("\n\n");

        $this->outputHelper->outputWelcomeMessage($output, $this->getApplication());

        $output->write("\n\n");

        $output->writeln("Doing {$requests_count} requests one by one...");

        $timestamp = \microtime(true); // current timestamp in seconds

        for ($i = 0; $i < $requests_count; ++$i) {
            
            $handler = new CurlHandler($input->getArgument('url'));
            
            $handler->execute();

            $info = $handler->getInfo();

            if ($info['http_code'] != 200) {
                ++$failed_requests;
                continue;
            }

            // add values to array in ms
            $table['DNS Lookup']['values'][] = $info['namelookup_time_us'] / 1000;
            $table['TCP Handshake']['values'][] = ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000;
            $table['SSL Handshake']['values'][] = ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000;
            $table['TTFB']['values'][] = ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000;
            $table['Data Transfer']['values'][] = ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000;
            $table['Total']['values'][] = $info['total_time_us'] / 1000;

            $curl_handlers[$i] = $handler;
        }

        $output->writeln('Execution time: ' . sprintf('%2.3f', \microtime(true) - $timestamp) . ' seconds');

        $output->write("\n\n");

        foreach($table as $key => $val) {
            $table[$key]['min'] = \min($val['values']);
            $table[$key]['max'] = \max($val['values']);
            $table[$key]['avg'] = StatHelper::calculateAverage($val['values']);
            $table[$key]['mdn'] = StatHelper::calculateMedian($val['values']);
            $table[$key]['p75'] = StatHelper::calculatePercentile(75, $val['values']);
            $table[$key]['p95'] = StatHelper::calculatePercentile(95, $val['values']);
        }

        if ($failed_requests > 0) {
            $output->writeln('<fg=red>Failed requests: ' . $failed_requests . '</>');
        }

        $this->outputHelper->outputTimingTable($output, $table);

        $output->write("\n\n");

        return self::SUCCESS;
    }
}
