<?php

namespace wenbin1989\yii2\upload;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use Yii;

/**
 * Uploader component.
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
     * @var integer max uploading file size, in bytes. Default is 10Mb.
     */
    public $maxSize = 10000000;

    /**
     * @var string image format convert server url.
     */
    public $convertServer;

    /**
     * @var array allowed upload file types, array format is:
     *
     * ~~~
     * [
     *     'directory' => ['type1', 'type2', ...],
     *     ...,
     * ]
     * ~~~
     *
     * e.g.:
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
     * function ($dir, $type, $uploader) {
     *     // $dir uploading file directory.
     *     // $type uploading file type(file extension).
     *     // $uploader current Uploader instance.
     * }
     * ```
     *
     */
    public $savePathGenerator;

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
     * upload by UploadedFile instance.
     *
     * @param UploadedFile $uploadedFile Uploaded file instance
     * @param string $dir uploading file directory.
     * @param string $savePath upload file save path. If null, call [[getSavePath()]] to generate one.
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

        if ($savePath !== null) {
            $dir = $this->getDir($savePath);
        }
        $type = $uploadedFile->getExtension();

        $file->error = $this->getErrors($uploadedFile->size, $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($dir, $type);
        }

        if ($uploadedFile->saveAs($savePath)) {
            $file->url = $this->savePath2Url($savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_UPLOAD;
        }

        return $file;
    }

    /**
     * upload file by file contents.
     *
     * @param string $contents file contents in binary.
     * @param string $dir uploading file directory.
     * @param string $type uploading file type(file extension).
     * @param string $savePath upload file save path. If null, call [[getSavePath()]] to generate one.
     * @return File
     */
    public function uploadByContents($contents, $dir, $type, $savePath = null)
    {
        $file = new File;

        if (empty($contents)) {
            $file->error = File::UPLOAD_ERROR_NO_CONTENT;
            return $file;
        }

        if ($savePath !== null) {
            $dir = $this->getDir($savePath);
        }

        $file->error = $this->getErrors(mb_strlen($contents, '8bit'), $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($dir, $type);
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
     * upload file by local file.
     *
     * @param string $localFilePath local file path.
     * @param string $dir uploading file directory.
     * @param string $savePath upload file save path. If null, call [[getSavePath()]] to generate one.
     * @return File
     */
    public function uploadByLocalFile($localFilePath, $dir, $savePath = null)
    {
        $file = new File;

        if (is_file($localFilePath)) {
            $file->error = File::UPLOAD_ERROR_NO_LOCAL_FILE;
            return $file;
        }

        if ($savePath !== null) {
            $dir = $this->getDir($savePath);
        }
        $type = pathinfo($localFilePath, PATHINFO_EXTENSION);

        $file->error = $this->getErrors(filesize($localFilePath), $dir, $type);
        if ($file->error !== UPLOAD_ERR_OK) {
            return $file;
        }

        if ($savePath === null) {
            $savePath = $this->getSavePath($dir, $type);
        }

        if (copy($localFilePath, $savePath)) {
            $file->url = $this->savePath2Url($savePath);
        } else {
            $file->error = File::UPLOAD_ERROR_UPLOAD;
        }

        return $file;
    }

    /**
     * convert uploaded image file format. upload coverted file in the same path of src file.
     *
     * @param string $fileUrl uploaded file url.
     * @param string $type file format to convert(file extension).
     * @return File
     */
    public function convertUploadedFile($fileUrl, $type)
    {
        $file = new File;

        $path = $this->url2SavePath($fileUrl);
        $contents = file_get_contents($path);
        if ($contents === false) {
            $file->error = File::UPLOAD_ERROR_NO_LOCAL_FILE;
            return $file;
        }

        $coverted = $this->convert($contents, $type);
        if ($coverted === false) {
            $file->error = File::UPLOAD_ERROR_CONVERT;
            return $file;
        }

        $dir = $this->getDir($path);
        $savePath = $this->changeFileExtentsion($path, $type);
        return $this->uploadByContents($coverted, $dir, $type, $savePath);
    }

    /**
     * convert image file format.
     *
     * @param string $srcData src file data to convert, in binary.
     * @param string $type file format to convert(file extension).
     * @return mixed converted file data, in binary, if succeed; false if failure.
     */
    public function convert($srcData, $type)
    {
        if ($this->convertServer === null) {
            throw new InvalidConfigException('The "convertServer" property must be set.');
        }
        /**
         * @var Curl $curl
         */
        $curl = Yii::$app->curl;
        $convertedData = $curl->post($this->convertServer, ['type' => $type], $srcData);

        return $convertedData;
    }

    /**
     * get upload file errors.
     *
     * @param integer $size file size in byte.
     * @param string $dir uploading file directory.
     * @param string $type uploading file type(file extension).
     * @return integer error code. return [[UPLOAD_ERR_OK]] if no error.
     */
    private function getErrors($size, $dir, $type)
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
     * get upload dir param from save path.
     *
     * @param string $savePath uploaded file save path.
     * @return string
     * @throws \yii\base\InvalidParamException if $savePath is not start with [[$uploadPath]].
     */
    private function getDir($savePath)
    {
        if (0 !== strpos($savePath, $this->uploadPath)) {
            throw new InvalidParamException('Invalid savePath param.');
        }

        $pathArray = explode($this->uploadPath, $savePath, 2);
        $dirArray = explode('/', $pathArray[1], 3);

        return $dirArray[1];
    }

    /**
     * get file url by save path.
     * @param string $savePath uploaded file save path.
     * @return string file url.
     * @throws \yii\base\InvalidParamException if $savePath is not start with [[$uploadPath]].
     */
    public function savePath2Url($savePath)
    {
        if (0 !== strpos($savePath, $this->uploadPath)) {
            throw new InvalidParamException('Invalid savePath param.');
        }

        $pathArray = explode($this->uploadPath, $savePath, 2);
        $pathArray[0] = $this->uploadUrl;
        return implode('', $pathArray);
    }

    /**
     * get file save path by url.
     * @param string $url uploaded file url.
     * @return string file path.
     * @throws \yii\base\InvalidParamException $url is not start with [[$uploadUrl]].
     */
    public function url2SavePath($url)
    {
        if (0 !== strpos($url, $this->uploadUrl)) {
            throw new InvalidParamException('Invalid url param.');
        }

        $pathArray = explode($this->uploadUrl, $url, 2);
        $pathArray[0] = $this->uploadPath;
        return implode('', $pathArray);
    }

    /**
     * change file extentsion.
     *
     * @param string $filePath file path to change.
     * @param string $extentsion file extentsion to change.
     * @return string new file path with $extentsion.
     */
    private function changeFileExtentsion($filePath, $extentsion)
    {
        $pathArray =  explode('.', $filePath, -1);
        $pathArray[] = $extentsion;
        return implode('.', $pathArray);
    }

    /**
     * get upload file path to save.
     *
     * @param string $dir uploading file directory.
     * @param string $type uploading file type(file extension).
     * @return string
     */
    private function getSavePath($dir, $type)
    {
        if ($this->savePathGenerator !== null) {
            return call_user_func($this->savePathGenerator, $dir, $type, $this);
        }

        $path = $this->uploadPath . '/' . $dir;
        $ymd = date('Ymd');
        $path .= '/' . $ymd;
        if (!is_dir($path)) {
            FileHelper::createDirectory($path, $this->dirMode, true);
        }

        // upload file name
        $fileName = date('YmdHis') . '_' . rand(10000, 99999) . '.' . $type;
        $path .= '/' . $fileName;

        return $path;
    }
}
