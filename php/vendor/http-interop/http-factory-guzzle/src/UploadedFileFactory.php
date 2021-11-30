<?php

namespace Http\Factory\Guzzle;

use GuzzleHttp\Psr7\UploadedFile;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    public function createUploadedFile(
        StreamInterface $stream,
        int $size = null,
        int $error = \UPLOAD_ERR_OK,
        string $clientFilename = null,
        string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            $size = $stream->getSize();
        }

        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
