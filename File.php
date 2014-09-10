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
     * @var integer error code.
     */
    public $error;
    /**
     * @var string error message.
     */
    public $message;
    /**
     * @var string file url.
     */
    public $url;

    // when upload by UploadedFile instance, the instance is null.
    const UPLOAD_ERROR_NO_UPLOADED_FILE = 100;
    // when upload by file content, the content is empty.
    const UPLOAD_ERROR_NO_CONTENT = 101;
    // when upload by local file, the local file doesn't exist.
    const UPLOAD_ERROR_NO_LOCAL_FILE = 102;

    // upload file size is larger than max allowed size.
    const UPLOAD_ERROR_SIZE = 200;
    // upload dir is not allowed.
    const UPLOAD_ERROR_DIR_NOT_ALLOWED = 201;
    // upload file type(file extension) is not allowed.
    const UPLOAD_ERROR_TYPE_NOT_ALLOWED = 202;

    // upload process error. (I/O errors, etc.)
    const UPLOAD_ERROR_UPLOAD = 300;
    // convert file format error.
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
 