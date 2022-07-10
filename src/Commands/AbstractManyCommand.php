<?php

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Perfo\Helpers\StatHelper;
use Perfo\Parsers\ServerTimingHeaderParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\LogicException;

abstract class AbstractManyCommand extends Command
{
    protected OutputHelper $outputHelper;

    protected function configure(): void
    {
        $this->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL',
            )->addOption(
                'requests',
                'r',
                InputOption::VALUE_REQUIRED,
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        throw new LogicException('You must override the execute() method in the concrete command class.');
    }

    protected function outputTimings(array $curl_handlers, OutputInterface $output)
    {
        $failed_requests = 0;
        $table = [];

        foreach ($curl_handlers as $ch) {

            /** @var CurlHandler $ch */
            $info = $ch->getInfo();

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
        }

        foreach ($table as $key => $val) {
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

        $this->outputHelper->outputTimingTable($output, $table, 'Timings (in ms)');
    }

    protected function outputServerTimings(array $curl_handlers, OutputInterface $output)
    {
        $parser = new ServerTimingHeaderParser();

        $table = [];

        foreach ($curl_handlers as $ch) {

            /** @var CurlHandler $ch */
            $headers = $ch->getHeaders();

            $server_timing_header = isset($headers['server-timing']) ? $headers['server-timing'] : '';

            if ('' === $server_timing_header) continue;

            // parse Server-Timing header
            $parsed_timings = [];

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

            if (0 === \count($parsed_timings)) continue;

            foreach ($parsed_timings as $item) {

                if (!isset($item['dur'])) continue;

                $table[$item['name']]['values'][] = $item['dur'];
            }
        }

        foreach ($table as $key => $val) {
            $table[$key]['min'] = \min($val['values']);
            $table[$key]['max'] = \max($val['values']);
            $table[$key]['avg'] = StatHelper::calculateAverage($val['values']);
            $table[$key]['mdn'] = StatHelper::calculateMedian($val['values']);
            $table[$key]['p75'] = StatHelper::calculatePercentile(75, $val['values']);
            $table[$key]['p95'] = StatHelper::calculatePercentile(95, $val['values']);
        }

        if (0 === \count($table)) {
            $output->writeln('<fg=gray;bg=red>Server-Timing header not exists</>');
            return;
        }

        $this->outputHelper->outputTimingTable($output, $table, 'Server-Timing');
    }
}
