<h1><p align="center">think-filesystem</p></h1>
<p align="center"> thinkphp6.1 的文件系统扩展包，支持上传阿里云OSS和七牛和腾讯云COS和华为云OBS和awsS3</p>

## 包含

1. php >= 7.1
2. thinkphp >=6.1.0

## 支持

1. 阿里云
2. 七牛云
3. 腾讯云
4. 华为云
5. AwsS3
6. ftp
7  sfpt

## 安装

第一步：

```shell
$ composer require yzh52521/think-filesystem
```

第二步： 在config/filesystem.php中添加配置

```
'aliyun' => [
    'type'         => 'aliyun',
    'accessId'     => '******',
    'accessSecret' => '******',
    'bucket'       => 'bucket',
    'endpoint'     => 'oss-cn-hongkong.aliyuncs.com',
    'url'          => 'http://oss-cn-hongkong.aliyuncs.com',
],
'qiniu'  => [
    'type'      => 'qiniu',
    'accessKey' => '******',
    'secretKey' => '******',
    'bucket'    => 'bucket',
    'domain'       => '',
],
'qcloud' => [
    'type'       => 'qcloud',
    'region'      => '***', //bucket 所属区域 英文
    'appId'      => '***', // 域名中数字部分
    'secretId'   => '***',
    'secretKey'  => '***',
    'bucket'          => '***',
    'timeout'         => 60,
    'connect_timeout' => 60,
    'cdn'             => '您的 CDN 域名',
    'scheme'          => 'https',
    'read_from_cdn'   => false,
]
'obs'=>[
     'type'       => 'obs',
     'key'        => 'OBS_ACCESS_ID',
     'secret'     => 'OBS_ACCESS_KEY', //Huawei OBS AccessKeySecret
     'bucket'     => 'OBS_BUCKET', //OBS bucket name
     'endpoint'   => 'OBS_ENDPOINT',
     'cdn_domain' => 'OBS_CDN_DOMAIN',
     'ssl_verify' => 'OBS_SSL_VERIFY',
     'debug'      => 'APP_DEBUG',
],
's3'=>[
      'type'       => 's3',
      'credentials'             => [
                'key'    => 'S3_KEY',
                'secret' => 'S3_SECRET',
      ],
      'region'                  => 'S3_REGION',
      'version'                 => 'latest',
      'bucket_endpoint'         => false,
      'use_path_style_endpoint' => false,
      'endpoint'                => 'S3_ENDPOINT',
      'bucket_name'             => 'S3_BUCKET',
],
'ftp' =>[
    'type'       => 'ftp',
    'host' => 'example.com',
    // 基于基础的身份验证设置...
    'username' => 'username',
    'password' => 'password',
    'root' => '/path/to/root',
    'url' => '/path/to/root',
    'port' => 21,
    'timeout' => 90,
],
'sftp'=>[
    'type'       => 'sftp',
    'host' => 'example.com',
    // 基于基础的身份验证设置...
    'username' => 'username',
    'password' => 'password',
    // 使用加密密码进行基于 SSH 密钥的身份验证的设置...
    'privateKey' => 'path/to/or/contents/of/privatekey',
    'passphrase' => 'passphrase-for-privateKey',
    // 可选的 SFTP 设置
   // 'port' => 22,
   // 'root' => '/path/to/root',
   // 'timeout' => 10,
   'url' => '/path/to/root',
]
```

第三步： 开始使用。 请参考thinkphp文档
文档地址：[https://www.kancloud.cn/manual/thinkphp6_0/1037639 ](https://www.kancloud.cn/manual/thinkphp6_0/1037639 )

### demo

```php
$file = $this->request->file( 'image' );
      try {
            validate(
                ['image' => [
                        // 限制文件大小(单位b)，这里限制为4M
                        'fileSize' => 10 * 1024 * 1000,
                        // 限制文件后缀，多个后缀以英文逗号分割
                        'fileExt'  => 'gif,jpg,png,jpeg'
                    ]
                ])->check( ['image' => $file] );

            $path     = \yzh52521\filesystem\facade\Filesystem::disk( 'public' )->putFile( 'test',$file);
            $url      = \yzh52521\filesystem\facade\Filesystem::disk( 'public' )->url( $path );
            return json( ['path' => $path,'url'  => $url] );
      } catch ( \think\exception\ValidateException $e ) {
            echo $e->getMessage();
     }
```

## 授权

MIT

## 感谢

1. thinkphp
3. overtrue/flysystem-qiniu
4. league/flysystem
5. overtrue/flysystem-cos
