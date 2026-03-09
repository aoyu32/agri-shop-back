<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\OssService;
use app\common\Response;
use think\facade\Filesystem;

/**
 * OSS文件管理控制器
 */
class OssController extends BaseController
{
    private $ossService;
    private $config;

    protected function initialize()
    {
        parent::initialize();
        $this->ossService = new OssService();
        $this->config = config('oss');
    }

    /**
     * 上传单个文件
     */
    public function upload()
    {
        try {
            // 获取上传的文件
            $file = $this->request->file('file');
            if (!$file) {
                return Response::validateError('请选择要上传的文件');
            }

            // 获取上传类型（product, shop, user, category, other）
            $type = $this->request->param('type', 'other');
            if (!isset($this->config['upload_path'][$type])) {
                $type = 'other';
            }

            // 验证文件
            $validate = $this->validateFile($file);
            if ($validate !== true) {
                return Response::validateError($validate);
            }

            // 生成文件名
            $ext = strtolower($file->getOriginalExtension());
            $filename = date('Ymd') . '/' . md5(uniqid() . microtime()) . '.' . $ext;
            $ossPath = $this->config['upload_path'][$type] . $filename;

            // 上传到OSS
            $url = $this->ossService->upload($file->getRealPath(), $ossPath);

            return Response::success([
                'url' => $url,
                'path' => $ossPath,
                'filename' => $filename,
                'size' => $file->getSize(),
                'ext' => $ext,
                'type' => $type
            ], '上传成功');
        } catch (\Exception $e) {
            return Response::error('上传失败：' . $e->getMessage());
        }
    }

    /**
     * 批量上传文件
     */
    public function batchUpload()
    {
        try {
            // 获取上传的文件
            $files = $this->request->file('files');
            if (!$files || !is_array($files)) {
                return Response::validateError('请选择要上传的文件');
            }

            // 获取上传类型
            $type = $this->request->param('type', 'other');
            if (!isset($this->config['upload_path'][$type])) {
                $type = 'other';
            }

            $uploadedFiles = [];
            $errors = [];

            foreach ($files as $index => $file) {
                try {
                    // 验证文件
                    $validate = $this->validateFile($file);
                    if ($validate !== true) {
                        $errors[] = "文件{$index}: {$validate}";
                        continue;
                    }

                    // 生成文件名
                    $ext = strtolower($file->getOriginalExtension());
                    $filename = date('Ymd') . '/' . md5(uniqid() . microtime() . $index) . '.' . $ext;
                    $ossPath = $this->config['upload_path'][$type] . $filename;

                    // 上传到OSS
                    $url = $this->ossService->upload($file->getRealPath(), $ossPath);

                    $uploadedFiles[] = [
                        'url' => $url,
                        'path' => $ossPath,
                        'filename' => $filename,
                        'size' => $file->getSize(),
                        'ext' => $ext
                    ];
                } catch (\Exception $e) {
                    $errors[] = "文件{$index}: " . $e->getMessage();
                }
            }

            return Response::success([
                'files' => $uploadedFiles,
                'success_count' => count($uploadedFiles),
                'error_count' => count($errors),
                'errors' => $errors
            ], '批量上传完成');
        } catch (\Exception $e) {
            return Response::error('批量上传失败：' . $e->getMessage());
        }
    }

    /**
     * 上传Base64图片
     */
    public function uploadBase64()
    {
        try {
            $base64Data = $this->request->param('image');
            if (!$base64Data) {
                return Response::validateError('图片数据不能为空');
            }

            // 获取上传类型
            $type = $this->request->param('type', 'other');
            if (!isset($this->config['upload_path'][$type])) {
                $type = 'other';
            }

            // 解析Base64数据
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $ext = strtolower($matches[1]);
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            } else {
                $ext = 'jpg';
            }

            // 验证文件类型
            if (!in_array($ext, $this->config['allowed_ext'])) {
                return Response::validateError('不支持的图片格式');
            }

            // 解码Base64
            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                return Response::validateError('图片数据解析失败');
            }

            // 验证文件大小
            if (strlen($imageData) > $this->config['max_size']) {
                $maxSizeMB = $this->config['max_size'] / 1024 / 1024;
                return Response::validateError("图片大小不能超过{$maxSizeMB}MB");
            }

            // 生成文件名
            $filename = date('Ymd') . '/' . md5(uniqid() . microtime()) . '.' . $ext;
            $ossPath = $this->config['upload_path'][$type] . $filename;

