<?php
declare ( strict_types = 1 );

namespace yzh52521\filesystem;

use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\Cache;
use think\Collection;
use think\File;
use think\file\UploadedFile;
use voku\helper\ASCII;
use yzh52521\filesystem\driver\Sftp;

/**
 * Class Driver
 * @package think\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Cache */
    protected $cache;

    /** @var Filesystem */
    protected $filesystem;


    protected $adapter;

    /**
     * The temporary URL builder callback.
     *
     * @var \Closure|null
     */
    protected $temporaryUrlCallback;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(Cache $cache,array $config)
    {
        $this->cache  = $cache;
        $this->config = array_merge( $this->config,$config );

        $adapter          = $this->createAdapter();
        $this->filesystem = $this->createFilesystem( $adapter );
    }

    protected function createCacheStore($config)
    {
        if (true === $config) {
            return new MemoryStore;
        }

        return new CacheStore(
            $this->cache->store( $config['store'] ),
            $config['prefix'] ?? 'flysystem',
            $config['expire'] ?? null
        );
    }

    abstract protected function createAdapter(): AdapterInterface;

    protected function createFilesystem(AdapterInterface $adapter): Filesystem
    {
        if (!empty( $this->config['cache'] )) {
            $adapter = new CachedAdapter( $adapter,$this->createCacheStore( $this->config['cache'] ) );
        }

        $config = array_intersect_key( $this->config,array_flip( ['visibility','disable_asserts','url'] ) );

        return new Filesystem( $adapter,count( $config ) > 0 ? $config : null );
    }

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists($path)
    {
        return $this->filesystem->has( $path );
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param string $path
     * @return bool
     */
    public function missing($path)
    {
        return !$this->exists( $path );
    }

    /**
     * 获取文件完整路径
     * @param string $path
     * @return string
     */
    public function path(string $path): string
    {
        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof AbstractAdapter) {
            return $adapter->applyPathPrefix( $path );
        }

        return $path;
    }

    /**
     * Create a streamed response for a given file.
     *
     * @param string $path
     * @param string|null $name
     * @param array|null $headers
     * @param string|null $disposition
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function response($path,$name = null,array $headers = [],$disposition = 'inline')
    {
        $response = new StreamedResponse;

        $filename = $name ?? basename( $path );

        $disposition = $response->headers->makeDisposition(
            $disposition,$filename,$this->fallbackName( $filename )
        );

        $response->headers->replace( $headers + [
                'Content-Type'        => $this->mimeType( $path ),
                'Content-Length'      => $this->size( $path ),
                'Content-Disposition' => $disposition,
            ] );

        $response->setCallback( function () use ($path) {
            $stream = $this->readStream( $path );
            fpassthru( $stream );
            fclose( $stream );
        } );

        return $response;
    }

    /**
     * Create a streamed download response for a given file.
     *
     * @param string $path
     * @param string|null $name
     * @param array|null $headers
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download($path,$name = null,array $headers = [])
    {
        return $this->response( $path,$name,$headers,'attachment' );
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param string $name
     * @return string
     */
    protected function fallbackName($name)
    {
        return str_replace( '%','',ASCII::to_ascii( $name,'en' ) );
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     *
     * @throws
     */
    public function get($path)
    {
        try {
            return $this->filesystem->read( $path );
        } catch ( FileNotFoundException $e ) {
            throw new \Exception( $e->getMessage(),$e->getCode(),$e );
        }
    }

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string
     */
    public function getVisibility($path)
    {
        if ($this->filesystem->getVisibility( $path ) == AdapterInterface::VISIBILITY_PUBLIC) {
            return 'public';
        }

        return 'private';
    }

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     */
    public function setVisibility($path,$visibility)
    {
        return $this->filesystem->setVisibility( $path,$this->parseVisibility( $visibility ) );
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @return bool
     */
    public function prepend($path,$data,$separator = PHP_EOL)
    {
        if ($this->exists( $path )) {
            return $this->put( $path,$data.$separator.$this->get( $path ) );
        }

        return $this->put( $path,$data );
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     * @return bool
     */
    public function append($path,$data,$separator = PHP_EOL)
    {
        if ($this->exists( $path )) {
            return $this->put( $path,$this->get( $path ).$separator.$data );
        }

        return $this->put( $path,$data );
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|array $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array( $paths ) ? $paths : func_get_args();

        $success = true;

        foreach ( $paths as $path ) {
            try {
                if (!$this->filesystem->delete( $path )) {
                    $success = false;
                }
            } catch ( FileNotFoundException $e ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy($from,$to)
    {
        return $this->filesystem->copy( $from,$to );
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move($from,$to)
    {
        return $this->filesystem->rename( $from,$to );
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     */
    public function size($path)
    {
        return $this->filesystem->getSize( $path );
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false
     */
    public function mimeType($path)
    {
        return $this->filesystem->getMimetype( $path );
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     */
    public function lastModified($path)
    {
        return $this->filesystem->getTimestamp( $path );
    }

    protected function concatPathToUrl($url,$path)
    {
        return rtrim( $url,'/' ).'/'.ltrim( $path,'/' );
    }

    public function url(string $path): string
    {
        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof CachedAdapter) {
            $adapter = $adapter->getAdapter();
        }

        if (method_exists( $adapter,'getUrl' )) {
            return $adapter->getUrl( $path );
        } elseif (method_exists( $this->filesystem,'getUrl' )) {
            return $this->filesystem->getUrl( $path );
        } elseif ($adapter instanceof AwsS3Adapter) {
            return $this->getAwsUrl( $adapter,$path );
        } elseif ($adapter instanceof Ftp || $adapter instanceof Sftp) {
            return $this->getFtpUrl( $path );
        } elseif ($adapter instanceof LocalAdapter) {
            return $this->getLocalUrl( $path );
        } else {
            throw new RuntimeException( 'This driver does not support retrieving URLs.' );
        }
    }

    /**
     * Get the URL for the file at the given path.
     *
     * @param \League\Flysystem\AwsS3v3\AwsS3Adapter $adapter
     * @param string $path
     * @return string
     */
    protected function getAwsUrl($adapter,$path)
    {
        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (!is_null( $url = $this->filesystem->getConfig()->get( 'url' ) )) {
            return $this->concatPathToUrl( $url,$adapter->getPathPrefix().$path );
        }

        return $adapter->getClient()->getObjectUrl(
            $adapter->getBucket(),$adapter->getPathPrefix().$path
        );
    }

    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     * @return string
     */
    protected function getFtpUrl($path)
    {
        $config = $this->filesystem->getConfig();

        return $config->has( 'url' )
            ? $this->concatPathToUrl( $config->get( 'url' ),$path )
            : $path;
    }

    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     * @return string
     */
    protected function getLocalUrl($path)
    {
        $config = $this->filesystem->getConfig();

        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if ($config->has( 'url' )) {
            return $this->concatPathToUrl( $config->get( 'url' ),$path );
        }

        return $path;
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string
     *
     * @throws \RuntimeException
     */
    public function temporaryUrl($path,$expiration,array $options = [])
    {
        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof CachedAdapter) {
            $adapter = $adapter->getAdapter();
        }

        if (method_exists( $adapter,'getTemporaryUrl' )) {
            return $adapter->getTemporaryUrl( $path,$expiration,$options );
        }

        if ($this->temporaryUrlCallback) {
            return $this->temporaryUrlCallback->bindTo( $this,static::class )(
                $path,$expiration,$options
            );
        }

        if ($adapter instanceof AwsS3Adapter) {
            return $this->getAwsTemporaryUrl( $adapter,$path,$expiration,$options );
        }

        throw new RuntimeException( 'This driver does not support creating temporary URLs.' );
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param \League\Flysystem\AwsS3v3\AwsS3Adapter $adapter
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string
     */
    public function getAwsTemporaryUrl($adapter,$path,$expiration,$options)
    {
        $client = $adapter->getClient();

        $command = $client->getCommand( 'GetObject',array_merge( [
            'Bucket' => $adapter->getBucket(),
            'Key'    => $adapter->getPathPrefix().$path,
        ],$options ) );

        $uri = $client->createPresignedRequest(
            $command,$expiration
        )->getUri();

        // If an explicit base URL has been set on the disk configuration then we will use
        // it as the base URL instead of the default path. This allows the developer to
        // have full control over the base path for this filesystem's generated URLs.
        if (!is_null( $url = $this->filesystem->getConfig()->get( 'temporary_url' ) )) {
            $uri = $this->replaceBaseUrl( $uri,$url );
        }

        return (string)$uri;
    }

    /**
     * Replace the scheme, host and port of the given UriInterface with values from the given URL.
     *
     * @param \Psr\Http\Message\UriInterface $uri
     * @param string $url
     * @return \Psr\Http\Message\UriInterface
     */
    protected function replaceBaseUrl($uri,$url)
    {
        $parsed = parse_url( $url );

        return $uri
            ->withScheme( $parsed['scheme'] )
            ->withHost( $parsed['host'] )
            ->withPort( $parsed['port'] ?? null );
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function files($directory = null,$recursive = false)
    {
        $contents = $this->filesystem->listContents( $directory ?? '',$recursive );

        return $this->filterContentsByType( $contents,'file' );
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string|null $directory
     * @return array
     */
    public function allFiles($directory = null)
    {
        return $this->files( $directory,true );
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     * @return array
     */
    public function directories($directory = null,$recursive = false)
    {
        $contents = $this->filesystem->listContents( $directory ?? '',$recursive );

        return $this->filterContentsByType( $contents,'dir' );
    }

    /**
     * Get all (recursive) of the directories within a given directory.
     *
     * @param string|null $directory
     * @return array
     */
    public function allDirectories($directory = null)
    {
        return $this->directories( $directory,true );
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory($path)
    {
        return $this->filesystem->createDir( $path );
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory($directory)
    {
        return $this->filesystem->deleteDir( $directory );
    }

    /**
     * Flush the Flysystem cache.
     *
     * @return void
     */
    public function flushCache()
    {
        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof CachedAdapter) {
            $adapter->getCache()->flush();
        }
    }

    /**
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Filter directory contents by type.
     *
     * @param array $contents
     * @param string $type
     * @return array
     */
    protected function filterContentsByType($contents,$type)
    {
        return Collection::make( $contents )
            ->where( 'type',$type )
            ->column( 'path' );
    }

    /**
     * Parse the given visibility value.
     *
     * @param string|null $visibility
     * @return string|null
     *
     * @throws \InvalidArgumentException
     */
    protected function parseVisibility($visibility)
    {
        if (is_null( $visibility )) {
            return;
        }

        switch ( $visibility ) {
            case 'public':
                return AdapterInterface::VISIBILITY_PUBLIC;
            case 'private':
                return AdapterInterface::VISIBILITY_PRIVATE;
        }

        throw new \InvalidArgumentException( "Unknown visibility: {$visibility}." );
    }

    /**
     * Define a custom temporary URL builder callback.
     *
     * @param \Closure $callback
     * @return void
     */
    public function buildTemporaryUrlsUsing(\Closure $callback)
    {
        $this->temporaryUrlCallback = $callback;
    }


    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|UploadedFile|string|resource $contents
     * @param mixed $options
     * @return bool
     */
    public function put($path,$contents,$options = [])
    {
        $options = is_string( $options )
            ? ['visibility' => $options]
            : (array)$options;

        // If the given contents is actually a file or uploaded file instance than we will
        // automatically store the file using a stream. This provides a convenient path
        // for the developer to store streams without managing them manually in code.
        if ($contents instanceof File ||
            $contents instanceof UploadedFile) {
            return $this->putFile( $path,$contents,$options );
        }

        if ($contents instanceof StreamInterface) {
            return $this->filesystem->putStream( $path,$contents->detach(),$options );
        }

        return is_resource( $contents )
            ? $this->filesystem->putStream( $path,$contents,$options )
            : $this->filesystem->put( $path,$contents,$options );
    }


    /**
     * 保存文件
     * @param string $path 路径
     * @param File|string $file 文件
     * @param null|string|\Closure $rule 文件名规则
     * @param array $options 参数
     * @return bool|string
     */
    public function putFile(string $path,File $file,$rule = null,array $options = [])
    {
        $file = is_string( $file ) ? new File( $file ) : $file;

        return $this->putFileAs( $path,$file,$file->hashName( $rule ),$options );
    }

    /**
     * 指定文件名保存文件
     * @param string $path 路径
     * @param File|string $file 文件
     * @param string $name 文件名
     * @param array $options 参数
     * @return bool|string
     */
    public function putFileAs(string $path,File $file,string $name,array $options = [])
    {
        $stream = fopen( is_string( $file ) ? $file : $file->getRealPath(),'r' );
        $path   = trim( $path.'/'.$name,'/' );

        $result = $this->put( $path,$stream,$options );

        if (is_resource( $stream )) {
            fclose( $stream );
        }

        return $result ? $path : false;

    }

    public function __call($method,$parameters)
    {
        return $this->filesystem->$method( ...$parameters );
    }
}
