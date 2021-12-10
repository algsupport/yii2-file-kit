<?php
namespace trntv\filekit;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;
use yii\base\BaseObject;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;


class File extends BaseObject
{

    protected mixed $path;

    protected mixed $extension;

    protected mixed $size;

    protected string $mimeType;

    protected mixed $pathinfo;

	/**
	 * @throws InvalidConfigException
	 */
	public static function create($file): object|array
	{

        if (is_a($file, self::class)) {
            return $file;
        }

        // UploadedFile
        if (is_a($file, UploadedFile::class)) {
            if ($file->error) {
                throw new InvalidArgumentException("File upload error \"$file->error\"");
            }
            return Yii::createObject([
                'class'=>self::class,
                'path'=>$file->tempName,
                'extension'=>$file->getExtension()
            ]);
        } // Path
        else {
            return Yii::createObject([
                'class' => self::class,
                'path' => FileHelper::normalizePath($file)
            ]);
        }
    }

	/**
	 * @throws InvalidConfigException
	 */
	public static function createAll(array $files): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[] = self::create($file);
        }
        return $result;
    }

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->path === null) {
            throw new InvalidConfigException;
        }
    }

    /**
     * @return mixed
     */
    public function getPath(): mixed
    {
        return $this->path;
    }

    /**
     * @return mixed
     */
    public function getSize(): mixed
    {
        if (!$this->size) {
            $this->size = filesize($this->path);
        }
        return $this->size;
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getMimeType(): string
    {
        if (!$this->mimeType) {
            $this->mimeType = FileHelper::getMimeType($this->path);
        }
        return $this->mimeType;
    }

    public function getExtension(): mixed
    {
        if ($this->extension === null) {
            $this->extension = $this->getPathInfo('extension');
        }
        return $this->extension;
    }


	/**
	 * @throws InvalidConfigException
	 */
	public function getExtensionByMimeType()
    {
        $extensions = FileHelper::getExtensionsByMimeType($this->getMimeType());
        return array_shift($extensions);
    }

    public function getPathInfo(bool $part = false): mixed
    {
        if ($this->pathinfo === null) {
            $this->pathinfo = pathinfo($this->path);
        }
        if ($part !== false) {
            return array_key_exists($part, $this->pathinfo) ? $this->pathinfo[$part] : null;
        }
        return $this->pathinfo;
    }

    /**
     * @param $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @param $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->error !== false;
    }
}
