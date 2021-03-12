<?php

namespace Fico7489\Es;

use Illuminate\Console\Command;

class RecreateIndexImportData extends Command
{
    protected $signature = 'es:recreate-index-import-data
        {--className=* : Class name for recreating index}
        {--recreateIndex : recreate index ?}
        {--importData : Import data ?}
        ';
    /** @var EsIndexManager */
    private $esIndexManager;

    public function __construct(EsIndexManager $esIndexManager)
    {
        parent::__construct();

        $this->esIndexManager = $esIndexManager;
    }

    public function handle()
    {
        $className = $this->input->getOption('className');

        $this->esIndexManager->setOutput($this->output);

        if ($this->option('recreateIndex')) {
            $this->esIndexManager->recreateIndexAll($className);
        }

        if ($this->option('importData')) {
            $this->esIndexManager->importDataAll($className);
        }
    }
}
