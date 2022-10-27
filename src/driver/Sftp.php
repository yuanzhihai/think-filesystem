<?php
declare( strict_types = 1 );

namespace yzh52521\filesystem\driver;

use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use yzh52521\filesystem\Driver;

class Sftp extends Driver
{
    protected function createAdapter()
    {
        return new SftpAdapter( new SftpConnectionProvider(
            $this->config['host'] ?? 'localhost', // host (required)
            $this->config['username'], // username (required)
            $this->config['passwrod'], // password (optional, default: null) set to null if privateKey is used
            $this->config['privateKey'], // private key (optional, default: null) can be used instead of password, set to null if password is set
            $this->config['passphrase'],'my-super-secret-passphrase-for-the-private-key', // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
            $this->config['port'] ?? 22, // port (optional, default: 22)
            $this->config['timeout'] ?? false, // use agent (optional, default: false)
            30, // timeout (optional, default: 10)
            10, // max tries (optional, default: 4)
            null,
        ),$this->config['root'], // root path (required)
            PortableVisibilityConverter::fromArray( [
                'file' => [
                    'public'  => 0640,
                    'private' => 0604,
                ],
                'dir'  => [
                    'public'  => 0740,
                    'private' => 7604,
                ],
            ] ) );
    }
}