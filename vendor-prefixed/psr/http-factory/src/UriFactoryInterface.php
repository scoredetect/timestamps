<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 22-February-2025 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message;

interface UriFactoryInterface
{
    /**
     * Create a new URI.
     *
     * @param string $uri
     *
     * @return UriInterface
     *
     * @throws \InvalidArgumentException If the given URI cannot be parsed.
     */
    public function createUri(string $uri = ''): UriInterface;
}
