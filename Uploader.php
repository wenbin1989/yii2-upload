<?php

namespace wenbin1989\yii2\upload;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use Yii;

/**
 * Uploader component 提供文件上传, 图片格式转换等功能.
 *
 * @author Wenbin Wang <wenbin1989@gmail.com>
 */
class Uploader extends Component
{
    /**
     * @var string the directory to store upload files. You may use path alias here.
     * If not set, it will use the "uploads" directory.
     */
    public $uploadPath = '@uploads';

    /**
     * @var string 上传后返回的文件url前缀.
     */
    public $uploadUrl = '/uploads';

    /**
     * @var integer 上传文件最大尺寸, 单位为bytes. 默认为10M.
     */
    public $maxSize = 10000000;

    /**
     * @var string 图片转换服务器的url. 通过POST请求图片转换服务器, url参数中加入type=format参数, 其中format为转换后的图片格式;
     * 请求body中为需要转换图片的二进制数据; 请求返回body为转换后图片的二进制数据.
     */
    public $convertServer;

    /**
     * @var array 允许上传的文件格式, 格式为:
     *
     * ~~~
     * [
     *     'directory' => ['type1', 'type2', ...],
     *     ...,
     * ]
     * ~~~
     *
     * 例如:
     *
     * ~~~
     * [
     *     'image' => ['jpg', 'png', 'gif'],
     *     'file' => ['doc', 'ppt', 'xls'],
     * ]
     * ~~~
     */
    public $allowedTypes;

    /**
     * @var integer the permission to be set for newly created files.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * If not set, the permission will be determined by the current environment.
     */
    public $fileMode;

    /**
     * @var integer the permission to be set for newly created directories.
     * This value will be used by PHP chmod() function. No umask will be applied.
     * Defaults to 0775, meaning the directory is read-writable by owner and group,
     * but read-only for other users.
     */
    public $dirMode = 0775;

    /**
     * @var callable a PHP callable that will be called to return the absolute path for uploading file to save.
     * If not set, [[getSavePath()]] will be used instead.
     * The signature of the callable should be:
     *
     * ```php
     * function ($type, $dir, $upload) {
     *     // $type 上传文件的类型(文件后缀名).
     *     // $dir 上传文件夹的名称.
     *     // $upload 当前Upload对象实例
     * }
     * ```
     *
     */
    public $getSavePath;

    /**
     * Initializes this component by ensuring the existence of the cache path.
     */
    public function init()
    {
        parent::init();
        $this->uploadPath = Yii::getAlias($this->uploadPath);
        if (!is_dir($this->uploadPath)) {
            throw new InvalidConfigException("The directory does not exist: {$this->uploadPath}");
        } elseif (!is_writable($this->uploadPath)) {
            throw new InvalidConfigException("The directory is not writable by the Web process: {$this->uploadPath}");
        } else {
            $this->uploadPath = realpath($this->uploadPath);
        }
        $this->uploadUrl = rtrim(Yii::getAlias($this->uploadUrl), '/');
    }

