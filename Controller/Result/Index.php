<?php
namespace Boxalino\Intelligence\Controller\Result;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;

/**
 * Class Index
 * @package Boxalino\Intelligence\Controller
 */
class Index extends \Magento\CatalogSearch\Controller\Result\Index
{
    /**
     * @var \Boxalino\Intelligence\Api\P13nAdapterInterface
     */
    protected $p13Helper;

    /**
     * @var \Boxalino\Intelligence\Helper\Data
     */
    protected $bxHelperData;

    /**
     * @var QueryFactory
     */
    protected $_queryFactory;

    /**
     * Catalog Layer Resolver
     *
     * @var Resolver
     */
    protected $layerResolver;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Index constructor.
     * @param \Boxalino\Intelligence\Helper\Data $bxHelperData
     * @param \Boxalino\Intelligence\Api\P13nAdapterInterface $p13nHelper
     * @param Context $context
     * @param Session $catalogSession
     * @param StoreManagerInterface $storeManager
     * @param QueryFactory $queryFactory
     * @param Resolver $layerResolver
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Boxalino\Intelligence\Helper\Data $bxHelperData,
        \Boxalino\Intelligence\Api\P13nAdapterInterface $p13nHelper,
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        Resolver $layerResolver,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context,$catalogSession,$storeManager,$queryFactory,$layerResolver);
        $this->_logger = $logger;
        $this->bxHelperData = $bxHelperData;
        $this->p13Helper = $p13nHelper;
        $this->_queryFactory = $queryFactory;
        $this->layerResolver = $layerResolver;
    }

    /**
     * Display search result
     *
     * @return void
     */
    public function execute()
    {
        if($this->bxHelperData->isSearchEnabled()){
            try{
                $start = microtime(true);
                $this->p13Helper->addNotification('debug', "request start at " . $start);

                $this->p13Helper->setIsSearch(true);
                $query = $this->_queryFactory->get();
                $queryText = $query->getQueryText();
                if(empty($queryText) && $this->bxHelperData->isEmptySearchEnabled())
                {
                    $query->setQueryText($this->bxHelperData->getEmptySearchQueryReplacement());
                }

                $redirect_link = $this->p13Helper->getResponse()->getRedirectLink();
                $this->p13Helper->addNotification('debug',
                    "request end, time: " . (microtime(true) - $start) * 1000 . "ms" .
                    ", memory: " . memory_get_usage(true));

                if($redirect_link != "") {
                    $this->getResponse()->setRedirect($this->p13Helper->getResponse()->getRedirectLink());
                }

                $query = $this->_queryFactory->get();
                if($this->p13Helper->areThereSubPhrases()){
                    $queries = $this->p13Helper->getSubPhrasesQueries();
                    if(count($queries) == 1) {
                        $this->_redirect('*/*/*', array('_current'=> true, '_query' => array(QueryFactory::QUERY_VAR_NAME => $queries[0])));
                    }
                }
                if($this->p13Helper->areResultsCorrected()) {
                    $correctedQuery = $this->p13Helper->getCorrectedQuery();
                    $query->setQueryText($correctedQuery);
                }
            }catch(\Exception $e){
                $this->bxHelperData->setFallback(true);
                $this->_logger->critical($e);
            }
            parent::execute();
        } else {
            parent::execute();
        }
    }

}
