<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use Google\Cloud\Storage\StorageClient;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;
use yzh52521\filesystem\Driver;

class Google extends Driver
{
    protected function createAdapter()
    {
        $storageClient = new StorageClient( [
            'projectId'   => $this->config['projectId'],
            'keyFilePath' => $this->config['keyFilePath'],
        ] );
        $bucket        = $storageClient->bucket( $this->config['bucket'] );

        return new GoogleStorageAdapter( $storageClient,$bucket );
    }
}