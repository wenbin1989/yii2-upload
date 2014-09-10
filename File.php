<?php

namespace wenbin1989\yii2\upload;

use yii\base\Model;

/**
 * File Model
 *
 * @author Wenbin Wang <wenbin1989@gmail.com>
 */
class File extends Model
{
    /**
     * @var integer 上传文件错误代码.
     */
    public $error;
    /**
     * @var string 上传文件错误信息.
     */
    public $message;
    /**
     * @var string 上传文件url.
     */
    public $url;

    // 通过UploadedFile上传文件时, UploadedFile对象为空.
    const UPLOAD_ERROR_NO_UPLOADED_FILE = 100;
    // 通过内容上传文件时, 内容为空.
    const UPLOAD_ERROR_NO_CONTENT = 101;
    // 通过本地文件上传文件时, 没有本地文件.
    const UPLOAD_ERROR_NO_LOCAL_FILE = 102;
    // 通过URL上传文件时, 没有指定的URL.
    const UPLOAD_ERROR_NO_URL = 103;

    // 上传文件超过最大允许大小
    const UPLOAD_ERROR_SIZE = 200;
    // 上传目录不被允许
    const UPLOAD_ERROR_DIR_NOT_ALLOWED = 201;
    // 上传文件类型(后缀名)不被允许
    const UPLOAD_ERROR_TYPE_NOT_ALLOWED = 202;

    // 上传过程出错(文件读写失败)
    const UPLOAD_ERROR_UPLOAD = 300;
    // 转换文件出错
    const UPLOAD_ERROR_CONVERT = 301;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['error', 'default', 'value' => UPLOAD_ERR_OK],
        ];
    }
}
 