<?php
/**
 * This file is part of devrun.
 * Copyright (c) 2017
 *
 * @file    Latte.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Application\UI\Images\Macros;

use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;

/**
 * Class Latte
 * @author Jan Brabec <brabijan@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 *
 * @package Devrun\Application\UI\Images\Macros
 */
class Latte extends MacroSet
{

    /**
     * @var bool
     */
    private $isUsed = FALSE;

    /**
     * @param \Latte\Compiler $compiler
     *
     * @return MacroSet
     */
    public static function install(Compiler $compiler)
    {
        $set = new static($compiler);
        /**
         * {img [namespace/]$name[, $size[, $flags]]}
         */
        $set->addMacro('img', [$set, 'tagImg'], NULL, [$set, 'attrImg']);
        $set->addMacro('imgAbs', [$set, 'tagImgAbs'], NULL, [$set, 'attrImgAbs']);
        return $set;
    }




    public function tagImg(MacroNode $node, PhpWriter $writer)
    {
        return $writer->write('$_img = $_imgStorage->fromIdentifier(%node.array); echo "<img src=\"" . $proxyUrl . $basePath . "/" . $_img->createLink() . "\">";');
    }


    public function attrImg(MacroNode $node, PhpWriter $writer)
    {
        return $writer->write('$_img = $_imgStorage->fromIdentifier(%node.array); echo \' src="\' . $proxyUrl . $basePath . "/" . $_img->createLink() . \'"\'');
    }


    public function tagImgAbs(MacroNode $node, PhpWriter $writer)
    {
        return $writer->write('$_img = $_imgStorage->fromIdentifier(%node.array); echo "<img src=\"" . $proxyUrl . $baseUrl . "/" . $_img->createLink() . "\">";');
    }


    public function attrImgAbs(MacroNode $node, PhpWriter $writer)
    {
        return $writer->write('$_img = $_imgStorage->fromIdentifier(%node.array); echo \' src="\' . $proxyUrl . $baseUrl . "/" . $_img->createLink() . \'"\'');
    }




    public function initialize()
    {
        $this->isUsed = FALSE;
    }


    public function finalize()
    {
        if (!$this->isUsed) {
            return array();
        }


        return array(
            get_called_class() . '::repairedValidateTemplateParams($template);',
            NULL
        );

    }



    public static function repairedValidateTemplateParams($template)
    {
        $params = $template->getParameters();

        if (!isset($params['_imagePipe']) || !$params['_imagePipe'] instanceof ImagePipe) {
            $where = isset($params['control']) ?
                " of component " . get_class($params['control']) . '(' . $params['control']->getName() . ')'
                : NULL;

            throw new \Nette\InvalidStateException(
                'Please provide an instanceof Img\\ImagePipe ' .
                'as a parameter $_imagePipe to template' . $where
            );
        }

    }



}