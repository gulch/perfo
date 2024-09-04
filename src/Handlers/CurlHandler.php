<?php

declare(strict_types=1);

namespace Perfo\Handlers;

use CurlHandle;
use Symfony\Component\Console\Input\InputInterface;

use function count;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function strlen;
use function trim;

class CurlHandler
{
    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0';

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
        curl_setopt($this->handler, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->handler, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->handler, \CURLOPT_SSL_VERIFYPEER, false);

        // Reuse connection
        if ($this->input->getOption('reuse')) {
            curl_setopt($this->handler, \CURLOPT_FRESH_CONNECT, false);
            curl_setopt($this->handler, \CURLOPT_TCP_KEEPALIVE, 1);
        } else {
            curl_setopt($this->handler, \CURLOPT_FRESH_CONNECT, true);
        }
        
        // DNS
        //curl_setopt($this->handler, \CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        //curl_setopt($this->handler, \CURLOPT_DNS_LOCAL_IP4, '1.1.1.1');

        $this->setupUserAgent();

        $this->setupEncoding();

        $this->setupProtocol();

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

    private function setupUserAgent(): void
    {
        // Set real browser user-agent
        if ($this->input->getOption('browser-user-agent')) {
            curl_setopt($this->handler, \CURLOPT_USERAGENT, self::BROWSER_USER_AGENT);
        } else {
            // default User Agent
            curl_setopt($this->handler, \CURLOPT_USERAGENT, 'gulch/perfo via cURL');
        }
    }

    private function setupEncoding(): void
    {
        // Set Content-Encoding
        curl_setopt($this->handler, \CURLOPT_ENCODING, $this->input->getOption('content-encoding'));
    }

    private function setupProtocol(): void
    {
        // send request via HTTP 1.1 protocol
        if ($this->input->getOption('http1')) {
            curl_setopt($this->handler, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);
        }

        // send request via HTTP2 protocol
        if ($this->input->getOption('http2')) {
            curl_setopt($this->handler, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_2);
        }

        // send request via HTTP3 protocol
        if ($this->input->getOption('http3')) {
            // constant CURL_HTTP_VERSION_3 value is 30
            // constant CURL_HTTP_VERSION_3ONLY value is 31
            curl_setopt($this->handler, \CURLOPT_HTTP_VERSION, 30);
        }
    }
}