            // 上传到OSS
            $url = $this->ossService->uploadContent($imageData, $ossPath);

            return Response::success([
                'url' => $url,
                'path' => $ossPath,
                'filename' => $filename,
                'size' => strlen($imageData),
                'ext' => $ext,
                'type' => $type
            ], '上传成功');
        } catch (\Exception $e) {
            return Response::error('上传失败：' . $e->getMessage());
        }
    }

    /**
     * 删除文件
     */
    public function delete()
    {
        try {
            $path = $this->request->param('path');
            if (!$path) {
                return Response::validateError('文件路径不能为空');
            }

            // 检查文件是否存在
            if (!$this->ossService->exists($path)) {
                return Response::error('文件不存在');
            }

            // 删除文件
            $this->ossService->delete($path);

            return Response::success([], '删除成功');
        } catch (\Exception $e) {
            return Response::error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除文件
     */
    public function batchDelete()
    {
        try {
            $paths = $this->request->param('paths');
            if (!$paths || !is_array($paths)) {
                return Response::validateError('请提供要删除的文件路径');
            }

            // 批量删除
            $this->ossService->batchDelete($paths);

            return Response::success([
                'count' => count($paths)
            ], '批量删除成功');
        } catch (\Exception $e) {
            return Response::error('批量删除失败：' . $e->getMessage());
        }
    }

    /**
     * 获取文件列表
     */
    public function list()
    {
        try {
            $prefix = $this->request->param('prefix', '');
            $maxKeys = (int)$this->request->param('max_keys', 100);

            $files = $this->ossService->listFiles($prefix, $maxKeys);

            return Response::success([
                'files' => $files,
                'count' => count($files)
            ]);
        } catch (\Exception $e) {
            return Response::error('获取文件列表失败：' . $e->getMessage());
        }
    }

    /**
     * 获取文件信息
     */
    public function info()
    {
        try {
            $path = $this->request->param('path');
            if (!$path) {
                return Response::validateError('文件路径不能为空');
            }

            // 检查文件是否存在
            if (!$this->ossService->exists($path)) {
                return Response::error('文件不存在');
            }

            $info = $this->ossService->getFileInfo($path);
            $info['url'] = $this->ossService->getUrl($path);
            $info['path'] = $path;

            return Response::success(['file' => $info]);
        } catch (\Exception $e) {
            return Response::error('获取文件信息失败：' . $e->getMessage());
        }
    }

    /**
     * 获取签名URL（用于私有文件访问）
     */
    public function getSignedUrl()
    {
        try {
            $path = $this->request->param('path');
            $timeout = (int)$this->request->param('timeout', 3600);

            if (!$path) {
                return Response::validateError('文件路径不能为空');
            }

            $url = $this->ossService->getSignedUrl($path, $timeout);

            return Response::success([
                'url' => $url,
                'expires_in' => $timeout
            ]);
        } catch (\Exception $e) {
            return Response::error('获取签名URL失败：' . $e->getMessage());
        }
    }

    /**
     * 复制文件
     */
    public function copy()
    {
        try {
            $sourcePath = $this->request->param('source_path');
            $destPath = $this->request->param('dest_path');

            if (!$sourcePath || !$destPath) {
                return Response::validateError('源路径和目标路径不能为空');
            }

            // 检查源文件是否存在
            if (!$this->ossService->exists($sourcePath)) {
                return Response::error('源文件不存在');
            }

            // 复制文件
            $this->ossService->copy($sourcePath, $destPath);
            $url = $this->ossService->getUrl($destPath);

            return Response::success([
                'url' => $url,
                'path' => $destPath
            ], '复制成功');
        } catch (\Exception $e) {
            return Response::error('复制失败：' . $e->getMessage());
        }
    }

    /**
     * 验证上传文件
     */
    private function validateFile($file)
    {
        // 检查文件是否有效
        if (!$file->isValid()) {
            return '无效的文件';
        }

        // 检查文件大小
        if ($file->getSize() > $this->config['max_size']) {
            $maxSizeMB = $this->config['max_size'] / 1024 / 1024;
            return "文件大小不能超过{$maxSizeMB}MB";
        }

        // 检查文件类型
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, $this->config['allowed_ext'])) {
            return '不支持的文件格式，仅支持：' . implode(', ', $this->config['allowed_ext']);
        }

        return true;
    }
}
