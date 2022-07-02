<?php

namespace Perfo\Handlers;

use CurlHandle;
use function curl_init, curl_setopt, curl_exec, curl_getinfo, strlen, trim, count;

class CurlHandler
{
    private CurlHandle $handler;
    private array $headers = [];

    public function __construct(string $url)
    {
        $this->handler = curl_init($url);
        $this->setupOptions();
    }

    public function execute(): string|bool
    {
        return curl_exec($this->handler);
    }

    public function getInfo()
    {
        return curl_getinfo($this->handler);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    private function setupOptions()
    {
        curl_setopt($this->handler, \CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->handler, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handler, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->handler, \CURLOPT_ENCODING, 'gzip, deflate, br');

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
