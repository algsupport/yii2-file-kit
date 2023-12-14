<?php
namespace algsupport\filekit\assets;

use yii\web\AssetBundle;

class BlueimpTmplAsset extends AssetBundle
{
    public $sourcePath = __DIR__.'/blueimp/blueimp-tmpl';

    public $js = [
        'js/tmpl.min.js'
    ];
}
