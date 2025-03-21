<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 21-March-2025 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

declare(strict_types=1);

namespace SDCOM_Timestamps\Vendor_Prefixed\GuzzleHttp\Promise;

/**
 * Interface used with classes that return a promise.
 */
interface PromisorInterface
{
    /**
     * Returns a promise.
     */
    public function promise(): PromiseInterface;
}
