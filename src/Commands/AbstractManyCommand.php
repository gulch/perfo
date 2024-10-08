<?php

declare(strict_types=1);

namespace Perfo\Commands;

use Perfo\Handlers\CurlHandler;
use Perfo\Helpers\OutputHelper;
use Perfo\Helpers\StatHelper;
use Perfo\Parsers\ServerTimingHeaderParser;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function floatval;

abstract class AbstractManyCommand extends AbstractCommand
{
    protected OutputHelper $output_helper;
    protected ?ServerTimingHeaderParser $server_timing_header_parser = null;

    protected function configure(): void
    {
        $this->addOption(
            'requests',
            'r',
            InputOption::VALUE_REQUIRED,
        );

        parent::configure();

        $this->output_helper = new OutputHelper;
    }

    protected function outputTimings(array $curl_handlers, OutputInterface $output): void
    {
        $failed_requests_count = 0;
        $table = [];

        foreach ($curl_handlers as $ch) {

            /** @var CurlHandler $ch */
            $info = $ch->getInfo();

            if ($info['http_code'] != 200) {
                ++$failed_requests_count;
                continue;
            }

            // add values to array in ms
            $table['DNS Lookup']['values'][] = floatval($info['namelookup_time_us'] / 1000);

            if ($info['connect_time_us'] > 0) {
                $table['TCP Handshake']['values'][] = floatval(($info['connect_time_us'] - $info['namelookup_time_us']) / 1000);
                $table['SSL Handshake']['values'][] = floatval(($info['appconnect_time_us'] - $info['connect_time_us']) / 1000);
            } else {
                $table['SSL Handshake']['values'][] = floatval(($info['appconnect_time_us'] - $info['namelookup_time_us']) / 1000);
            }

            $table['Request Handle']['values'][] = floatval(($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000);
            
            $table['Data Transfer']['values'][] = floatval(($info['total_time_us'] - $info['starttransfer_time_us']) / 1000);

            $table['TTFB']['values'][] = floatval($info['starttransfer_time_us'] / 1000);
            
            $table['Total']['values'][] = floatval($info['total_time_us'] / 1000);
        }

        foreach ($table as $key => $val) {
            $table[$key]['min'] = \min($val['values']);
            $table[$key]['max'] = \max($val['values']);
            $table[$key]['avg'] = StatHelper::calculateAverage($val['values']);
            $table[$key]['mdn'] = StatHelper::calculateMedian($val['values']);
            $table[$key]['p75'] = StatHelper::calculatePercentile(75, $val['values']);
            $table[$key]['p95'] = StatHelper::calculatePercentile(95, $val['values']);
        }

        if ($failed_requests_count > 0) {
            $output->writeln('<fg=red>Failed requests: ' . $failed_requests_count . '</>');
        }

        $this->output_helper->outputTimingTable($output, $table, 'Timings (in ms)');
    }

    protected function outputServerTimings(array $curl_handlers, OutputInterface $output): void
    {
        if (!$this->server_timing_header_parser) {
            $this->server_timing_header_parser = new ServerTimingHeaderParser();
        }

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
                        $this->server_timing_header_parser->parse($st),
                    ];
                }
            } else {
                $parsed_timings = $this->server_timing_header_parser->parse($server_timing_header);
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

        $this->output_helper->outputTimingTable($output, $table, 'Server-Timing');
    }
}
