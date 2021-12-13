<?php
namespace trntv\filekit;

use Yii;
use yii\base\Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToWriteFile;
use trntv\filekit\events\StorageEvent;
use trntv\filekit\filesystem\FilesystemBuilderInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

/**
 * Class Storage
 * @package trntv\filekit
 * @author Eugene Terentev <eugene@terentev.net>
 */
class Storage extends Component
{
    /**
     * Event triggered after delete
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    /**
     * Event triggered after save
     */
    const EVENT_BEFORE_SAVE = 'beforeSave';
    /**
     * Event triggered after delete
     */
    const EVENT_AFTER_DELETE = 'afterDelete';
    /**
     * Event triggered after save
     */
    const EVENT_AFTER_SAVE = 'afterSave';
    /**
     * @var
     */
    public $baseUrl;
    /**
     * @var
     */
    public $filesystemComponent;
    /**
     * @var
     */
    protected $filesystem;
    /**
     * Max files in directory
     * "-1" = unlimited
     * @var int
     */
    public int $maxDirFiles = 65535; // Default: Fat32 limit
    /**
     * An array default config when save file.
     * It can be a callable for more flexible
     *
     * ```php
     * function (\trntv\filekit\File $fileObj) {
     *
     *      return ['ContentDisposition' => 'filename="' . $fileObj->getPathInfo('filename') . '"'];
     * }
     * ```
     *
     * @var array|callable
     * @since 2.0.2
     */
    public $defaultSaveConfig = [];
    /**
     * @var bool
     */
    public bool $useDirindex = true;
    /**
     * @var int
     */
    private int $dirindex = 1;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->baseUrl !== null) {
            $this->baseUrl = Yii::getAlias($this->baseUrl);
        }

        if ($this->filesystemComponent !== null) {
            $this->filesystem = Yii::$app->get($this->filesystemComponent);
        } else {
            $this->filesystem = Yii::createObject($this->filesystem);
            if ($this->filesystem instanceof FilesystemBuilderInterface) {
                $this->filesystem = $this->filesystem->build();
            }
        }
    }

    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @param $filesystem
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
    }

	/**
	 * @throws Exception
	 * @throws InvalidConfigException
	 */
	public function save($file, $preserveFileName = false, $mode = 'r+', $config = [], $pathPrefix = ''): bool|string
    {
		if (str_contains($file, 'https')){
			$file = $this->getFilesystem()->readStream($file);
		}
        $pathPrefix = FileHelper::normalizePath($pathPrefix);
        $fileObj = File::create($file);
        $dirIndex = $this->getDirIndex($pathPrefix);
        if ($preserveFileName === false) {
            do {
                $filename = implode('.', [
                    Yii::$app->security->generateRandomString(),
                    $fileObj->getExtension()
                ]);
                $path = implode(DIRECTORY_SEPARATOR, array_filter([$pathPrefix, $dirIndex, $filename]));
            } while ($this->getFilesystem()->fileExists($path));
        } else {
            $filename = $fileObj->getPathInfo('filename');
            $path = implode(DIRECTORY_SEPARATOR, array_filter([$pathPrefix, $dirIndex, $filename]));
        }

        $this->beforeSave($fileObj->getPath(), $this->getFilesystem());

        $stream = fopen($fileObj->getPath(), $mode);

        $defaultConfig = $this->defaultSaveConfig;

        if (is_callable($defaultConfig)) {
            $defaultConfig = call_user_func($defaultConfig, $fileObj);
        }

        if (is_callable($config)) {
            $config = call_user_func($config, $fileObj);
        }

        $config = array_merge(['ContentType' => $fileObj->getMimeType()], $defaultConfig, $config);

        // $success = $this->getFilesystem()->writeStream($path, $stream, $config);
        try {
            $this->getFilesystem()->writeStream($path, $stream, $config);
            $this->afterSave($path, $this->getFilesystem());
            return $path;
        } catch (FilesystemException | UnableToWriteFile $exception) {
            var_dump($exception);
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
        return false;
    }

	/**
	 * @throws Exception
	 * @throws InvalidConfigException
	 */
	public function saveAll($files, $preserveFileName = false, $overwrite = false, array $config = []): array
	{
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $this->save($file, $preserveFileName, $overwrite, $config);
        }
        return $paths;
    }

	/**
	 * @throws InvalidConfigException
	 */
	public function delete($path): bool
	{
        if ($this->getFilesystem()->fileExists($path)) {
            $this->beforeDelete($path, $this->getFilesystem());
            if ($this->getFilesystem()->delete($path)) {
                $this->afterDelete($path, $this->getFilesystem());
                return true;
            }
        }
        return false;
    }

	/**
	 * @throws InvalidConfigException
	 */
	public function deleteAll($files)
    {
        foreach ($files as $file) {
            $this->delete($file);
        }

    }

    /**
     * @param string $path
     * @return false|int|string|null
     */
    protected function getDirIndex(string $path = ''): bool|int|string|null
    {
        if (!$this->useDirindex) {
            return null;
        }
        $normalizedPath = '.dirindex';
        if (isset($path)) {
            $normalizedPath = $path . DIRECTORY_SEPARATOR . '.dirindex';
        }
        if (!$this->getFilesystem()->fileExists($normalizedPath)) {
            $this->getFilesystem()->write($normalizedPath, (string)$this->dirindex);
        } else {
            $this->dirindex = $this->getFilesystem()->read($normalizedPath);
            if ($this->maxDirFiles !== -1) {
                $filesCount = count($this->getFilesystem()->listContents($this->dirindex)->sortByPath()->toArray());
                if ($filesCount > $this->maxDirFiles) {
                    $this->dirindex++;
                    $this->getFilesystem()->write($normalizedPath, (string)$this->dirindex);
                }
            }
        }

        return $this->dirindex;
    }

	/**
	 * @throws InvalidConfigException
	 */
	public function beforeSave($path, $filesystem = null)
    {
        $event = Yii::createObject([
            'class' => StorageEvent::class,
            'path' => $path,
            'filesystem' => $filesystem
        ]);
        $this->trigger(self::EVENT_BEFORE_SAVE, $event);
    }

    /**
     * @param $path
     * @param $filesystem
     * @throws InvalidConfigException
     */
    public function afterSave($path, $filesystem)
    {
        $event = Yii::createObject([
            'class' => StorageEvent::class,
            'path' => $path,
            'filesystem' => $filesystem
        ]);
        $this->trigger(self::EVENT_AFTER_SAVE, $event);
    }

    /**
     * @param $path
     * @param $filesystem
     * @throws InvalidConfigException
     */
    public function beforeDelete($path, $filesystem)
    {
        $event = Yii::createObject([
            'class' => StorageEvent::class,
            'path' => $path,
            'filesystem' => $filesystem
        ]);
        $this->trigger(self::EVENT_BEFORE_DELETE, $event);
    }

    /**
     * @param $path
     * @param $filesystem
     * @throws InvalidConfigException
     */
    public function afterDelete($path, $filesystem)
    {
        $event = Yii::createObject([
            'class' => StorageEvent::class,
            'path' => $path,
            'filesystem' => $filesystem
        ]);
        $this->trigger(self::EVENT_AFTER_DELETE, $event);
    }
}
