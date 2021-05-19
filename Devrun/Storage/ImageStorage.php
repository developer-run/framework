<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    ImageStorage.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Storage;

use Contributte\ImageStorage\Image;
use Contributte\ImageStorage\Exception\ImageResizeException;
use Devrun\Utils\Debugger;

class ImageStorage extends \Contributte\ImageStorage\ImageStorage
{

    /*
     * Absolute data dir path in basePath directory (.../)
     * @var string
     */
    private $www_dir;

    /**
     * Absolute data dir path in public directory (.../public/data by default)
     * @var string
     */
    private $data_path;

    /**
     * Relative data dir in public directory (data by default)
     * @var string
     */
    private $data_dir;

    /**
     * How to compute the checksum of image file
     * sha1_file by default
     * @var string
     */
    private $algorithm_file;

    /**
     * How to compute the checksum of image content
     * sha1 by default
     * @var string
     */
    private $algorithm_content;

    /**
     * Quality of saved thumbnails
     * @var int
     */
    private $quality;

    /**
     * Default transform method
     * 'fit' by default
     * @var string
     */
    private $default_transform;

    /**
     * Noimage image identifier
     * @var string
     */
    private $noimage_identifier;

    /**
     * Create friendly url?
     * @var bool
     */
    private $friendly_url;

    /**
     * @var int
     */
    private $mask = 0775;

    /**
     * @var array
     */
    private $_image_flags = [
        'fit' => 0,
        'fill' => 4,
        'exact' => 8,
        'stretch' => 2,
        'shrink_only' => 1
    ];


    public function __construct($www_dir, $data_path, $data_dir, $algorithm_file, $algorithm_content, $quality, $default_transform, $noimage_identifier, $friendly_url)
    {
        $this->www_dir = $www_dir;
        $this->data_path = $data_path;
        $this->data_dir = $data_dir;
        $this->algorithm_file = $algorithm_file;
        $this->algorithm_content = $algorithm_content;
        $this->quality = $quality;
        $this->default_transform = $default_transform;
        $this->noimage_identifier = $noimage_identifier;
        $this->friendly_url = $friendly_url;

        parent::__construct($data_path, $data_dir, $algorithm_file, $algorithm_content, $quality, $default_transform, $noimage_identifier, $friendly_url);
    }


    /**
     * Delete stored image and all thumbnails/resized images, etc
     *
     * replace original ImageNameScript::fromName
     * @see ImageNameScript::fromName()
     *
     * @param mixed $arg
     */
    public function delete($arg): void
    {
        if (is_object($arg) && $arg instanceof Image) {
            $script = ImageNameScript::fromIdentifier($arg->identifier);
        } else {
            $script = ImageNameScript::fromName($arg);
        }

        $pattern = preg_replace('/__file__/', $script->name, ImageNameScript::PATTERN);
        $dir = implode('/', [$this->data_path, $script->namespace, $script->prefix]);

        if (!file_exists($dir)) {
            return;
        }

        foreach (new \DirectoryIterator($dir) as $file_info) {
            if (preg_match($pattern, $file_info->getFilename())) {
                unlink($file_info->getPathname());
            }
        }

        // delete empty dir
        if (file_exists($dir)) {
            if ($isDirEmpty = !(new \FilesystemIterator($dir))->valid()) {
                @rmdir($dir);
            }
        }
    }


    /**
     * replace parent fromIdentifier
     * parent method use sharpen image, this is not beautiful, remove it
     *
     *
     * @param $args
     *
     * @return Image|array
     * @throws ImageResizeException
     * @throws \Contributte\ImageStorage\Exception\ImageStorageException
     * @throws \Nette\Utils\ImageException
     * @throws \Nette\Utils\UnknownImageFileException
     */
    public function fromIdentifier($args): Image
    {
        if (!is_array($args)) {
            $args = [$args];
        }

        /**
         * Define image identifier
         */
        $identifier = $args[0];

        /**
         * For don`t crop if no image
         */
        $isNoImage = false;

        /**
         * If we need original photo, do not resize anything
         */
        if (sizeof($args) === 1) {
            if (!file_exists($fileImage = implode('/', [$this->data_path, $identifier])) || !$identifier) {
                return $this->getNoImage(TRUE);
            }
            return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifier);
        }

        /**
         * Define new image size (w, h)
         */
        preg_match('/(\d+)?x(\d+)?(crop(\d+)x(\d+)x(\d+)x(\d+))?/', $args[1], $matches);
        $size = [(int) $matches[1], (int) $matches[2]];
        $crop = [];

        if (!$size[0] || !$size[1]) {
            throw new ImageResizeException("Error resizing image. You have to provide both width and height.");
        }

        if (sizeof($matches) === 8) {
            $crop = [(int) $matches[4], (int) $matches[5], (int) $matches[6], (int) $matches[7]];
        }

        /**
         * Define transform method / flag
         */
        $flag = isset($args[2]) ? $args[2] : $this->default_transform;
        $quality = isset($args[3]) ? $args[3] : $this->quality;

        /**
         * Verify that given identifier is not empty
         */
        if (!$identifier) {
            $is_no_image = FALSE;
            list($script, $file) = $this->getNoImage(FALSE);
        } else {
            /**
             * Create ImageNameScript and set particular sizes, flags, etc
             */

            $script = ImageNameScript::fromIdentifier($identifier);

            /**
             * Verify existency of image
             */
            $file = implode('/', [$this->data_path, $script->original]);
            if (!file_exists($file)) {
                $is_no_image = TRUE;
                list($script, $file) = $this->getNoImage(FALSE);
            }
        }

        $script->setSize($size);
        $script->setCrop($crop);
        $script->setFlag($flag);
        $script->setQuality($quality);

        $identifier = $script->getIdentifier();

        if (!file_exists(implode('/', [$this->data_path, $identifier]))) {
            /**
             * $file is now a path to noimage file (if any)
             */

            if (!file_exists($file)) {
                /**
                 * Raise and exception?
                 */
                return new Image(NULL, '#', '#', 'Can not find image');
            }

            $_image = \Nette\Utils\Image::fromFile($file);

            if ($script->hasCrop() && !$isNoImage) {
                call_user_func_array([$_image, 'crop'], $script->crop);
            }

            if (FALSE !== strpos($flag, '+')) {
                $bits = 0;

                foreach (explode('+', $flag) as $f) {
                    $bits = $this->_image_flags[$f] | $bits;
                }

                $flag = $bits;
            } else {
                $flag = $this->_image_flags[$flag];
            }

            /*
             * modify from parent
             */
            $_image
                ->resize($size[0], $size[1], $flag)
                ->save(
                    implode('/', [$this->data_path, $identifier]),
                    $quality
                );
        }

        return new Image($this->friendly_url, $this->data_dir, $this->data_path, $identifier, ['script' => $script]);
    }

    /**
     * @return string
     */
    public function getWwwDir()
    {
        return $this->www_dir;
    }


}