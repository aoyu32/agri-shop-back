<?php

declare(strict_types=1);

namespace app\service;

use OSS\OssClient;
use OSS\Core\OssException;

/**
 * 阿里云OSS服务类
 */
class OssService
{
    private $client;
    private $bucket;
    private $config;

    public function __construct()
    {
        $this->config = config('oss');
        $this->bucket = $this->config['bucket'];

        try {
            $this->client = new OssClient(
                $this->config['access_key_id'],
                $this->config['access_key_secret'],
                $this->config['endpoint']
            );
        } catch (OssException $e) {
            throw new \Exception('OSS初始化失败：' . $e->getMessage());
        }
    }

    /**
     * 上传文件
     * @param string $localFile 本地文件路径
     * @param string $ossPath OSS存储路径
     * @return string 文件URL
     */
    public function upload(string $localFile, string $ossPath): string
    {
        try {
            $this->client->uploadFile($this->bucket, $ossPath, $localFile);
            return $this->getUrl($ossPath);
        } catch (OssException $e) {
            throw new \Exception('文件上传失败：' . $e->getMessage());
        }
    }

    /**
     * 上传文件内容
     * @param string $content 文件内容
     * @param string $ossPath OSS存储路径
     * @return string 文件URL
     */
    public function uploadContent(string $content, string $ossPath): string
    {
        try {
            $this->client->putObject($this->bucket, $ossPath, $content);
            return $this->getUrl($ossPath);
        } catch (OssException $e) {
            throw new \Exception('文件上传失败：' . $e->getMessage());
        }
    }

    /**
     * 删除文件
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function delete(string $ossPath): bool
    {
        try {
            $this->client->deleteObject($this->bucket, $ossPath);
            return true;
        } catch (OssException $e) {
            throw new \Exception('文件删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除文件
     * @param array $ossPaths OSS文件路径数组
     * @return bool
     */
    public function batchDelete(array $ossPaths): bool
    {
        try {
            $this->client->deleteObjects($this->bucket, $ossPaths);
            return true;
        } catch (OssException $e) {
            throw new \Exception('批量删除失败：' . $e->getMessage());
        }
    }

    /**
     * 检查文件是否存在
     * @param string $ossPath OSS文件路径
     * @return bool
     */
    public function exists(string $ossPath): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $ossPath);
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * 获取文件URL
     * @param string $ossPath OSS文件路径
     * @return string
     */
    public function getUrl(string $ossPath): string
    {
        // 如果配置了自定义域名，使用自定义域名
        if (!empty($this->config['domain'])) {
            return rtrim($this->config['domain'], '/') . '/' . ltrim($ossPath, '/');
        }

        // 使用默认域名
        return 'https://' . $this->bucket . '.' . $this->config['endpoint'] . '/' . ltrim($ossPath, '/');
    }

    /**
     * 获取签名URL（用于私有文件访问）
     * @param string $ossPath OSS文件路径
     * @param int $timeout 超时时间（秒）
     * @return string
     */
    public function getSignedUrl(string $ossPath, int $timeout = 3600): string
    {
        try {
            return $this->client->signUrl($this->bucket, $ossPath, $timeout);
        } catch (OssException $e) {
            throw new \Exception('获取签名URL失败：' . $e->getMessage());
        }
    }

    /**
     * 列出文件
     * @param string $prefix 前缀
     * @param int $maxKeys 最大返回数量
     * @return array
     */
    public function listFiles(string $prefix = '', int $maxKeys = 100): array
    {
        try {
            $options = [
                'prefix' => $prefix,
                'max-keys' => $maxKeys,
            ];
            $listObjectInfo = $this->client->listObjects($this->bucket, $options);

            $files = [];
            foreach ($listObjectInfo->getObjectList() as $objectInfo) {
                $files[] = [
                    'key' => $objectInfo->getKey(),
                    'size' => $objectInfo->getSize(),
                    'last_modified' => $objectInfo->getLastModified(),
                    'url' => $this->getUrl($objectInfo->getKey())
                ];
            }

            return $files;
        } catch (OssException $e) {
            throw new \Exception('列出文件失败：' . $e->getMessage());
        }
    }

    /**
     * 复制文件
     * @param string $sourceOssPath 源文件路径
     * @param string $destOssPath 目标文件路径
     * @return bool
     */
    public function copy(string $sourceOssPath, string $destOssPath): bool
    {
        try {
            $this->client->copyObject($this->bucket, $sourceOssPath, $this->bucket, $destOssPath);
            return true;
        } catch (OssException $e) {
            throw new \Exception('文件复制失败：' . $e->getMessage());
        }
    }

    /**
     * 获取文件信息
     * @param string $ossPath OSS文件路径
     * @return array
     */
    public function getFileInfo(string $ossPath): array
    {
        try {
            $meta = $this->client->getObjectMeta($this->bucket, $ossPath);
            return [
                'size' => $meta['content-length'] ?? 0,
                'type' => $meta['content-type'] ?? '',
                'last_modified' => $meta['last-modified'] ?? '',
                'etag' => $meta['etag'] ?? '',
            ];
        } catch (OssException $e) {
            throw new \Exception('获取文件信息失败：' . $e->getMessage());
        }
    }
}
