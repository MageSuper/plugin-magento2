<?php
namespace Boxalino\Intelligence\Block;

/**
 * Class Slider
 * @package Boxalino\Intelligence\Block
 */
class Slider extends \Magento\Framework\View\Element\Template{

    /**
     * @var \Boxalino\Intelligence\Api\P13nAdapterInterface
     */
    protected $p13nHelper;

    /**
     * @var \Boxalino\Intelligence\Helper\Data
     */
    protected $bxHelperData;

    /**
     * Slider constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Boxalino\Intelligence\Api\P13nAdapterInterface $p13nHelper
     * @param \Boxalino\Intelligence\Helper\Data $bxHelperData
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Boxalino\Intelligence\Api\P13nAdapterInterface $p13nHelper,
        \Boxalino\Intelligence\Helper\Data $bxHelperData,
        array $data = []
    )
    {
        $this->p13nHelper = $p13nHelper;
        $this->bxHelperData = $bxHelperData;
        parent::__construct($context, $data);
    }

    /**
     * @param $price
     * @return array
     */
    private function explodePrice($price){
        return explode("-", $price);
    }

    /**
     * @return array|null
     */
    public function getSliderValues(){
        $facets = $this->p13nHelper->getFacets();
        if (empty($facets) || empty($facets->getFacetValues($facets->getPriceFieldName()))) {
            return null;
        }
        $priceRange = $this->explodePrice($facets->getPriceRanges()[0]);
        $selectedPrice = $this->getRequest()->getParam('bx_discountedPrice') !== null ?
            $this->explodePrice($this->getRequest()->getParam('bx_discountedPrice')) : $priceRange;
        if (!isset($priceRange[1])) {
            $priceRange[1] = 0;
        }
        if($priceRange[0] == $priceRange[1]){
            $priceRange[1]++;
        }
        return array_merge($selectedPrice, $priceRange);
    }
}
