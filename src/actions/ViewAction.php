<?php

namespace algsupport\filekit\actions;

use Yii;
use yii\web\HttpException;

/**
 * Class ViewAction
 * @package algsupport\filekit\actions
 * @author Eugene Terentev <eugene@terentev.net>
 */
class ViewAction extends BaseAction
{
    /**
     * @var string path request param
     */
    public $pathParam = 'path';
    /**
     * @var boolean whether the browser should open the file within the browser window. Defaults to false,
     * meaning a download dialog will pop up.
     */
    public $inline = false;

	/**
	 * @throws \yii\web\HttpException
	 * @throws \yii\base\InvalidConfigException
	 * @throws \yii\web\RangeNotSatisfiableHttpException
	 */
	public function run()
    {
        $path = Yii::$app->request->get($this->pathParam);
        $filesystem = $this->getFileStorage()->getFilesystem();
        if ($filesystem->fileExists($path) === false) {
            throw new HttpException(404);
        }
        return Yii::$app->response->sendStreamAsFile(
            $filesystem->readStream($path),
            pathinfo($path, PATHINFO_BASENAME),
            [
                'mimeType' => $filesystem->mimeType($path),
                'inline' => $this->inline
            ]
        );
    }
}
