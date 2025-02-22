<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 22-February-2025 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Client;

use SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message\RequestInterface;

/**
 * Thrown when the request cannot be completed because of network issues.
 *
 * There is no response object as this exception is thrown when no response has been received.
 *
 * Example: the target host name can not be resolved or the connection failed.
 */
interface NetworkExceptionInterface extends ClientExceptionInterface
{
    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface;
}
