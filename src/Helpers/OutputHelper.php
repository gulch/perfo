<?php

namespace Perfo\Helpers;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OutputHelper
{
    private const DEFAULT_OFFSET = 30;

    public function outputWelcomeMessage(OutputInterface $output, Application $app): void
    {
        $output->writeln('<options=bold;fg=bright-magenta>🚀 ' . $app->getName() . ' v' . $app->getVersion() . '</>');

        $curl_version = \curl_version();
        $output->writeln('<fg=magenta>Using Curl v' . $curl_version['version'] . ' with ' . $curl_version['ssl_version'] . '</>');
    }

    public function outputGeneralInfo(InputInterface $input, OutputInterface $output, array $info): void
    {
        $url_string = $input->getArgument('url');

        if ($input->getArgument('url') != $info['url']) {
            $url_string .= ' -> <options=bold>' . $info['url'] . '</>';
        }

        $output->writeln('<fg=green>URL:</> ' . $url_string);
        $output->writeln('<fg=green>Protocol:</> ' . $this->getHttpVersionText($info['http_version']));
        $output->writeln('<fg=green>Code:</> ' . $info['http_code']);
        $output->writeln('<fg=green>Downloaded:</> ' . $this->getSizeText($info['size_download']));
    }

    public function outputServerTiming(OutputInterface $output, array $items): void
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

            $offset = self::DEFAULT_OFFSET - strlen($item['name']) - strlen($dur);

            $offset = $offset > 0 ? $offset : 1;

            $string .= '<fg=gray>' . str_repeat('.', $offset) . '</>';

            $string .= $dur;

            if (isset($item['desc'])) {
                $string .= '<fg=gray>' . str_repeat('.', 5) . '</><fg=magenta>' . $item['desc'] . '</>';
            }

            $output->writeln($string);
        }
    }

    public function outputHeaders(OutputInterface $output, array $headers): void
    {
        $output->writeln('<fg=gray>Headers:</>');

        foreach ($headers as $key => $val) {
            $offset = self::DEFAULT_OFFSET - strlen($key);

            $offset = $offset > 0 ? $offset : 1;

            if (true === is_array($val)) {
                $val = implode(' • ', $val);
            }

            $output->writeln("<fg=blue>{$key}</><fg=gray>" . str_repeat('.', $offset) . '</>' . $val);
        }
    }

    public function outputTiming(OutputInterface $output, array $info): void
    {
        $output->writeln('<fg=gray>Timing (in ms):</>');
        $output->writeln($this->getFormattedStr('DNS Lookup', $info['namelookup_time_us'] / 1000));
        $output->writeln($this->getFormattedStr('TCP Handshake', ($info['connect_time_us'] - $info['namelookup_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('SSL Handshake', ($info['appconnect_time_us'] - $info['connect_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('TTFB', ($info['starttransfer_time_us'] - $info['pretransfer_time_us']) / 1000));
        $output->writeln($this->getFormattedStr('Data Transfer', ($info['total_time_us'] - $info['starttransfer_time_us']) / 1000));
        $output->writeln('<fg=gray>' . str_repeat('-', 30) . '</>');
        $output->writeln($this->getFormattedStr('Total', $info['total_time_us'] / 1000, true, 'bright-yellow'));

        if ($info['redirect_time'] > 0) {
            $output->writeln('<fg=gray>' . str_repeat('-', self::DEFAULT_OFFSET) . '</>');
            $output->writeln($this->getFormattedStr('Redirect', $info['redirect_time_us'] / 1000));
        }
    }

    public function outputTimingTable(OutputInterface $output, array $table): void
    {
        $table_offset = 15;

        $header_text = 'Timing (in ms):';

        $offset = self::DEFAULT_OFFSET - $table_offset - strlen($header_text);

        $header_text .= str_repeat('.', $offset);

        foreach ($table['Total'] as $key => $value) {

            if(true === \is_array($value)) continue;

            $offset = $table_offset - strlen($key);

            $header_text .= str_repeat('.', $offset);
            $header_text .= $key;
        }

        $output->writeln("<fg=gray>{$header_text}</>");

        foreach ($table as $key => $item) {

            $offset = self::DEFAULT_OFFSET - $table_offset - strlen($key);

            $text = "<fg=yellow>{$key}</>";
            $text .= '<fg=gray>' . str_repeat('.', $offset) . '</>';

            foreach ($item as $item_value) {
                
                if(true === \is_array($item_value)) continue;

                $formatted_value = sprintf('%4.2f', $item_value);
                $item_offset = $table_offset - strlen($formatted_value);

                $text .= '<fg=gray>' . str_repeat('.', $item_offset) . '</>';
                $text .= $formatted_value;
            }

            if($key === 'Total') {
                $total_offset = self::DEFAULT_OFFSET - $table_offset;
                $total_offset += $table_offset * (count($item) - 1);

                $output->writeln('<fg=gray>' . str_repeat('-', $total_offset) . '</>');
            }

            $output->writeln($text);
        }
    }

/*     public function writeFormattedLine(
        OutputInterface $output,
        string $title,
        string|int|float $value,
        string $color = 'yellow',
    ): void {
        $output->writeln($this->getFormattedStr($title, $value, $color));
    } */

    public function getFormattedStr(
        string $title,
        float $value,
        bool $is_bold = false,
        string $color = 'yellow'
    ): string {
        $value = sprintf('%4.2f', $value);
        $offset = self::DEFAULT_OFFSET - strlen($title) - strlen($value);
        $bold = $is_bold ? ';options=bold' : '';
        $string = "<fg={$color}{$bold}>{$title}</>";
        $string .= '<fg=gray>' . str_repeat('.', $offset) . '</>';
        $string .= $value;

        return $string;
    }

    private function getHttpVersionText(int $value): string
    {
        return match ($value) {
            1 => 'HTTP/1.0',
            2 => 'HTTP/1.1',
            3 => 'HTTP/2',
            4 => 'HTTP/3',
            default => 'unknown'
        };
    }

    private function getSizeText(int $value): string
    {
        // MBytes/s
        if ($value > 1024 * 1024) {
            return sprintf('%1.2f', $value / 1024 / 1024) . ' MBytes';
        }

        // KBytes/s
        if ($value > 1024) {
            return sprintf('%1.2f', $value / 1024) . ' Kbytes';
        }

        return $value . ' bytes';
    }
}
