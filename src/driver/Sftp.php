<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use League\Flysystem\AdapterInterface;
use yzh52521\filesystem\Driver;

class Sftp extends Driver
{
    protected function createAdapter(): AdapterInterface
    {
        return new \League\Flysystem\Sftp\SftpAdapter( $this->config );
    }
}