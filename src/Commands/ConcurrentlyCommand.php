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
            )->addOption(
                'server-timing',
                't',
                InputOption::VALUE_NONE,
            );

        $this->outputHelper = new OutputHelper;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

        $output->write("\n\n");

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $this->outputServerTimings($curl_handlers, $output);
        }

        $output->write("\n\n");

        return self::SUCCESS;
    }

    private function outputServerTimings(array $curl_handlers, OutputInterface $output)
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

        if(0 === \count($table)) {
            $output->writeln('<fg=gray;bg=red>Server-Timing header not exists</>');
        } else {
            $this->outputHelper->outputTimingTable($output, $table, 'Server-Timing');
        }       
    }
}
