<?php

namespace Perfo\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function curl_setopt, strlen, trim, count;

class SingleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('one')
            ->setDescription('Single Request App Performance Measure')
            ->setHelp('This command allows you to send single request to web app...')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'Web Application URL',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $headers = [];

        $ch = \curl_init();

        curl_setopt($ch, \CURLOPT_URL, $input->getArgument('url'));

        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);

        // this function is called by curl for each header received
        curl_setopt(
            $ch,
            \CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {

                $header_arr = \explode(':', $header, 2);

                // ignore headers with no value
                if (count($header_arr) < 2) {
                    return strlen($header);
                }

                $key = \strtolower(trim($header_arr[0]));

                if (isset($headers[$key]))
                {
                    $headers[$key] = $headers[$key] . ' | ' . $header_arr[1];

                    return strlen($header);
                }

                $headers[$key] = trim($header_arr[1]);

                return strlen($header);
            }
        );

        $response = \curl_exec($ch);

        $info = \curl_getinfo($ch);

        //print_r($headers);

        //print_r($info);

        //echo 'DNS Lookup' . sprintf('%20.2f', $info['namelookup_time_us'] / 1000) . " ms \n";
        //echo 'TCP Handshake' . sprintf('%17.2f', ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000) . " ms \n";
        //echo 'SSL Handshake' . sprintf('%17.2f', ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000) . " ms \n";
        //echo 'Time To First Byte' . sprintf('%12.2f', ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000) . " ms \n";
        //echo 'Data Transfer' . sprintf('%17.2f', ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000) . " ms \n";
        //echo $headers['server-timing'][0] . "\n";

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
            
            $output->writeln("<fg=blue>{$key}</>" . str_repeat('.', $offset) . $val);
        }
    }
}
