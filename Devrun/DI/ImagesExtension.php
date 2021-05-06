<?php

namespace Devrun\DI;

use Devrun\Application\UI\Images\Macros\Latte;
use Nette;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class ImagesExtension extends Nette\DI\CompilerExtension
{

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'publicDir' => Expect::string("%wwwDir%"),
            'data_path' => Expect::string('%wwwDir%/media'),
            'data_dir' => Expect::string('media'),
            'algorithm_file' => Expect::string('sha1_file'),
            'algorithm_content' => Expect::string('sha1'),
            'quality' => Expect::int(85),
            'default_transform' => Expect::string('fit'),
            'noimage_identifier' => Expect::string('noimage/8f/no-image.png'),
            'friendly_url' => Expect::bool(false),
        ]);
    }


    public function loadConfiguration()
    {
        $config  = $this->getConfig();

        /** @var Nette\DI\ContainerBuilder $builder */
        $builder = $this->getContainerBuilder();

        /** @var Nette\DI\Definitions\FactoryDefinition $engine */
        $engine = $builder->getDefinition('nette.latteFactory');

        $install = Latte::class . '::install';

        if (method_exists('Latte\Engine', 'getCompiler')) {
            $engine->getResultDefinition()->addSetup('Devrun\Application\UI\Images\Macros\Latte::install(?->getCompiler())', array('@self'));
        } else {
            $engine->getResultDefinition()->addSetup($install . '(?->compiler)', array('@self'));
        }

        $builder->addDefinition($this->prefix('storage'))
                ->setFactory('Devrun\Storage\ImageStorage')
                ->setArguments([
                    $config->publicDir,
                    $config->data_path,
                    $config->data_dir,
                    $config->algorithm_file,
                    $config->algorithm_content,
                    $config->quality,
                    $config->default_transform,
                    $config->noimage_identifier,
                    $config->friendly_url
                ]);
    }


}
