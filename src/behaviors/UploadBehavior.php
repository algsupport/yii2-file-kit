<?php
namespace algsupport\filekit\behaviors;

use Throwable;
use Exception;
use yii\db\ActiveQuery;
use algsupport\filekit\Storage;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\db\StaleObjectException;
use yii\db\ActiveQueryInterface;
use yii\base\InvalidConfigException;

/**
 * Class UploadBehavior
 * @author Eugene Terentev <eugene@terentev.net>
 */
class UploadBehavior extends Behavior
{
    /**
     * @var ActiveRecord
     */
    public $owner;

    /**
     * @var string Model attribute that contain uploaded file information
     * or array of files information
     */
    public string $attribute = 'file';

    /**
     * @var bool
     */
    public bool $multiple = false;

    public $attributePrefix;

    public $attributePathName = 'path';
    public $attributeBaseUrlName = 'base_url';
    /**
     * @var string
     */
    public string $pathAttribute;
    /**
     * @var string
     */
    public string $baseUrlAttribute;
    /**
     * @var string
     */
    public $typeAttribute;
    /**
     * @var string
     */
    public $sizeAttribute;
    /**
     * @var string
     */
    public $nameAttribute;
    /**
     * @var string
     */
    public $orderAttribute;

    /**
     * @var string name of the relation
     */
    public string $uploadRelation;
    /**
     * @var $uploadModel
     * Schema example:
     *      `id` INT NOT NULL AUTO_INCREMENT,
     *      `path` VARCHAR(1024) NOT NULL,
     *      `base_url` VARCHAR(255) NULL,
     *      `type` VARCHAR(255) NULL,
     *      `size` INT NULL,
     *      `name` VARCHAR(255) NULL,
     *      `order` INT NULL,
     *      `foreign_key_id` INT NOT NULL,
     */
    public $uploadModel;
    /**
     * @var string
     */
    public string $uploadModelScenario = 'default';

    public $filesStorage = 'fileStorage';

    protected $deletePaths;

    protected $storage;

    public function events(): array
    {
        $multipleEvents = [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFindMultiple',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsertMultiple',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdateMultiple',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDeleteMultiple',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete'
        ];

        $singleEvents = [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFindSingle',
            ActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidateSingle',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdateSingle',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdateSingle',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDeleteSingle',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete'
        ];

        return $this->multiple ? $multipleEvents : $singleEvents;
    }

    /**
     * @return array
     */
    public function fields(): array
    {
        $fields = [
            $this->attributePathName ? : 'path' => $this->pathAttribute,
            $this->attributeBaseUrlName ? : 'base_url' => $this->baseUrlAttribute,
            'type' => $this->typeAttribute,
            'size' => $this->sizeAttribute,
            'name' => $this->nameAttribute,
            'order' => $this->orderAttribute
        ];

        if ($this->attributePrefix !== null) {
            $fields = array_map(function ($fieldName) {
                return $this->attributePrefix . $fieldName;
            }, $fields);
        }

        return $fields;
    }

	/**
	 * @throws Exception
	 */
	public function afterValidateSingle()
    {
        $this->loadModel($this->owner, $this->owner->{$this->attribute});
    }

	/**
	 * @throws Exception
	 */
	public function afterInsertMultiple()
    {
        if ($this->owner->{$this->attribute}) {
            $this->saveFilesToRelation($this->owner->{$this->attribute});
        }
    }

	/**
	 * @throws Throwable
	 * @throws StaleObjectException
	 * @throws InvalidConfigException
	 */
	public function afterUpdateMultiple()
    {
        $filesPaths = ArrayHelper::getColumn($this->getUploaded(), 'path');
        $models = $this->owner->getRelation($this->uploadRelation)->all();
        $modelsPaths = ArrayHelper::getColumn($models, $this->getAttributeField('path'));
        $newFiles = $updatedFiles = [];
        foreach ($models as $model) {
            $path = $model->getAttribute($this->getAttributeField('path'));
            if (!in_array($path, $filesPaths, true) && $model->delete()) {
                $this->getStorage()->delete($path);
            }
        }
        foreach ($this->getUploaded() as $file) {
            if (!in_array($file['path'], $modelsPaths, true)) {
                $newFiles[] = $file;
            } else {
                $updatedFiles[] = $file;
            }
        }
        $this->saveFilesToRelation($newFiles);
        $this->updateFilesInRelation($updatedFiles);
    }


	/**
	 * @throws Exception
	 */
	public function beforeUpdateSingle()
    {
        $this->deletePaths = $this->owner->getOldAttribute($this->getAttributeField('path'));
    }


	/**
	 * @throws InvalidConfigException
	 * @throws Exception
	 */
	public function afterUpdateSingle()
    {
        $path = $this->owner->getAttribute($this->getAttributeField('path'));
        if (!empty($this->deletePaths) && $this->deletePaths !== $path) {
            $this->deleteFiles();
        }
    }

