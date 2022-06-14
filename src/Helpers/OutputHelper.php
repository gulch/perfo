<?php

namespace Perfo\Helpers;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OutputHelper
{
    public function outputWelcomeMessage(OutputInterface $output, Application $app): void
    {
        $output->writeln('<options=bold;fg=bright-magenta>🚀' . $app->getName() . ' v' . $app->getVersion() . '</>');
    }

    public function outputGeneralInfo(InputInterface $input, OutputInterface $output, array $info): void
    {
        $url_string = $input->getArgument('url');

        if($input->getArgument('url') != $info['url']) {
            $url_string .= ' -> <options=bold>' . $info['url'] . '</>';
        }

        $output->writeln('<fg=green>URL:</> ' . $url_string);
        $output->writeln('<fg=green>Protocol:</> ' . $this->getHttpVersionText($info['http_version']));
        $output->writeln('<fg=green>Code:</> ' . $info['http_code']);
        $output->writeln('<fg=green>Downloaded:</> ' . $this->getSizeText($info['size_download']));
    }

    private function getHttpVersionText(int $value): string
    {
        return match ($value) {
            1 => 'HTTP/1.0',
            2 => 'HTTP/1.1',
            3 => 'HTTP/2',
            3 => 'HTTP/3',
            default => 'unknown'
        };
    }

    private function getSizeText(int $value): string
    {
        // MBytes/s
        if ($value > 1024 * 1024) 
        {
            return sprintf('%1.2f', $value / 1024 / 1024) . ' MBytes';
        }

        // KBytes/s
        if ($value > 1024)
        {
            return sprintf('%1.2f', $value / 1024) . ' Kbytes';
        }

        return $value . ' bytes';
    }
}
