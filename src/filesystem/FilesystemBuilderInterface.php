<?php
namespace trntv\filekit\filesystem;

use League\Flysystem\Filesystem;

/**
 * Interface FilesystemBuilderInterface
 * @package trntv\filekit\filesystem
 */
interface FilesystemBuilderInterface
{
    /**
     * @return mixed
     */
    public function build(): Filesystem;
}
