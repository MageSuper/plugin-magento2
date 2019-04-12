<?php
namespace Boxalino\Intelligence\Model\Exporter\Process;

use Boxalino\Intelligence\Model\Exporter\ProcessManager;

class Delta extends ProcessManager
{

    /**
     * Indexer ID in configuration
     */
    const INDEXER_ID = 'boxalino_indexer_delta';

    const INDEXER_TYPE = 'delta';

    /**
     * Default server timeout
     */
    const SERVER_TIMEOUT_DEFAULT = 60;

    /**
     * @var array
     */
    protected $ids = [];

    /**
     * stop execution if there are no deltas
     *
     * @return bool
     */
    public function run()
    {
        $ids = $this->getIds();
        if(empty($ids))
        {
            $this->logger->info("bxLog: The delta export is empty at {$this->getLatestRun()}. Closing request.");
            return true;
        }

        parent::run();
    }

    public function getType(): string
    {
        return self::INDEXER_TYPE;
    }

    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    /**
     * Get timeout for exporter
     * @return bool|int
     */
    public function getTimeout($account)
    {
        return self::SERVER_TIMEOUT_DEFAULT;
    }

    /**
     * If the exporter scheduler is enabled, the delta export time has to be validated
     * 1. the delta can only be triggered between configured start-end hours
     * 2. 2 subsequent deltas can only be run with the time difference configured
     * 3. the delta after a full export can only be run after the configured time
     *
     * @param $startExportDate
     * @return bool
     */
    public function exportDeniedOnAccount($account)
    {
        if(!$this->config->isExportSchedulerEnabled($account))
        {
            return false;
        }

        $startHour = $this->config->getExportSchedulerDeltaStart($account);
        $endHour = $this->config->getExportSchedulerDeltaEnd($account);
        $runDateHour = new \DateTime('NOW');
        $runDateHour= $runDateHour->format("H");
        if($runDateHour === min(max($runDateHour, $startHour), $endHour))
        {
            $latestDeltaRunDate = $this->getLatestDeltaUpdate();
            $deltaTimeRange = $this->config->getExportSchedulerDeltaMinInterval($account);
            if($latestDeltaRunDate === min($latestDeltaRunDate, date("Y-m-d H:i:s", strtotime("-$deltaTimeRange min"))))
            {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * Check latest run on delta
     *
     * @return false|string|null
     */
    public function getLatestRun()
    {
        if(is_null($this->latestRun))
        {
            $this->latestRun = $this->getLatestUpdatedAt($this->getIndexerId());
        }

        if(empty($this->latestRun) || strtotime($this->latestRun) < 0)
        {
            $this->latestRun = date("Y-m-d H:i:s", strtotime("-1 hour"));
        }

        return $this->latestRun;
    }

    /**
     * @return array
     */
    public function getIds()
    {
        $lastUpdateDate = $this->getLatestRun();
        $ids = $this->processResource->getProductIdsByUpdatedAt($lastUpdateDate);
        if(empty($ids))
        {
            $categoryUpdates = $this->processResource->hasDeltaReadyCategories($lastUpdateDate);
            if($categoryUpdates)
            {
                return $this->processResource->getLatestUpdatedProductIds();
            }
        }
        
        return $ids;
    }

    /**
     * @return bool
     */
    public function getExportProducts()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function getExportCustomers()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getExportTransactions()
    {
        return false;
    }
}