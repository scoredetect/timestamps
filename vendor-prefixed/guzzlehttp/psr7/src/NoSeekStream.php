<?php
/**
 * @license MIT
 *
 * Modified by ScoreDetect on 21-March-2025 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

declare(strict_types=1);

namespace SDCOM_Timestamps\Vendor_Prefixed\GuzzleHttp\Psr7;

use SDCOM_Timestamps\Vendor_Prefixed\Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that prevents a stream from being seeked.
 */
final class NoSeekStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface */
    private $stream;

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Cannot seek a NoSeekStream');
    }

    public function isSeekable(): bool
    {
        return false;
    }
}
