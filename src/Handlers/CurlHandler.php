<?php

declare(strict_types=1);

namespace Perfo\Handlers;

use CurlHandle;
use Symfony\Component\Console\Input\InputInterface;

use function count, curl_exec, curl_getinfo, curl_init, curl_setopt, strlen, trim;

class CurlHandler
{
    private CurlHandle $handler;
    private InputInterface $input;
    private array $headers = [];

    public function __construct(InputInterface $input)
    {
        $this->input = $input;

        $this->init();
    }

    private function init(): void
    {
        $this->handler = curl_init($this->input->getArgument('url'));

        $this->setupOptions();
    }

    public function getHandle(): CurlHandle
    {
        return $this->handler;
    }

    public function execute(): string|bool
    {
        return curl_exec($this->handler);
    }

    public function getInfo(): mixed
    {
        return curl_getinfo($this->handler);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    private function setupOptions(): void
    {
        curl_setopt($this->handler, \CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->handler, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handler, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->handler, \CURLOPT_ENCODING, 'gzip, deflate, br, zstd');
        curl_setopt($this->handler, \CURLOPT_USERAGENT, 'gulch/perfo via cURL');

        curl_setopt($this->handler, \CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt($this->handler, \CURLOPT_FRESH_CONNECT, true);

        // force request via HTTP3 protocol
        if ($this->input->getOption('force-http3')) {
            // constant CURL_HTTP_VERSION_3ONLY value is 31
            curl_setopt($this->handler, \CURLOPT_HTTP_VERSION, 31);
        }

        // this function is called by curl for each header received
        curl_setopt(
            $this->handler,
            \CURLOPT_HEADERFUNCTION,
            function ($curl, $header) {

                $header_arr = \explode(':', $header, 2);

                // ignore headers with no value
                if (count($header_arr) < 2) {
                    return strlen($header);
                }

                $key = \strtolower(trim($header_arr[0]));
                $value = trim($header_arr[1]);

                if (isset($this->headers[$key])) {

                    if (true === \is_array($this->headers[$key])) {
                        $this->headers[$key] = [$value, ...$this->headers[$key]];
                    }

                    $this->headers[$key] = [$value, $this->headers[$key]];

                    return strlen($header);
                }

                $this->headers[$key] = $value;

                return strlen($header);
            }
        );
    }
}
