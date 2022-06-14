<?php

namespace Perfo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_setopt, strlen, trim, count, explode, is_array;

class ConcurrentlyCommand extends Command
{
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = [];

        $cmh = \curl_multi_init();

        $curl_handlers = [];

        $requests_count = $input->getOption('requests');

        for ($i = 0; $i < $requests_count; ++$i) {
            $curl_handlers[$i] = \curl_init();

            curl_setopt($curl_handlers[$i], \CURLOPT_URL, $input->getArgument('url'));
            curl_setopt($curl_handlers[$i], \CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_handlers[$i], \CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_handlers[$i], \CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl_handlers[$i], \CURLOPT_ENCODING, 'gzip, deflate, br');

            curl_setopt(
                $curl_handlers[$i],
                \CURLOPT_HEADERFUNCTION,
                function ($curl, $header) use (&$headers, $i) {

                    $header_arr = explode(':', $header, 2);

                    // ignore headers with no value
                    if (count($header_arr) < 2) {
                        return strlen($header);
                    }

                    $key = \strtolower(trim($header_arr[0]));
                    $value = trim($header_arr[1]);

                    if (isset($headers[$i][$key])) {

                        if (true === is_array($headers[$i][$key])) {
                            $headers[$i][$key] = [$value, ...$headers[$i][$key]];
                        }

                        $headers[$i][$key] = [$value, $headers[$i][$key]];

                        return strlen($header);
                    }

                    $headers[$i][$key] = $value;

                    return strlen($header);
                }
            );

            \curl_multi_add_handle($cmh, $curl_handlers[$i]);
        }

        do {
            \curl_multi_exec($cmh, $running);
        } while ($running > 0);

        foreach ($curl_handlers as $ch) {
            $info = \curl_getinfo($ch);
            $output->writeln('');
            $output->writeln('<fg=gray>Timing:</>');
            $output->writeln($this->getFormattedStr('DNS Lookup', $info['namelookup_time_us'] / 1000));
            $output->writeln($this->getFormattedStr('TCP Handshake', ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000));
            $output->writeln($this->getFormattedStr('SSL Handshake', ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000));
            $output->writeln($this->getFormattedStr('TTFB', ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000));
            $output->writeln($this->getFormattedStr('Data Transfer', ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000));
        }

        //print_r($headers);

        return self::SUCCESS;
    }

    private function getFormattedStr(string $title, float $value, ?string $color = 'yellow'): string
    {
        $offset = 30 - strlen($title);

        return "<fg={$color}>{$title}</>" . sprintf('%' . $offset  . '.2f', $value) . ' ms';
    }
}
