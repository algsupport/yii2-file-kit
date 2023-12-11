<?php
namespace algsupport\filekit\filesystem;

use League\Flysystem\Filesystem;

/**
 * Interface FilesystemBuilderInterface
 * @package algsupport\filekit\filesystem
 */
interface FilesystemBuilderInterface
{
    /**
     * @return mixed
     */
    public function build(): Filesystem;
}
