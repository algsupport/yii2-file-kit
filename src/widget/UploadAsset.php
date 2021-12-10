<?php
namespace trntv\filekit\widget;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;
use rmrevin\yii\fontawesome\NpmFreeAssetBundle;

class UploadAsset extends AssetBundle
{

    public $depends = [
        JqueryAsset::class,
        BlueimpFileuploadAsset::class,
        NpmFreeAssetBundle::class
    ];

    public $sourcePath = __DIR__ . '/assets';

    public $css = [
        YII_DEBUG ? 'css/upload-kit.css' : 'css/upload-kit.min.css'
    ];

    public $js = [
        YII_DEBUG ? 'js/upload-kit.js' : 'js/upload-kit.min.js'
    ];
}
