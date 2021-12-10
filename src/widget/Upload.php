<?php
namespace trntv\filekit\widget;

use Yii;
use ArrayObject;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\jui\JuiAsset;
use yii\widgets\InputWidget;

/**
 * Class Upload
 * @package trntv\filekit\widget
 */
class Upload extends InputWidget
{

    /**
     * Available errors handlers
     */
    const YII_ERROR_HANDLER = 'yii';
    const POPOVER_ERROR_HANDLER = 'popover';

    /**
     * @var
     */
    public $files;
    /**
     * @var array|ArrayObject
     */
    public array|ArrayObject $url;

    /**
     * @var string path where files would be stored
     */
    public string $uploadPath = '';

    /**
     * @var array
     */
    public array $clientOptions = [];
    /**
     * @var bool
     */
    public bool $showPreviewFilename = false;
    /**
     * @var bool
     */
    public bool $editFilename = false;
    /**
     * @var bool
     */
    public bool $multiple = false;
    /**
     * @var bool
     */
    public bool $sortable = false;
    /**
     * @var int min file size in bytes
     */
    public $minFileSize;
    /**
     * @var int
     */
    public int $maxNumberOfFiles = 1;
    /**
     * @var int max file size in bytes
     */
    public $maxFileSize;
    /**
     * @var string regexp
     */
    public $acceptFileTypes;
    /**
     * @var string
     */
    public string $messagesCategory = 'filekit/widget';
    /**
     * @var bool preview image file or not in the upload box.
     */
    public bool $previewImage = true;
    /**
     * custom hiddenInput id，if not set $this->options['id'] will be use.
     * useful if you use name,value
     * @var null|string
     */
    public ?string $hiddenInputId = null;
    /**
     * Client error handler.
     * For using yiiActiveForm set to 'yii'
     * @var string
     */
    public string $errorHandler = self::POPOVER_ERROR_HANDLER;

	/**
	 * @throws InvalidConfigException
	 */
	public function init()
    {
        parent::init();

        $this->registerMessages();

        if ($this->maxNumberOfFiles > 1 || $this->multiple) {
            $this->multiple = true;
        }
        if ($this->hasModel()) {
            $this->name = $this->name ?: Html::getInputName($this->model, $this->attribute);
            $this->value = $this->value ?: Html::getAttributeValue($this->model, $this->attribute);
        }
        if (!array_key_exists('name', $this->clientOptions)) {
            $this->clientOptions['name'] = $this->name;
        }
        if ($this->multiple && $this->value && !is_array($this->value)) {
            throw new InvalidArgumentException('In "multiple" mode, value must be an array.');
        }
        if (!array_key_exists('fileparam', $this->url)) {
            $this->url['fileparam'] = $this->getFileInputName();
        }
        if (!$this->files && $this->value) {
            $this->files = $this->multiple ? $this->value : [$this->value];
        }

        if (!array_key_exists('upload-path', $this->url) && !empty($this->uploadPath)) {
            $this->url['upload-path'] = $this->uploadPath;
        }
        if(!in_array($this->errorHandler,[self::YII_ERROR_HANDLER, self::POPOVER_ERROR_HANDLER]))
            throw new InvalidArgumentException('errorHandler param can be "yii" or "popover"');
        if($this->hasModel() && $this->errorHandler == self::YII_ERROR_HANDLER){
            $this->addFieldValidator();
        } else {
            $this->errorHandler = self::POPOVER_ERROR_HANDLER;
        }

        $this->clientOptions = ArrayHelper::merge(
            [
                'url' => Url::to($this->url),
                'multiple' => $this->multiple,
                'sortable' => $this->sortable,
                'maxNumberOfFiles' => $this->maxNumberOfFiles,
                'maxFileSize' => $this->maxFileSize,
                'minFileSize' => $this->minFileSize,
                'acceptFileTypes' => $this->acceptFileTypes,
                'files' => $this->files,
                'previewImage' => $this->previewImage,
                'showPreviewFilename' => $this->showPreviewFilename,
                'editFilename' => $this->editFilename,
                'errorHandler' => $this->errorHandler,
                'pathAttribute' => 'path',
                'baseUrlAttribute' => 'base_url',
                'pathAttributeName' => 'path',
                'baseUrlAttributeName' => 'base_url',
                'messages' => [
                    'maxNumberOfFiles' => Yii::t($this->messagesCategory, 'Maximum number of files exceeded'),
                    'acceptFileTypes' => Yii::t($this->messagesCategory, 'File type not allowed'),
                    'maxFileSize' => Yii::t($this->messagesCategory, 'File is too large'),
                    'minFileSize' => Yii::t($this->messagesCategory, 'File is too small')
                ]
            ],
            $this->clientOptions
        );
    }

    /**
     * @return void Registers widget translations
     */
    protected function registerMessages()
    {
        if (!array_key_exists($this->messagesCategory, Yii::$app->i18n->translations)) {
            Yii::$app->i18n->translations[$this->messagesCategory] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en-US',
                'basePath' => __DIR__ . '/messages',
                'fileMap' => [
                    $this->messagesCategory => 'filekit/widget.php'
                ],
            ];
        }
    }

    /**
     * @return string
     */
    public function getFileInputName(): string
    {
        return sprintf('_fileinput_%s', $this->id);
    }

    /**
     * @return string
     */
    public function run(): string
    {
        $this->registerClientScript();
        $content = Html::beginTag('div');
        $content .= Html::hiddenInput($this->name, null, [
            'class' => 'empty-value',
            'id' => $this->hiddenInputId === null ? $this->options['id'] : $this->hiddenInputId
        ]);
        $content .= Html::fileInput($this->getFileInputName(), null, [
            'name' => $this->getFileInputName(),
            'id' => $this->getId(),
            'multiple' => $this->multiple
        ]);
        $content .= Html::endTag('div');
        return $content;
    }

    /**
     * Registers required script for the plugin to work as jQuery File Uploader
     */
    public function registerClientScript()
    {
        UploadAsset::register($this->getView());
        $options = Json::encode($this->clientOptions);
        if ($this->sortable) {
            JuiAsset::register($this->getView());
        }
        $this->getView()->registerJs("jQuery('#{$this->getId()}').yiiUploadKit($options);");
    }

    /**
     * Adding attribute to yiiActiveForm validator
     */
    public function addFieldValidator()
    {
        $inputID = $this->field->inputId;
        if (isset($this->field->selectors['error'])) {
            $errorSelector = $this->field->selectors['error'];
        } elseif (isset($this->field->errorOptions['class'])) {
            $errorSelector = '.' . implode('.', preg_split('/\s+/', $this->field->errorOptions['class'], -1, PREG_SPLIT_NO_EMPTY));
        } else {
            $errorSelector = $this->field->errorOptions['tag'] ?? 'span';
        }
        $clientValidator[] = [
            'id' => Html::getInputId($this->model, $this->attribute),
            'name' => $this->attribute,
            'container' => $this->field->selectors['container'] ?? ".field-$inputID",
            'input' => $this->field->selectors['input'] ?? "#$inputID",
            'error' => $errorSelector
        ];
        $this->field->form->attributes = ArrayHelper::merge($this->field->form->attributes, $clientValidator);
    }
}
