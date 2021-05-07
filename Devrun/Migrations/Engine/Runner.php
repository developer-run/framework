<?php


namespace Devrun\Migrations\Engine;

use Nette\Utils\DateTime;
use Nextras\Migrations\Entities\File;
use Nextras\Migrations\Entities\Migration;
use Nextras\Migrations\ExecutionException;
use Nextras\Migrations\IDriver;
use Nextras\Migrations\IPrinter;

class Runner extends \Nextras\Migrations\Engine\Runner
{

    /** @var IDriver */
    private $driver;

    public function __construct(IDriver $driver, IPrinter $printer)
    {
        $this->driver = $driver;
        parent::__construct($driver, $printer);
    }


    /**
     * custom execute
     * původní je s chybou na řádku asi 180
     * throw new ExecutionException(sprintf('Executing migration "%s" has failed.', $file->path), null, $e);
     * Wrong parameters for Nextras\Migrations\ExecutionException([string $message [, long $code [, Throwable $previous = NULL]]])
     *
     * @param File $file
     * @return int
     */
    protected function execute(File $file)
    {
        $this->driver->beginTransaction();

        $migration = new Migration;
        $migration->group = $file->group->name;
        $migration->filename = $file->name;
        $migration->checksum = $file->checksum;
        $migration->executedAt = new DateTime('now');

        $this->driver->insertMigration($migration);

        try {
            $queriesCount = $this->getExtension($file->extension)->execute($file);

        } catch (\Exception $e) {
            $this->driver->rollbackTransaction();
            throw new ExecutionException(sprintf("Executing migration \"%s\" has failed\n\n\"%s\".", $file->path, $e->getMessage()));
        }

        $this->driver->markMigrationAsReady($migration);
        $this->driver->commitTransaction();

        return $queriesCount;
    }


}