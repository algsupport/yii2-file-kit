<?php
namespace algsupport\filekit\actions;

use Yii;
use algsupport\filekit\Storage;
use yii\base\Action;
use yii\di\Instance;
use yii\base\InvalidConfigException;

/**
 * Class BaseAction
 * @package algsupport\filekit\actions
 * @author Eugene Terentev <eugene@terentev.net>
 */
abstract class BaseAction extends Action
{
    /**
     * @var string file storage component name
     */
    public string $fileStorage = 'fileStorage';
    /**
     * @var string Request param name that provides file storage component name
     */
    public string $fileStorageParam = 'fileStorage';
    /**
     * @var string session key to store list of uploaded files
     */
    public string $sessionKey = '_uploadedFiles';
    /**
     * Allows users to change filestorage by passing GET variable
     * @var bool
     */
    public bool $allowChangeFilestorage = false;

	/**
	 * @throws InvalidConfigException
	 */
	protected function getFileStorage(): array|object|string
	{
        if ($this->allowChangeFilestorage) {
            $fileStorage = Yii::$app->request->get($this->fileStorageParam);
        } else {
            $fileStorage = $this->fileStorage;
        }
	    return Instance::ensure($fileStorage, Storage::class);
    }
}
