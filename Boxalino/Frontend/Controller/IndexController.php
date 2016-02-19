<?php
namespace Boxalino\Frontend\Controller;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;

class IndexController extends \Magento\CatalogSearch\Controller\Result\Index
{

    protected $bxHelperData;

    public function __construct(
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        \Boxalino\Frontend\Helper\Data $bxHelperData,
        Resolver $layerResolver)
    {
        $this->bxHelperData = $bxHelperData;
        parent::__construct($context, $catalogSession, $storeManager, $queryFactory, $layerResolver);

    }

    public function execute()
    {
		if($this->bxHelperData->isSearchEnabled()){
            $configuration = array('Magento\CatalogSearch\Block\SearchResult\ListProduct' =>
                array('type'=>'Boxalino\Frontend\Block\Product\BxListProducts')
            );
            $this->_objectManager->configure($configuration);

            $configuration = array('searchFilterList' =>
                array('type'=>'Boxalino\Frontend\Model\FilterList')
            );
            $this->_objectManager->configure($configuration);

            $configuration = array('searchFilterListTop' =>
                array('type'=>'Boxalino\Frontend\Model\FilterListTop')
            );
            $this->_objectManager->configure($configuration);

            $configuration = array('Magento\Catalog\Model\Layer\State' =>
                array('type'=>'Boxalino\Frontend\Block\State')
            );
            $this->_objectManager->configure($configuration);
        }
        return parent::execute(); // TODO: Change the autogenerated stub
    }

}

?>