       public function beforeDeleteMultiple()
    {
        $this->deletePaths = ArrayHelper::getColumn($this->getUploaded(), 'path');
    }

	/**
	 * @throws Exception
	 */
	public function beforeDeleteSingle()
    {
        $this->deletePaths = $this->owner->getAttribute($this->getAttributeField('path'));
    }

	/**
	 * @throws InvalidConfigException
	 */
	public function afterDelete()
    {
        $this->deletePaths = is_array($this->deletePaths) ? array_filter($this->deletePaths) : $this->deletePaths;
        $this->deleteFiles();
    }


	/**
	 * @throws InvalidConfigException
	 * @throws Exception
	 */
	public function afterFindMultiple()
    {
        $models = $this->owner->{$this->uploadRelation};
        $fields = $this->fields();
        $data = [];
        foreach ($models as $k => $model) {
            $file = [];
            foreach ($fields as $dataField => $modelAttribute) {
                $file[$dataField] = $model->hasAttribute($modelAttribute)
                    ? ArrayHelper::getValue($model, $modelAttribute)
                    : null;
            }
            if ($file['path']) {
                $data[$k] = $this->enrichFileData($file);
            }

        }
        $this->owner->{$this->attribute} = $data;
    }


	/**
	 * @throws InvalidConfigException
	 */
	public function afterFindSingle()
    {
        $file = array_map(function ($attribute) {
            return $this->owner->getAttribute($attribute);
        }, $this->fields());
        if ($file['path'] !== null && $file['base_url'] === null){
            $file['base_url'] = $this->getStorage()->baseUrl;
        }
        if (array_key_exists('path', $file) && $file['path']) {
            $this->owner->{$this->attribute} = $this->enrichFileData($file);
        }
    }

    public function getUploadModelClass(): string
    {
        if (!$this->uploadModel) {
            $this->uploadModel = $this->getUploadRelation()->modelClass;
        }
        return $this->uploadModel;
    }

	/**
	 * @throws Exception
	 */
	protected function saveFilesToRelation($files)
    {
        $modelClass = $this->getUploadModelClass();
        foreach ($files as $file) {
            $model = new $modelClass;
            $model->setScenario($this->uploadModelScenario);
            $model = $this->loadModel($model, $file);
            if ($this->getUploadRelation()->via !== null) {
                $model->save(false);
            }
            $this->owner->link($this->uploadRelation, $model);
        }
    }

	/**
	 * @throws Exception
	 */
	protected function updateFilesInRelation($files)
    {
        $modelClass = $this->getUploadModelClass();
        foreach ($files as $file) {
            $model = $modelClass::findOne([$this->getAttributeField('path') => $file['path']]);
            if ($model) {
                $model->setScenario($this->uploadModelScenario);
                $model = $this->loadModel($model, $file);
                $model->save(false);
            }
        }
    }

	/**
	 * @throws InvalidConfigException
	 */
	protected function getStorage(): object|array|string
    {
        if (!$this->storage) {
            $this->storage = Instance::ensure($this->filesStorage, Storage::class);
        }
        return $this->storage;

    }

    protected function getUploaded(): array
    {
        $files = $this->owner->{$this->attribute};
        return $files ?: [];
    }

    protected function getUploadRelation(): ActiveQueryInterface|ActiveQuery|null
    {
        return $this->owner->getRelation($this->uploadRelation);
    }

	/**
	 * @throws Exception
	 */
	protected function loadModel($model, $data)
    {
        $attributes = array_flip($model->attributes());
        foreach ($this->fields() as $dataField => $modelField) {
            if ($modelField && array_key_exists($modelField, $attributes)) {
                $model->{$modelField} =  ArrayHelper::getValue($data, $dataField);
            }
        }
        return $model;
    }

	/**
	 * @throws Exception
	 */
	protected function getAttributeField($type)
    {
        return ArrayHelper::getValue($this->fields(), $type, false);
    }

	/**
	 * @throws InvalidConfigException
	 */
	protected function deleteFiles()
	{
        $storage = $this->getStorage();
        if ($this->deletePaths !== null) {
            return is_array($this->deletePaths)
                ? $storage->deleteAll($this->deletePaths)
                : $storage->delete($this->deletePaths);
        }
        return true;
    }

	/**
	 * @throws InvalidConfigException
	 */
	protected function enrichFileData($file)
    {
        $fs = $this->getStorage()->getFilesystem();
        if ($file['path'] && $fs->fileExists($file['path'])) {
            $data = [
                'type' => $fs->mimeType($file['path']),
                'size' => $fs->fileSize($file['path']),
                'timestamp' => $fs->lastModified($file['path'])
            ];
            foreach ($data as $k => $v) {
                if (!array_key_exists($k, $file) || !$file[$k]) {
                    $file[$k] = $v;
                }
            }
        }
        if ($file['path'] !== null && $file['base_url'] === null) {
            $file['base_url'] = $this->getStorage()->baseUrl;
        }
        return $file;
    }
}
