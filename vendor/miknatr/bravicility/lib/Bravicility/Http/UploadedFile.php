<?php

namespace Bravicility\Http;

class UploadedFile
{
    protected $tmpFileName;
    protected $name;
    protected $contentType;
    protected $size;

    /**
     * @param array $data
     * @return UploadedFile
     */
    public static function createFromPhpUpload(array $data)
    {
        if (is_array($data['name'])) {
            throw new \LogicException('We do not support arrays of uploaded files');
        }

        // http://www.php.net/manual/en/features.file-upload.post-method.php

        if (empty($data['name'])) {
            throw new UploadedFileException('Не указано имя загружаемого файла');
        }
        $filename = $data['name'];

        if (!empty($data['error'])) {
            throw new UploadedFileException(static::phpUploadErrorDescription($data['error'], $filename));
        }

        if (empty($data['tmp_name']) || empty($data['type'])) {
            throw new UploadedFileException("Ошибка при загрузке файла {$filename}");
        }

        $size = empty($data['size']) ? filesize($data['tmp_name']) : $data['size'];

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = new static($data['tmp_name']);
        return $uploadedFile
            ->setName($filename)
            ->setContentType($data['type'])
            ->setSize($size)
        ;
    }

    protected static function phpUploadErrorDescription($code, $filename)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:   // The uploaded file exceeds the upload_max_filesize directive in php.ini
            case UPLOAD_ERR_FORM_SIZE:  // The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form
                return "Загруженный файл {$filename} слишком большой";

            case UPLOAD_ERR_PARTIAL:    // The uploaded file was only partially uploaded
            case UPLOAD_ERR_NO_FILE:    // No file was uploaded
            case UPLOAD_ERR_NO_TMP_DIR: // Missing a temporary folder
            case UPLOAD_ERR_CANT_WRITE: // Failed to write file to disk
            case UPLOAD_ERR_EXTENSION:  // File upload stopped by extension
            default:
                return "Ошибка при загрузке файла {$filename}";
        }
    }

    public function __construct($tmpFileName)
    {
        $this->tmpFileName = $tmpFileName;
    }


    //
    // SAVING THE FILE
    //

    public function move($filename)
    {
        if (!@move_uploaded_file($this->tmpFileName, $filename)) {
            throw new UploadedFileException("Ошибка при сохранении загруженного файла");
        }
    }


    //
    // PROPERTIES
    //

    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return UploadedFile
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     * @return UploadedFile
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     * @return UploadedFile
     */
    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }
}
