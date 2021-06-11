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
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\FileSystem;

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

    /** @var \Nette\Caching\Cache */
    private $cache;


    public function __construct($www_dir, $data_path, $data_dir, $algorithm_file, $algorithm_content, $quality, $default_transform, $noimage_identifier, $friendly_url, Storage $storage)
    {
        $this->cache = new \Nette\Caching\Cache($storage, 'image-storage');

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
     * Create identifier image if need
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
        /**
         * Define image identifier
         */
        $identifier = $args[0];

        // check if original medium file exist
        if (!file_exists($fileImage = implode('/', [$this->data_path, $identifier]))) {

            // check if original file exist
            if (file_exists($path = implode('/', [$this->getWwwDir(), $identifier]))) {
                $checksumIdentifier = $this->cache->load($identifier, function (&$dependencies) use ($identifier, $path) {
                    $checksum = call_user_func_array($this->algorithm_file, [$path]);

                    $namespace = dirname($identifier);
                    $name = basename($identifier);

                    $prefix = substr($checksum, 0, 2);
                    $savePath = implode('/', [$this->data_path, $namespace, $prefix, $name]);

                    if (!file_exists($savePath)) {
                        FileSystem::copy($path, $savePath);
                    }

                    $dependencies[Cache::EXPIRE] = '1 month';
                    $dependencies[Cache::FILES] = [$path, $savePath];

                    return implode('/', [$namespace, $prefix, $name]);
                });

                $args[0] = $checksumIdentifier;
            }
        }

        return parent::fromIdentifier($args);
    }


    /**
     * @return string
     */
    public function getWwwDir()
    {
        return $this->www_dir;
    }


}