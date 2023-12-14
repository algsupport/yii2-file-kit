<?php
namespace algsupport\filekit\assets;

use yii\web\AssetBundle;

class BlueimpLoadImageAsset extends AssetBundle
{
    public $sourcePath = __DIR__.'/blueimp/blueimp-load-image';

    public $js = [
        'js/load-image.all.min.js'
    ];
}