    /**
     * 通过UploadedFile上传文件.
     *
     * @param UploadedFile $uploadedFile UploadFile对象实例.
     * @param string $dir 上传文件夹的名称.
     * @param string $savePath 指定保存路径.
     * @return File
     */
    public function uploadByUploadFile($uploadedFile, $dir, $savePath = null)
    {
        $file = new File;

        if ($uploadedFile === null) {
            $file->error = File::UPLOAD_ERROR_NO_UPLOADED_FILE;
            return $file;
        }
        // 检查上传文件是否有错
        if ($uploadedFile->getHasError()) {
            $file->error = $uploadedFile->error;
            return $file;
        }

        $type = $uploadedFile->getExtension();
        $file->error = $this->checkErrors($uploadedFile->size, $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($type, $dir);
        }

        if ($uploadedFile->saveAs($savePath)) {
            $file->url = $this->savePath2Url($savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_UPLOAD;
        }

        return $file;
    }

    /**
     * 通过文件内容上传文件.
     *
     * @param string $contents 文件内容
     * @param string $dir 上传文件夹的名称.
     * @param string $type 上传文件的类型(文件后缀名).
     * @param string $savePath 指定保存路径.
     * @return File.
     */
    public function uploadByContents($contents, $dir, $type, $savePath = null)
    {
        $file = new File;

        if (empty($contents)) {
            $file->error = File::UPLOAD_ERROR_NO_CONTENT;
            return $file;
        }

        $file->error = $this->checkErrors(mb_strlen($contents, '8bit'), $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($type, $dir);
        }

        if ($fp = fopen($savePath, 'w+')) {
            fwrite($fp, $contents);
            fclose($fp);
            $file->url = $this->savePath2Url($savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_UPLOAD;
        }

        return $file;
    }

    /**
     * 通过本地文件上传文件.
     *
     * @param string $localFilePath 本地文件路径
     * @param string $dir 上传文件夹的名称.
     * @param string $savePath 指定保存路径.
     * @return File
     */
    public function uploadByLocalFile($localFilePath, $dir, $savePath = null)
    {
        $file = new File;

        if (is_file($localFilePath)) {
            $file->error = File::UPLOAD_ERROR_NO_LOCAL_FILE;
            return $file;
        }

        $type = pathinfo($localFilePath, PATHINFO_EXTENSION);

        $file->error = $this->checkErrors(filesize($localFilePath), $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($type, $dir);
        }

        if (copy($localFilePath, $savePath)) {
            $file->url = $this->savePath2Url($savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_UPLOAD;
        }

        return $file;
    }

    /**
     * 转换文件类型(主要为图片格式转换).
     *
     * @param string $url 待转换的文件url(上传后得到的url).
     * @param string $type 要转换的目标文件类型(文件后缀名).
     * @return array File.
     */
    public function convert($url, $type)
    {
        if ($this->convertServer === null) {
            throw new InvalidConfigException('The "convertServer" property must be set.');
        }

        $file = new File;

        $path = $this->url2SavePath($url);
        $contents = file_get_contents($path);
        if ($contents === false) {
            $file->error = File::UPLOAD_ERROR_NO_URL;
            return $file;
        }

        /**
         * @var Curl $curl
         */
        $curl = Yii::$app->curl;
        $convertedContents = $curl->post($this->convertServer, ['type' => $type], $contents);

        if ($convertedContents !== false) {
            $dir = $this->getDirFormSavePath($path);
            $savePath = $this->changeType($path, $type);
            return $this->uploadByContents($convertedContents, $dir, $type, $savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_CONVERT;
            return $file;
        }
    }

    /**
     * 检查文件上传过程中是否有相关错误.
     *
     * @param integer $size 文件大小, 单位为byte.
     * @param string $dir 上传文件夹的名称.
     * @param string $type 上传文件的类型(文件后缀名).
     * @return integer 错误代码. 无错误则返回[[UPLOAD_ERR_OK]].
     */
    private function checkErrors($size, $dir, $type)
    {
        // 检查上传文件大小是否超过设定最大值
        if ($size > $this->maxSize) {
            return File::UPLOAD_ERROR_SIZE;
        }
        // 检查目录参数是否被允许
        if (!isset($this->allowedTypes[$dir])) {
            return File::UPLOAD_ERROR_DIR_NOT_ALLOWED;
        }
        // 检查文件类型是否被允许
        if (!in_array($type, $this->allowedTypes[$dir])) {
            return File::UPLOAD_ERROR_TYPE_NOT_ALLOWED;
        }

        return UPLOAD_ERR_OK;
    }

    /**
     * 获取上传文件待保存的绝对路径.
     *
     * @param string $type 上传文件的类型(文件后缀名).
     * @param string $dir 上传文件夹的名称.
     * @return string
     */
    private function getSavePath($type, $dir)
    {
        if ($this->getSavePath !== null) {
            return call_user_func($this->getSavePath, $type, $dir, $this);
        }

        $path = $this->uploadPath . '/' . $dir;
        $ymd = date('Ymd');
        $path .= '/' . $ymd;
        if (!is_dir($path)) {
            FileHelper::createDirectory($path, $this->dirMode, true);
        }

        // 上传文件名
        $fileName = date('YmdHis') . '_' . rand(10000, 99999) . '.' . $type;
        $path .= '/' . $fileName;

        return $path;
    }

    private function savePath2Url($savePath)
    {
        $pathArray = explode($this->uploadPath, $savePath, 2);
        $pathArray[0] = $this->uploadUrl;
        return implode('', $pathArray);
    }

    private function url2SavePath($url)
    {
        $pathArray = explode($this->uploadUrl, $url, 2);
        $pathArray[0] = $this->uploadPath;
        return implode('', $pathArray);
    }

    private function getDirFormSavePath($savePath)
    {
        $pathArray = explode($this->uploadPath, $savePath, 2);
        $dirArray = explode('/', $pathArray[1], 3);

        return $dirArray[1];
    }

    private function changeType($savePath, $type)
    {
        $pathArray =  explode('.', $savePath, -1);
        $pathArray[] = $type;

        return implode('.', $pathArray);
    }
}
