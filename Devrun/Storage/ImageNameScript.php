<?php

namespace Devrun\Storage;

class ImageNameScript extends \Contributte\ImageStorage\ImageNameScript
{


    public static function fromIdentifier($identifier): \Contributte\ImageStorage\ImageNameScript
    {
        return self::fromName($identifier);
    }


    /**
     * replace original identify mask
     *
     * original:
     * $pattern = preg_replace('/__file__/', '([^\/]*)\/([^\/]*)\/(.*?)', self::PATTERN);
     *
     * new:
     * $pattern = preg_replace('/__file__/', '(.*?)\/([^\/]*)\/([^\/]*)', self::PATTERN);
     *
     * @param $name
     *
     * @return static
     */
    public static function fromName($name): \Contributte\ImageStorage\ImageNameScript
    {
        $pattern = preg_replace('/__file__/', '(.*?)\/([^\/]*)\/([^\/]*)', self::PATTERN);

        preg_match($pattern, $name, $matches);

        $script = new static($matches[0]);

        $script->original  = $matches[1] . '/' . $matches[2] . '/' . $matches[3] . '.' . $matches[15];
        $script->namespace = $matches[1];
        $script->prefix    = $matches[2];
        $script->name      = $matches[3];
        $script->size      = [(int)$matches[5], (int)$matches[6]];
        $script->flag      = $matches[12];
        $script->quality   = $matches[14];
        $script->extension = $matches[15];

        if ($matches[8] && $matches[9] && $matches[10] && $matches[11]) {
            $script->crop = [(int)$matches[8], (int)$matches[9], (int)$matches[10], (int)$matches[11]];
        }

        return $script;
    }

}
