<?php

namespace Perfo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_setopt, strlen, trim, count, explode, is_array;

class OneByOneCommand extends Command
{
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
                'server-timing',
                't',
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

        // sort headers array by header name
        \ksort($headers);

        $output->writeln('');
        $output->writeln('🚀' . $this->getApplication()->getName() . ' v' . $this->getApplication()->getVersion());
        $output->writeln('');

        $this->outputHeaders($headers, $output);

        $output->writeln('');

        $output->writeln('<fg=gray>Timing:</>');
        $output->writeln($this->getFormattedStr('DNS Lookup', $info['namelookup_time_us'] / 1000));
        $output->writeln($this->getFormattedStr('TCP Handshake', ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('SSL Handshake', ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('TTFB', ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('Data Transfer', ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000));


        // Server-Timing
        if ($input->getOption('server-timing')) {
            $output->writeln('');

            if (true == is_array($headers['server-timing'])) {
                $parsed_timings = [];
                foreach ($headers['server-timing'] as $st) {
                    $parsed_timings = [
                        ...$parsed_timings,
                        $this->parseServerTiming($st),
                    ];
                }
            } else {
                $this->outputServerTiming(
                    $this->parseServerTiming($headers['server-timing'] ?? ''),
                    $output,
                );
            }
        }

        return self::SUCCESS;
    }

    private function getFormattedStr(string $title, float $value, ?string $color = 'yellow'): string
    {
        $offset = 30 - strlen($title);

        return "<fg={$color}>{$title}</>" . sprintf('%' . $offset  . '.2f', $value) . ' ms';
    }

    private function outputHeaders(array $headers, OutputInterface $output): void
    {
        $output->writeln('<fg=gray>Headers:</>');

        foreach ($headers as $key => $val) {
            $offset = 40 - strlen($key);

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

        foreach($items as $item) {

            $string = "<fg=magenta>{$item['name']}</>";

            $offset = 30 - strlen($item['name']);

            $offset = $offset > 0 ? $offset : 1;

            $string .= '<fg=gray>' . str_repeat('.', $offset) . '</>';

            if(isset($item['dur'])) {
                $string .= sprintf('%7.2f', $item['dur']);
            }

            if(isset($item['desc'])) {
                $string .= '<fg=gray>' . str_repeat('.', 5) . '</><fg=magenta>'. $item['desc'] .'</>'; 
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
        $result_array = [];

        foreach (explode(',', $header) as $item) {
            $params = explode(';', $item);

            $timing['name'] = $params[0];

            for($i = 1, $c = count($params); $i < $c; ++$i)
            {
                [$paramName, $paramValue] = explode('=', $params[$i]);

                $paramName = trim($paramName);

                if(!$paramName) continue;

                $paramValue = trim($paramValue);
                $paramValue = trim($paramValue, '"');

                $timing[$paramName] = $paramValue;

            }

            $result_array[] = $timing;
        }

        return $result_array;
    }
}
