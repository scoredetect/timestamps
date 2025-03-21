<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 21-March-2025 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace SDCOM_Timestamps\Vendor_Prefixed\GuzzleHttp;

use SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
