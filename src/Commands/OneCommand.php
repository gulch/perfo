<?php

namespace Perfo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_setopt, strlen, trim, count, explode, is_array;

class OneCommand extends Command
{
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = [];

        $ch = \curl_init();

        curl_setopt($ch, \CURLOPT_URL, $input->getArgument('url'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

        // this function is called by curl for each header received
        curl_setopt(
            $ch,
            \CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {

                $header_arr = explode(':', $header, 2);

                // ignore headers with no value
                if (count($header_arr) < 2) {
                    return strlen($header);
                }

                $key = \strtolower(trim($header_arr[0]));
                $value = trim($header_arr[1]);

                if (isset($headers[$key])) {

                    if (true === is_array($headers[$key])) {
                        $headers[$key] = [$value, ...$headers[$key]];
                    }

                    $headers[$key] = [$value, $headers[$key]];

                    return strlen($header);
                }

                $headers[$key] = $value;

                return strlen($header);
            }
        );

        $response = \curl_exec($ch);

        $info = \curl_getinfo($ch);

        $output->writeln('');
        $output->writeln('<options=bold;fg=bright-red>🚀' . $this->getApplication()->getName() . ' v' . $this->getApplication()->getVersion() . '</>');
        $output->writeln('');

        if ($input->getOption('output-headers')) {
            // sort headers array by header name
            \ksort($headers);

            $this->outputHeaders($headers, $output);

            $output->writeln('');
        }

        $output->writeln('<fg=gray>Timing (in ms):</>');
        $output->writeln($this->getFormattedStr('DNS Lookup', $info['namelookup_time_us'] / 1000));
        $output->writeln($this->getFormattedStr('TCP Handshake', ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('SSL Handshake', ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('TTFB', ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('Data Transfer', ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000));
        $output->writeln('<fg=gray>' . str_repeat('-', 30) . '</>');
        $output->writeln($this->getFormattedStr('Total', $info['total_time_us'] / 1000, true, 'bright-yellow'));
        $output->writeln('');

        // Server-Timing
        if ($input->getOption('server-timing')) {

            $server_timing_header = isset($headers['server-timing']) ? $headers['server-timing'] : ''; 

            if (true == is_array($server_timing_header)) {
                $parsed_timings = [];
                foreach ($server_timing_header as $st) {
                    $parsed_timings = [
                        ...$parsed_timings,
                        $this->parseServerTiming($st),
                    ];
                }
            } else {
                $this->outputServerTiming(
                    $this->parseServerTiming($server_timing_header),
                    $output,
                );
            }
            $output->writeln('');
        }

        return self::SUCCESS;
    }

    private function getFormattedStr(
        string $title,
        float $value,
        bool $is_bold = false,
        string $color = 'yellow'
    ): string {

        $value = sprintf('%4.2f', $value);

        $offset = 30 - strlen($title) - strlen($value);

        $bold = $is_bold ? ';options=bold' : '';

        $string = "<fg={$color}{$bold}>{$title}</>";

        $string .= '<fg=gray>' . str_repeat('.', $offset) . '</>';

        $string .= $value;

        return $string;
    }

    private function outputHeaders(array $headers, OutputInterface $output): void
    {
        $output->writeln('<fg=gray>Headers:</>');

        foreach ($headers as $key => $val) {
            $offset = 30 - strlen($key);

            $offset = $offset > 0 ? $offset : 1;

            if (true === is_array($val)) {
                $val = implode(' • ', $val);
            }

            $output->writeln("<fg=blue>{$key}</><fg=gray>" . str_repeat('.', $offset) . '</>' . $val);
        }
    }

    private function outputServerTiming(array $items, OutputInterface $output): void
    {
        $output->writeln('<fg=gray>Server-Timing:</>');

        if (0 === count($items)) {
            $output->writeln('<fg=gray;bg=red>Server-Timing header not exists</>');
            return;
        }

        foreach ($items as $item) {

            $string = "<fg=magenta>{$item['name']}</>";

            $dur = '';
            if (isset($item['dur'])) {
                $dur = sprintf('%4.2f', $item['dur']);
            }

            $offset = 30 - strlen($item['name']) - strlen($dur);

            $offset = $offset > 0 ? $offset : 1;

            $string .= '<fg=gray>' . str_repeat('.', $offset) . '</>';

            $string .= $dur;

            if (isset($item['desc'])) {
                $string .= '<fg=gray>' . str_repeat('.', 5) . '</><fg=magenta>' . $item['desc'] . '</>';
            }

            $output->writeln($string);
        }
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
