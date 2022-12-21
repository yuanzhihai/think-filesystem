<?php

declare ( strict_types = 1 );

namespace yzh52521\filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\Cache;
use think\File;
use think\file\UploadedFile;
use think\helper\Arr;
use voku\helper\ASCII;

/**
 * Class Driver
 * @package yzh52521\filesystem
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
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected $prefixer;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(Cache $cache,array $config)
    {
        $this->cache  = $cache;
        $this->config = array_merge( $this->config,$config );

        $separator      = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new PathPrefixer( $config['root'] ?? '',$separator );

        if (isset( $config['prefix'] )) {
            $this->prefixer = new PathPrefixer( $this->prefixer->prefixPath( $config['prefix'] ),$separator );
        }

        $this->adapter          = $this->createAdapter();
        $this->filesystem = $this->createFilesystem( $this->adapter,$this->config );
    }

    abstract protected function createAdapter();

    /**
     * @param FilesystemAdapter $adapter
     * @param array $config
     * @return Filesystem
     */
    protected function createFilesystem(FilesystemAdapter $adapter,array $config)
    {
        if ($config['read-only'] ?? false === true) {
            $adapter = new ReadOnlyFilesystemAdapter($adapter);
        }

        if (! empty($config['prefix'])) {
            $adapter = new PathPrefixedAdapter($adapter, $config['prefix']);
        }

        return new Filesystem( $adapter,Arr::only( $config,[
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ] ) );
    }

    /**
     * 获取文件完整路径
     * @param string $path
     * @return string
     */
    public function path(string $path): string
    {
        return $this->prefixer->prefixPath( $path );
    }

    protected function concatPathToUrl($url,$path)
    {
        return rtrim( $url,'/' ).'/'.ltrim( $path,'/' );
    }

    /**
     * Determine if a file or directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists($path): bool
    {
        return $this->filesystem->has( $path );
    }

    /**
     * Determine if a file or directory is missing.
     *
     * @param string $path
     * @return bool
     */
    public function missing($path): bool
    {
        return !$this->exists( $path );
    }

    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function fileExists($path): bool
    {
        return $this->filesystem->fileExists( $path );
    }

    /**
     * Determine if a file is missing.
     *
     * @param string $path
     * @return bool
     */
    public function fileMissing($path): bool
    {
        return !$this->fileExists( $path );
    }

    /**
     * Determine if a directory exists.
     *
     * @param string $path
     * @return bool
     */
    public function directoryExists($path)
    {
        return $this->filesystem->directoryExists( $path );
    }

    /**
     * Determine if a directory is missing.
     *
     * @param string $path
     * @return bool
     */
    public function directoryMissing($path)
    {
        return !$this->directoryExists( $path );
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function get($path)
    {
        try {
            return $this->filesystem->read( $path );
        } catch ( UnableToReadFile $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }
    }

    /**
     * Create a streamed response for a given file.
     *
     * @param string $path
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function response($path,$name = null,array $headers = [],$disposition = 'inline')
    {
        $response = new StreamedResponse;

        if (!array_key_exists( 'Content-Type',$headers )) {
            $headers['Content-Type'] = $this->mimeType( $path );
        }

        if (!array_key_exists( 'Content-Length',$headers )) {
            $headers['Content-Length'] = $this->size( $path );
        }

        if (!array_key_exists( 'Content-Disposition',$headers )) {
            $filename = $name ?? basename( $path );

            $disposition = $response->headers->makeDisposition(
                $disposition,$filename,$this->fallbackName( $filename )
            );

            $headers['Content-Disposition'] = $disposition;
        }

        $response->headers->replace( $headers );

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
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string
     */
    public function getVisibility($path)
    {
        if ($this->filesystem->visibility( $path ) == Visibility::PUBLIC) {
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
        try {
            $this->filesystem->setVisibility( $path,$visibility );
        } catch ( UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
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
        if ($this->fileExists( $path )) {
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
        if ($this->fileExists( $path )) {
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
                $this->filesystem->delete( $path );
            } catch ( UnableToDeleteFile $e ) {
                throw_if( $this->throwsExceptions(),$e );

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
        try {
            $this->filesystem->copy( $from,$to );
        } catch ( UnableToCopyFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
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
        try {
            $this->filesystem->move( $from,$to );
        } catch ( UnableToMoveFile $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     * @throws FilesystemException
     */
    public function size($path)
    {
        return $this->filesystem->fileSize( $path );
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string|false
     */
    public function mimeType($path)
    {
        try {
            return $this->filesystem->mimeType( $path );
        } catch ( UnableToRetrieveMetadata $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }

        return false;
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     * @return int
     */
    public function lastModified($path): int
    {
        return $this->filesystem->lastModified( $path );
    }


    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        try {
            return $this->filesystem->readStream( $path );
        } catch ( UnableToReadFile $e ) {
            throw_if( $this->throwsExceptions(),$e );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path,$resource,array $options = [])
    {
        try {
            $this->filesystem->writeStream( $path,$resource,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    protected function getLocalUrl($path)
    {
        if (isset( $this->config['url'] )) {
            return $this->concatPathToUrl( $this->config['url'],$path );
        }

        return $path;
    }

    public function url(string $path): string
    {
        $adapter = $this->adapter;

        if (method_exists( $adapter,'getUrl' )) {
            return $adapter->getUrl( $path );
        } elseif (method_exists( $this->filesystem,'getUrl' )) {
            return $this->filesystem->getUrl( $path );
        } elseif ($adapter instanceof SftpAdapter || $adapter instanceof FtpAdapter) {
            return $this->getFtpUrl( $path );
        } elseif ($adapter instanceof LocalFilesystemAdapter) {
            return $this->getLocalUrl( $path );
        } else {
            throw new \RuntimeException( 'This driver does not support retrieving URLs.' );
        }
    }


    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     * @return string
     */
    protected function getFtpUrl($path)
    {
        return isset( $this->config['url'] )
            ? $this->concatPathToUrl( $this->config['url'],$path )
            : $path;
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
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemOperator
     */
    public function getDriver()
    {
        return $this->filesystem;
    }

    /**
     * Get the Flysystem adapter.
     *
     * @return \League\Flysystem\FilesystemAdapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * 保存文件
     * @param string $path 路径
     * @param File|string $file 文件
     * @param null|string|\Closure $rule 文件名规则
     * @param array $options 参数
     * @return bool|string
     */
    public function putFile(string $path,$file,$rule = null,array $options = [])
    {
        $file = is_string( $file ) ? new File( $file ) : $file;
        return $this->putFileAs( $path,$file,$file->hashName( $rule ),$options );
    }

    /**
     * 指定文件名保存文件
     * @param string $path 路径
     * @param File $file 文件
     * @param string $name 文件名
     * @param array $options 参数
     * @return bool|string
     */
    public function putFileAs(string $path,File $file,string $name,array $options = [])
    {
        $stream = fopen( $file->getRealPath(),'r' );
        $path   = trim( $path.'/'.$name,'/' );

        $result = $this->put( $path,$stream,$options );

        if (is_resource( $stream )) {
            fclose( $stream );
        }

        return $result ? $path : false;
    }

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

        try {
            if ($contents instanceof StreamInterface) {
                $this->writeStream( $path,$contents->detach(),$options );

                return true;
            }

            is_resource( $contents )
                ? $this->writeStream( $path,$contents,$options )
                : $this->write( $path,$contents,$options );
        } catch ( UnableToWriteFile|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
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
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isFile();
            } )
            ->sortByPath()
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
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
        return $this->filesystem->listContents( $directory ?? '',$recursive )
            ->filter( function (StorageAttributes $attributes) {
                return $attributes->isDir();
            } )
            ->map( function (StorageAttributes $attributes) {
                return $attributes->path();
            } )
            ->toArray();
    }

    /**
     * Get all the directories within a given directory (recursive).
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
        try {
            $this->filesystem->createDirectory( $path );
        } catch ( UnableToCreateDirectory|UnableToSetVisibility $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     * @return bool
     */
    public function deleteDirectory($directory)
    {
        try {
            $this->filesystem->deleteDirectory( $directory );
        } catch ( UnableToDeleteDirectory $e ) {
            throw_if( $this->throwsExceptions(),$e );

            return false;
        }

        return true;
    }

    /**
     * Determine if Flysystem exceptions should be thrown.
     *
     * @return bool
     */
    protected function throwsExceptions(): bool
    {
        return (bool)( $this->config['throw'] ?? false );
    }

    public function __call($method,$parameters)
    {
        return $this->filesystem->$method( ...$parameters );
    }
}
