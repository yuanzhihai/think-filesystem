<?php

declare(strict_types=1);

namespace yzh52521\filesystem\driver;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use yzh52521\filesystem\Driver;
use Aws\S3\S3Client;

class S3 extends Driver
{

    protected function createAdapter()
    {
        $client = new S3Client( $this->config );
        return new AwsS3V3Adapter(
            $client,
            $this->config['bucket_name']
        );
    }
}
