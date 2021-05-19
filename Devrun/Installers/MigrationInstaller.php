<?php


namespace Devrun\Module\Installers;

use Devrun\Module\IInstaller;
use Devrun\Module\IModule;
use Devrun\NotSupportedException;
use Devrun\Utils\FileTrait;
use Nette\DI\Container;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

class MigrationInstaller implements IInstaller
{

    // rename files by mask Y-m-HIS [actual time]
    const TIME_RENAMING = false;

    use FileTrait;

    const MODE_RESET    = 'reset';
    const MODE_CONTINUE = 'continue';

    /** @var string */
    protected $resourcesDir;

    /** @var string */
    protected $configDir;

    /** @var string */
    protected $migrationsDir;

    /** @var string */
    protected $baseDir;

    /** @var array */
    protected $parameters = [];

    /** @var Container */
    protected $context;

    /** @var string */
    private $mode = self::MODE_RESET;



    /**
     * @param \Nette\DI\Container $context
     */
    public function __construct(Container $context)
    {
        $this->resourcesDir = $context->parameters['resourcesDir'];
        $this->parameters   = $context->parameters;

        $this->context       = $context;
        $this->configDir     = $context->parameters['configDir'];
        $this->baseDir       = $context->parameters['baseDir'];
        $this->migrationsDir = $context->parameters['migrationsDir'];
    }


    /**
     * @param IModule $module
     * @todo alpha version, not uninstall
     *
     */
    public function install(IModule $module)
    {
        if (is_dir($dir = "{$module->getPath()}/resources/migrations")) {
            $files = Finder::find('*.sql', '*.php')->from($dir);
            $this->copyOriginalFiles($module, $files);
        }
    }

    /**
     * @param IModule $module
     */
    public function uninstall(IModule $module)
    {
        // TODO: Implement uninstall() method.
    }

    /**
     * @param IModule $module
     */
    public function upgrade(IModule $module, $from, $to)
    {
        if ($isGit = is_dir($module->getGitPath() . DIRECTORY_SEPARATOR . ".git")) {
            chdir($module->getGitPath());

            // array of logs [ '7e3c3df,03580c7,2019-10-07 06:18:19 +0200', '03580c7,,2019-10-01 09:29:13 +0200' ]
            if ($gitLastLog = exec('git log --pretty=format:\'%h,%p,%ci\' --abbrev-commit', $outputs, $return)) {

                // git ok
                if ($return == 0) {

                    // array keys [ 7e3c3df => "7e3c3df,03580c7,2019-10-07 06:18:19 +0200", 03580c7 => "03580c7,,2019-10-01 09:29:13 +0200" ]
                    $logs = [];
                    foreach ($outputs as $output) {
                        $logs[Strings::before($output, ',')] = $output;
                    }

                    // is version in format "v0.8.0-1-g7e3c3df" ?
                    $fromHash = preg_match("%(.*)-\d-g(.*)%", $from)
                        ? Strings::after($from, '-g')
                        : $from;

                    // is version in format "v0.8.0-1-g7e3c3df" ?
                    $toHash = preg_match("%(.*)-\d-g(.*)%", $to)
                        ? Strings::after($to, '-g')
                        : $to;

                    $fromDate = null;
                    $toDate   = null;
                    if (isset($logs[$fromHash])) {
                        $_expLog  = explode(',', $logs[$fromHash]);
                        $prevHash = $_expLog[1];

                        if (isset($logs[$prevHash])) {
                            $_expLog  = explode(',', $logs[$prevHash]);
                            $fromDate = end($_expLog);
                        }
                    }

                    if (isset($logs[$toHash])) {
                        $_expLog = explode(',', $logs[$toHash]);
                        $toDate  = end($_expLog);
                    }

                    if ($fromDate && $toDate) {

                        $fromDate = \Nette\Utils\DateTime::from($fromDate);
                        $toDate   = \Nette\Utils\DateTime::from($toDate);

                        if (is_dir($dir = "{$module->getPath()}/resources/migrations")) {

                            $files = Finder::findFiles('*.sql', '*.php')
                                   ->filter(function (\SplFileInfo $fileInfo) use ($fromDate, $toDate) {

                                       // check migration file format 2019-08-12-102030...
                                       if (preg_match("%^((19|20|21)(\d{2}-\d{2}-\d{2}-)\d{6}).*%", $fileInfo->getBasename())) {
                                           $fileDate = \Nette\Utils\DateTime::createFromFormat("Y-m-d-His", substr($fileInfo->getBasename(), 0, 17));
                                           return $fileDate > $fromDate && $fileDate <= $toDate;
                                       }

                                       return false;
                                   })
                                   ->from($dir);

                            $this->copyOriginalFiles($module, $files);
                        }
                    }
                }
            }

        } else {
            Debugger::log("`{$module->getName()}` module has not git version, specify this in composer or overflow getGitPath in Module.php", ILogger::WARNING);
        }
    }

    /**
     * @param IModule $module
     */
    public function downgrade(IModule $module, $from, $to)
    {
        // TODO: Implement downgrade() method.
        throw new NotSupportedException(__METHOD__ . " not implemented ");
    }



    /**
     * @param IModule $module
     * @param Finder $files
     */
    protected function copyOriginalFiles(IModule $module, Finder $files)
    {
        if ($files->count()) {
            static $index = 1;

            if (is_dir($dir = "{$module->getPath()}/resources/migrations")) {
                $sourcesSort = [];
                foreach ($files as $file => $spl) {
                    $sourcesSort[basename($file)] = $file;
                }

                ksort($sourcesSort);

                /*
                 * copy original migration files to actual time + index files
                 */
                foreach ($sourcesSort as $file) {
                    if ($relativeDir = Strings::after($file, $dir)) {

                        if (self::TIME_RENAMING) {
                            $timeStr     = date("Y-m-d-His");
                            $replacement = $timeStr . "-" . str_pad($index, 3, '0', STR_PAD_LEFT);
                            $newFileName = Strings::replace($relativeDir, "((19|20|21)(\d{2}-\d{2}-\d{2}-)\d{6})", $replacement);
                            $relativeDir = $newFileName;
                        }

                        $newPath = $this->migrationsDir . $relativeDir;

                        FileSystem::copy($file, $newPath);
                        $index++;
                    }
                }
            }
        }
    }


}