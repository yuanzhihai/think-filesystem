<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\AdapterInterface;
use yzh52521\filesystem\Driver;

class Ftp extends Driver
{
    protected function createAdapter(): AdapterInterface
    {
        return new FtpAdapter( $this->config );
    }
}