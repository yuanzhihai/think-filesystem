<?php

declare(strict_types=1);

namespace yzh52521\filesystem\driver;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Visibility;
use yzh52521\filesystem\Driver;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsS3PortableVisibilityConverter;

class S3 extends Driver
{

    protected function createAdapter()
    {
        $client = new S3Client( $this->config );
        $root = (string) ($this->config['root'] ?? '');
        $visibility = new AwsS3PortableVisibilityConverter(
            $config['visibility'] ?? Visibility::PUBLIC
        );
        $streamReads = $this->config['stream_reads'] ?? false;
        return new AwsS3V3Adapter(
            $client,
            $this->config['bucket'],$root, $visibility, null, $config['options'] ?? [], $streamReads
        );
    }
}
