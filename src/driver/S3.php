<?php

declare(strict_types=1);

namespace yzh52521\filesystem\driver;

use League\Flysystem\AdapterInterface;
use yzh52521\filesystem\Driver;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;

class S3 extends Driver
{

    protected function createAdapter(): AdapterInterface
    {
        $handler = new GuzzleHandler();
        $options = array_merge($this->config, ['http_handler' => $handler]);
        $client  = new S3Client($options);
        return new AwsS3Adapter($client, $options['bucket_name'], '');
    }


}
