<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 31-October-2024 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Client;

use SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message\RequestInterface;
use SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
