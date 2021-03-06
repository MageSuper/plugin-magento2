<?php
namespace Boxalino\Intelligence\Model\ResourceModel;

use \Magento\Framework\App\ResourceConnection;

/**
 * Keeps most of db access for the exporter class
 *
 * Class Exporter
 * @package Boxalino\Intelligence\Model\ResourceModel
 */
class ProcessManager
{

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    protected $adapter;
    
    /**
     * ProcessManager constructor.
     * @param LoggerInterface $logger
     * @param ResourceConnection $resource
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->adapter = $resource->getConnection();
    }
    
    /**
     * Check product IDs from last delta run
     *
     * @param null | array $date
     * @return array
     */
    public function getProductIdsByUpdatedAt($date)
    {
        $select = $this->adapter->select()
            ->from(
                ['c_p_e' => $this->adapter->getTableName('catalog_product_entity')],
                ['entity_id']
            )->where("DATE_FORMAT(c_p_e.updated_at, '%Y-%m-%d %H:%i:%s') >=  DATE_FORMAT(?, '%Y-%m-%d %H:%i:%s')", $date);

        return $this->adapter->fetchCol($select);
    }

    /**
     * Rollback indexer latest updated date in case of error
     *
     * @param $id
     * @param $updated
     * @return int
     */
    public function updateIndexerUpdatedAt($id, $updated)
    {
        $dataBind = [
            "updated" => $updated,
            "indexer_id" => $id
        ];

        return $this->adapter->insertOnDuplicate(
            $this->adapter->getTableName("boxalino_export"),
            $dataBind, ["updated"]
        );
    }

    /**
     * @param $id
     * @return string
     */
    public function getLatestUpdatedAtByIndexerId($id)
    {
        $select = $this->adapter->select()
            ->from($this->adapter->getTableName("boxalino_export"), ["updated"])
            ->where("indexer_id = ?", $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * Getting a list of product IDs affected
     *
     * @param $id
     * @return string
     */
    public function getAffectedEntityIds($id)
    {
        $select = $this->adapter->select()
            ->from($this->adapter->getTableName("boxalino_export"), ["entity_id"])
            ->where("indexer_id = ?", $id);

        return $this->adapter->fetchOne($select);
    }

    /**
     * Updating the list of product IDs affected
     *
     * @param $id
     * @return string
     */
    public function updateAffectedEntityIds($id, $ids)
    {
        $dataBind = [
            "entity_id" => $ids,
            "indexer_id" => $id
        ];

        return $this->adapter->insertOnDuplicate(
            $this->adapter->getTableName("boxalino_export"),
            $dataBind, ["entity_id"]
        );
    }
}