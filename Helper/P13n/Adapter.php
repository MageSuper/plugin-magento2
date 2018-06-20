<?php
namespace Boxalino\Intelligence\Helper\P13n;
use com\boxalino\bxclient\v1\BxClient;
use com\boxalino\bxclient\v1\BxSearchRequest;
use com\boxalino\bxclient\v1\BxFilter;

/**
 * Class Adapter
 * @package Boxalino\Intelligence\Helper\P13n
 */
class Adapter
{
    /**
     * @var null
     */
    private static $bxClient = null;

    /**
     * @var array
     */
    private static $choiceContexts = array();

    /**
     * @var string
     */
    protected $scopeStore = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $catalogCategory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Framework\App\Response\Http
     */
    protected $response;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Magento\Search\Model\QueryFactory
     */
    protected $queryFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Boxalino\Intelligence\Helper\Data
     */
    protected $bxHelperData;

    /**
     * @var
     */
    protected $currentSearchChoice;

    /**
     * @var bool
     */
    protected $bxDebug = false;

    /**
     * @var bool
     */
    protected $navigation = false;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $_modelConfig;

    /**
     * @var String
     */
    protected $landingPageChoice = null;

    /**
     * @var string
     */
    protected $prefixContextParameter = '';

    /**
     * Adapter constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Category $catalogCategory
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Search\Model\QueryFactory $queryFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer\Resolver $layerResolver
     * @param \Boxalino\Intelligence\Helper\Data $bxHelperData
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Category $catalogCategory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Registry $registry,
        \Magento\Search\Model\QueryFactory $queryFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Magento\Framework\App\Response\Http $response,
        \Boxalino\Intelligence\Helper\Data $bxHelperData,
        \Magento\Eav\Model\Config $config

    ){
        $this->_modelConfig = $config;
        $this->response = $response;
        $this->bxHelperData = $bxHelperData;
        $this->scopeConfig = $scopeConfig;
        $this->catalogCategory = $catalogCategory;
        $this->request = $request;
        $this->registry = $registry;
        $this->queryFactory = $queryFactory;
        $this->storeManager = $storeManager;
        if ($this->bxHelperData->isPluginEnabled()) {
            $libPath = __DIR__ . '/../../Lib';
            require_once($libPath . '/BxClient.php');
            \com\boxalino\bxclient\v1\BxClient::LOAD_CLASSES($libPath);
            $this->initializeBXClient();
        }
    }

    /**
     * Initializes the \com\boxalino\bxclient\v1\BxClient
     */
    protected function initializeBXClient()
    {
        if (self::$bxClient == null) {
            $account = $this->scopeConfig->getValue('bxGeneral/general/account_name', $this->scopeStore);
            $password = $this->scopeConfig->getValue('bxGeneral/general/password', $this->scopeStore);
            $isDev = $this->scopeConfig->getValue('bxGeneral/general/dev', $this->scopeStore);
            $host = $this->scopeConfig->getValue('bxGeneral/advanced/host', $this->scopeStore);
            $p13n_username = $this->scopeConfig->getValue('bxGeneral/advanced/p13n_username', $this->scopeStore);
            $p13n_password = $this->scopeConfig->getValue('bxGeneral/advanced/p13n_password', $this->scopeStore);
            $apiKey = $this->scopeConfig->getValue('bxGeneral/general/apiKey', $this->scopeStore);
            $apiSecret = $this->scopeConfig->getValue('bxGeneral/general/apiSecret', $this->scopeStore);
            $domain = $this->scopeConfig->getValue('bxGeneral/general/domain', $this->scopeStore);
            self::$bxClient = new \com\boxalino\bxclient\v1\BxClient($account, $password, $domain, $isDev, $host, null, null, null, $p13n_username, $p13n_password, $this->request->getParams(), $apiKey, $apiSecret);
            self::$bxClient->setTimeout($this->scopeConfig->getValue('bxGeneral/advanced/thrift_timeout', $this->scopeStore));
            $curl_timeout = $this->scopeConfig->getValue('bxGeneral/advanced/curl_connection_timeout', $this->scopeStore);
            if($curl_timeout != '') {
                self::$bxClient->setCurlTimeout($curl_timeout);
            }

            if($this->request->getControllerName() == 'page') {
                self::$bxClient->addToRequestMap('bx_cms_id', \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Cms\Model\Page')->getIdentifier());
            }
        }
    }

    /**
     * @param string $queryText
     * @return array
     */
    public function getSystemFilters($queryText = "", $type='product')
    {
        $filters = array();
        if($type == 'product') {
            if ($queryText == "") {
                $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->bxHelperData->getLanguage(), array(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH), true);
            } else {
                $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_visibility_' . $this->bxHelperData->getLanguage(), array(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE, \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG), true);
            }
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('products_status', array(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED));
        }
        if($type == 'blog'){
            $filters[] = new \com\boxalino\bxclient\v1\BxFilter('is_blog', array(1));
        }
        return $filters;
    }

    /**
     * @return mixed|string
     */
    public function getAutocompleteChoice()
    {
        $choice = $this->scopeConfig->getValue('bxSearch/advanced/autocomplete_choice_id', $this->scopeStore);
        if ($choice == null) {
            $choice = "autocomplete";
        }
        return $choice;
    }

    public function setLandingPageChoiceId($choice = ''){

      if (!empty($choice)) {
        return $this->landingPageChoice = $choice;
      }

      return $choice;

    }

    /**
     * @param $queryText
     * @return mixed|string
     */
    public function getSearchChoice($queryText, $isBlog = false)
    {
        if($isBlog) {
            $choice = $this->scopeConfig->getValue('bxSearch/advanced/blog_choice_id', $this->scopeStore);
            if ($choice == null) {
                $choice = "read_search";
            }
            return $choice;
        }
        $landingPageChoiceId = $this->landingPageChoice;

        if (!empty($landingPageChoiceId)) {
          $this->currentSearchChoice = $landingPageChoiceId;
          return $landingPageChoiceId;
        }

        if ($queryText == null) {
            $choice = $this->scopeConfig->getValue('bxSearch/advanced/navigation_choice_id', $this->scopeStore);
            if ($choice == null) {
                $choice = "navigation";
            }
            $this->currentSearchChoice = $choice;
            $this->navigation = true;
            return $choice;
        }

        $choice = $this->scopeConfig->getValue('bxSearch/advanced/search_choice_id', $this->scopeStore);
        if ($choice == null) {
            $choice = "search";
        }
        $this->currentSearchChoice = $choice;
        return $choice;
    }

    /**
     * @return mixed|string
     */
    public function getEntityIdFieldName()
    {
        $entityIdFieldName = $this->scopeConfig->getValue('bxGeneral/advanced/entity_id', $this->scopeStore);
        if (!isset($entityIdFieldName) || $entityIdFieldName === '') {
            $entityIdFieldName = 'products_group_id';
        }
        return $entityIdFieldName;
    }

    /**
     * @param $queryText
     * @param \Boxalino\Intelligence\Helper\Autocomplete $autocomplete
     * @return array
     */
    public function autocomplete($queryText, \Boxalino\Intelligence\Helper\Autocomplete $autocomplete)
    {
        $data = array();
        $hash = null;
        $autocomplete_limit = $this->scopeConfig->getValue('bxSearch/autocomplete/limit', $this->scopeStore);
        $products_limit = $this->scopeConfig->getValue('bxSearch/autocomplete/products_limit', $this->scopeStore);
        $searches = $this->bxHelperData->isBlogEnabled() ? array('product', 'blog') : array('product');
        if ($queryText) {
            $bxRequests = array();
            foreach ($searches as $search) {
                $isBlog = $search == 'blog';
                $bxRequest = new \com\boxalino\bxclient\v1\BxAutocompleteRequest($this->bxHelperData->getLanguage(),
                    $queryText, $autocomplete_limit, $products_limit, $this->getAutocompleteChoice(),
                    $this->getSearchChoice($queryText, $isBlog)
                );
                $searchRequest = $bxRequest->getBxSearchRequest();
                $returnFields = $isBlog ? $this->bxHelperData->getBlogReturnFields() : array('products_group_id');
                $searchRequest->setReturnFields($returnFields);
                $id = $isBlog ? 'id' : 'products_group_id';
                $searchRequest->setGroupBy($id);
                if(!$isBlog) {
                  $searchRequest->setFilters($this->getSystemFilters($queryText, $search));
                }
                $bxRequests[] = $bxRequest;
            }
            self::$bxClient->setAutocompleteRequests($bxRequests);
            self::$bxClient->autocomplete();
            $bxAutocompleteResponses = self::$bxClient->getAutocompleteResponses();

            foreach ($searches as $index => $search) {
                $bxAutocompleteResponse = $bxAutocompleteResponses[$index];
                if($search == 'product'){
                    $first = true;
                    $global = [];

                    $searchChoiceIds = $bxAutocompleteResponse->getBxSearchResponse()->getHitIds($this->currentSearchChoice, true, 0, 10, $this->getEntityIdFieldName());
                    $searchChoiceProducts = $autocomplete->getListValues($searchChoiceIds);

                    foreach ($searchChoiceProducts as $product) {
                        $row = array();
                        $row['type'] = 'global_products';
                        $row['row_class'] = 'suggestion-item global_product_suggestions';
                        $row['product'] = $product;
                        $row['first'] = $first;
                        $first = false;
                        $global[] = $row;
                    }

                    $suggestions = [];
                    $suggestionProducts = [];

                    foreach ($bxAutocompleteResponse->getTextualSuggestions() as $i => $suggestion) {

                        $totalHitcount = $bxAutocompleteResponse->getTextualSuggestionTotalHitCount($suggestion);

                        if ($totalHitcount <= 0) {
                            continue;
                        }

                        $_data = array('title' => $suggestion, 'num_results' => $totalHitcount, 'type' => 'suggestion',
                            'id' => $i, 'row_class' => 'acsuggestions');

                        $suggestionProductIds = $bxAutocompleteResponse->getBxSearchResponse($suggestion)->getHitIds($this->currentSearchChoice, true, 0, 10, $this->getEntityIdFieldName());
                        $suggestionProductValues = $autocomplete->getListValues($suggestionProductIds);
                        foreach ($suggestionProductValues as $product) {
                            $suggestionProducts[] = array("type" => "sub_products", "product" => $product,
                                'row_class' => 'suggestion-item sub_product_suggestions sub_id_' . $i);
                        }

                        if ($_data['title'] == $queryText) {
                            array_unshift($suggestions, $_data);
                        } else {
                            $suggestions[] = $_data;
                        }
                    }
                    $data = array_merge($suggestions, $global);
                    $data = array_merge($data, $suggestionProducts);
                } else {
                    $searchChoiceIds = $bxAutocompleteResponse->getBxSearchResponse()->getHitIds($this->getSearchChoice($queryText, true), true, 0, 10, $this->getEntityIdFieldName());
                    $first = true;
                    foreach ($searchChoiceIds as $id)  {
                        $blog = array();
                        foreach ($this->bxHelperData->getBlogReturnFields() as $field) {
                            $value = $bxAutocompleteResponse->getBxSearchResponse()->getHitVariable($this->getSearchChoice($queryText, true), $id, $field, 0);
                            $blog[$field] = is_array($value) ? reset($value) : $value;
                            if($field == 'title'){
                              $parts = explode('&#', $blog[$field]);
                              // fix for encoding problem
                              if (sizeof($parts)>1) {
                                $blog[$field] = mb_convert_encoding($blog[$field], "UTF-8", "HTML-ENTITIES");
                              }
                            }
                        }
                        $data[] = array('type' => 'blog','product' => $blog, 'first' => $first);
                        if($first) $first = false;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param $queryText
     * @param int $pageOffset
     * @param $hitCount
     * @param \com\boxalino\bxclient\v1\BxSortFields|null $bxSortFields
     * @param null $categoryId
     * @param bool $addFinder
     */
     public function search($queryText, $pageOffset = 0, $hitCount,  \com\boxalino\bxclient\v1\BxSortFields $bxSortFields = null, $categoryId = null, $addFinder = false)
     {
         $returnFields = array($this->getEntityIdFieldName(), 'categories', 'discountedPrice', 'title', 'score');
         $additionalFields = explode(',', $this->scopeConfig->getValue('bxGeneral/advanced/additional_fields', $this->scopeStore));
         $returnFields = array_merge($returnFields, $additionalFields);

         self::$bxClient->forwardRequestMapAsContextParameters();
         if($addFinder) {
             $bxRequest = new \com\boxalino\bxclient\v1\BxParametrizedRequest($this->bxHelperData->getLanguage(), $this->getFinderChoice());
             $this->prefixContextParameter = $bxRequest->getRequestWeightedParametersPrefix();
             $this->setPrefixContextParameter($this->prefixContextParameter);
             $bxRequest->setHitsGroupsAsHits(true);
             $bxRequest->addRequestParameterExclusionPatterns('bxi_data_owner');
         } else {
             if(!is_null($this->landingPageChoice)) {
                 $bxRequest = new \com\boxalino\bxclient\v1\BxParametrizedRequest($this->bxHelperData->getLanguage(), $this->landingPageChoice, $hitCount);
             }else {
                 $bxRequest = new \com\boxalino\bxclient\v1\BxSearchRequest($this->bxHelperData->getLanguage(), $queryText, $hitCount, $this->getSearchChoice($queryText));
             }
         }
         $bxRequest->setGroupBy('products_group_id');
         $bxRequest->setReturnFields($returnFields);
         $bxRequest->setOffset($pageOffset);
         $bxRequest->setSortFields($bxSortFields);
         $bxRequest->setFacets($this->prepareFacets());
         $bxRequest->setFilters($this->getSystemFilters($queryText));
         $bxRequest->setGroupFacets(true);

         if ($categoryId != null && !$addFinder) {
             $filterField = "category_id";
             $filterValues = array($categoryId);
             $filterNegative = false;
             $bxRequest->addFilter(new BxFilter($filterField, $filterValues, $filterNegative));
         }

         self::$bxClient->addRequest($bxRequest);
         if($this->bxHelperData->isBlogEnabled() && (is_null($categoryId) || !$this->navigation)) {
             $this->addBlogResult($queryText, $hitCount);
         }
     }

     private function addBlogResult($queryText, $hitCount) {
         $bxRequest = new \com\boxalino\bxclient\v1\BxSearchRequest($this->bxHelperData->getLanguage(), $queryText, $hitCount, $this->getSearchChoice($queryText, true));
         $requestParams =  $this->request->getParams();
         $pageOffset = isset($requestParams['bx_blog_page']) ? ($requestParams['bx_blog_page'] - 1) * ($hitCount) : 0;
         $bxRequest->setOffset($pageOffset);
         $bxRequest->setGroupBy('id');
         $returnFields = $this->bxHelperData->getBlogReturnFields();
         $bxRequest->setReturnFields($returnFields);
         self::$bxClient->addRequest($bxRequest);
     }

     public function getLandingpageContextParameters($extraParams = null){

       foreach ($extraParams as $key => $value) {

         self::$bxClient->addRequestContextParameter($key, $value);

       }

     }

    /**
     * @return string
     */
    public function getFinderChoice() {

        $choice_id = $this->scopeConfig->getValue('bxSearch/advanced/finder_choice_id', $this->scopeStore);
        if(is_null($choice_id) || $choice_id == '') {
            $choice_id = 'productfinder';
        }
        $this->currentSearchChoice = $choice_id;
        return $choice_id;
    }

    /**
     * @return string
     */
    public function getOverlayChoice() {

        $choice_id = $this->scopeConfig->getValue('bxSearch/advanced/overlay_choice_id', $this->scopeStore);
        if(is_null($choice_id) || $choice_id == '') {
            $choice_id = 'extend';
        }
        $this->currentSearchChoice = $choice_id;
        return $choice_id;
    }

    /**
     * @param $prefix
     */
    protected function setPrefixContextParameter($prefix){
        $requestParams = $this->request->getParams();
        foreach ($requestParams as $key => $value) {
            if(strpos($key, $prefix) == 0) {
                self::$bxClient->addRequestContextParameter($key, $value);
            }
        }
    }

    /**
     *
     */
    public function simpleSearch($addFinder = false, $isOverlay = false)
    {
        if($this->isNarrative) {
            return;
        }
        $isFinder = $this->bxHelperData->getIsFinder();
        $query = $this->queryFactory->get();
        $queryText = $query->getQueryText();

        if ($isOverlay == true) {
            $this->currentSearchChoice = $this->getOverlayChoice();
            return;
        }

        if (self::$bxClient->getChoiceIdRecommendationRequest($this->getSearchChoice($queryText)) != null && !$addFinder && !$isFinder) {
            $this->currentSearchChoice = $this->getSearchChoice($queryText);
            return;
        }
        if (self::$bxClient->getChoiceIdRecommendationRequest($this->getFinderChoice()) != null && ($addFinder || $isFinder)) {
            $this->currentSearchChoice = $this->getFinderChoice();
            return;
        }
        $requestParams = $this->request->getParams();
        $field = '';
        $order = isset($requestParams['product_list_order']) ? $requestParams['product_list_order'] : $this->getMagentoStoreConfigListOrder();

        if (($order == 'title') || ($order == 'name')) {
            $field = 'products_bx_parent_title';
        } elseif ($order == 'price') {
            $field = 'products_bx_grouped_price';
        }

        $dir = isset($requestParams['product_list_dir']) ? true : false;
        $categoryId = $this->registry->registry('current_category') != null ? $this->registry->registry('current_category')->getId() : null;
        $hitCount = isset($requestParams['product_list_limit']) ? $requestParams['product_list_limit'] : $this->getMagentoStoreConfigPageSize();
        $pageOffset = isset($requestParams['p']) ? ($requestParams['p'] - 1) * ($hitCount) : 0;
        $this->search($queryText, $pageOffset, $hitCount, new \com\boxalino\bxclient\v1\BxSortFields($field, $dir), $categoryId, $addFinder);
    }

    protected function addNarrativeRequest($choice_id = 'narrative', $choices = null) {
        $this->currentSearchChoice = $choice_id;
        $this->isNarrative = true;
        $requestParams = $this->request->getParams();
        $field = '';
        $order = isset($requestParams['product_list_order']) ? $requestParams['product_list_order'] : $this->getMagentoStoreConfigListOrder();
        if (($order == 'title') || ($order == 'name')) {
            $field = 'products_bx_parent_title';
        } elseif ($order == 'price') {
            $field = 'products_bx_grouped_price';
        }
        $dir = isset($requestParams['product_list_dir']) ? true : false;
        $hitCount = isset($requestParams['product_list_limit']) ? $requestParams['product_list_limit'] : $this->getMagentoStoreConfigPageSize();
        $pageOffset = isset($requestParams['p']) ? ($requestParams['p'] - 1) * ($hitCount) : 0;

        $language = $this->getLanguage();
        $bxRequest = new \com\boxalino\bxclient\v1\BxRequest($language, $choice_id, $hitCount);
        $bxRequest->setOffset($pageOffset);
        $bxRequest->setSortFields(new \com\boxalino\bxclient\v1\BxSortFields($field, $dir));
        $facets = $this->prepareFacets();
        $bxRequest->setFacets($facets);
        $bxRequest->setGroupFacets(true);
        self::$bxClient->addRequest($bxRequest);
        $requestParams = $this->request->getParams();
        foreach ($requestParams as $key => $value) {
            self::$bxClient->addRequestContextParameter($key, $value);
            if($key == 'choice_id') {
                $choice_ids = explode(',', $value);
                if(is_array($choice_ids)) {
                    foreach ($choice_ids as $choice) {
                        $bxRequest = new \com\boxalino\bxclient\v1\BxRequest($language, $choice, $hitCount);
                        if(strpos($choice, 'banner') !== FALSE) {
                            self::$bxClient->addRequestContextParameter('banner_context', [1]);
                            $bxRequest->setReturnFields(array('title', 'products_bxi_bxi_jssor_slide', 'products_bxi_bxi_jssor_transition', 'products_bxi_bxi_name', 'products_bxi_bxi_jssor_control', 'products_bxi_bxi_jssor_break'));
                        }
                        self::$bxClient->addRequest($bxRequest);
                    }
                }
            }
        }
        if(!is_null($choices)) {
            $choice_ids = explode(',', $choices);
            if(is_array($choice_ids)) {
                foreach ($choice_ids as $choice) {
                    $bxRequest = new \com\boxalino\bxclient\v1\BxRequest($language, $choice, $hitCount);
                    if(strpos($choice, 'banner') !== FALSE) {
                        self::$bxClient->addRequestContextParameter('banner_context', [1]);
                        $bxRequest->setReturnFields(array('title', 'products_bxi_bxi_jssor_slide', 'products_bxi_bxi_jssor_transition', 'products_bxi_bxi_name', 'products_bxi_bxi_jssor_control', 'products_bxi_bxi_jssor_break'));
                    }
                    self::$bxClient->addRequest($bxRequest);
                }
            }
        }
    }

    protected $isNarrative = false;
    public function getNarratives($choice_id = 'narrative', $choices = null) {
        if(is_null(self::$bxClient->getChoiceIdRecommendationRequest($choice_id))) {
            $this->addNarrativeRequest($choice_id, $choices);
        }
        $narrative = $this->getResponse()->getNarratives($choice_id);
        return $narrative;
    }

    public function getNarrativeDependencies($choice_id = 'narrative', $choices = null) {
        if(is_null(self::$bxClient->getChoiceIdRecommendationRequest($choice_id))) {
            $this->addNarrativeRequest($choice_id, $choices);
        }
        $dependencies = $this->getResponse()->getNarrativeDependencies($choice_id);
        return $dependencies;
    }

    /**
     * @return mixed
     */
    public function getMagentoStoreConfigPageSize()
    {

        $storeConfig = $this->getMagentoStoreConfig();
        $storeDisplayMode = $storeConfig['list_mode'];

        //we may get grid-list, list-grid, grid or list
        $storeMainMode = explode('-', $storeDisplayMode);
        $storeMainMode = $storeMainMode[0];
        $hitCount = $storeConfig[$storeMainMode . '_per_page'];
        return $hitCount;
    }

    /**
     * @return mixed
     */
    protected function getMagentoStoreConfigListOrder()
    {

        $storeConfig = $this->getMagentoStoreConfig();
        return $storeConfig['default_sort_by'];
    }

    /**
     * @return mixed
     */
    private function getMagentoStoreConfig()
    {

        return $this->scopeConfig->getValue('catalog/frontend');
    }

    /**
     * @return string
     */
    public function getPrefixContextParameter() {
        return $this->prefixContextParameter;
    }

    /**
     * @return string
     */
    public function getUrlParameterPrefix()
    {

        return 'bx_';
    }

    public function getLanguage() {
        return $this->bxHelperData->getLanguage();
    }

    /**
     * @return \com\boxalino\bxclient\v1\BxFacets
     */
    private function prepareFacets()
    {

        $bxFacets = new \com\boxalino\bxclient\v1\BxFacets();
        $selectedValues = array();
        $bxSelectedValues = array();
        $systemParamValues = array();
        $requestParams = $this->request->getParams();
        $context = $this->navigation ? 'navigation' : 'search';
        $attributeCollection = $this->bxHelperData->getFilterProductAttributes($context);
        $facetOptions = $this->bxHelperData->getFacetOptions();
        $separator = $this->bxHelperData->getSeparator();
        foreach ($requestParams as $key => $values) {
            $additionalChecks = false;
            if (strpos($key, $this->getUrlParameterPrefix()) === 0 && $key != 'bx_category_id') {
                $fieldName = substr($key, 3);
                $selectedValues[$fieldName] = $values;
                if (isset($attributeCollection['products_' . $key]) || $key == 'bx_discountedPrice') {
                    $bxSelectedValues[$fieldName] = explode($separator, $values);

                } else {
                    $key = substr($fieldName, strlen('products_'), strlen($fieldName));
                    $additionalChecks = true;
                }
            }
            if (isset($attributeCollection['products_' . $key])) {
                $paramValues = !is_array($values) ? array($values) : $values;
                $attributeModel = $this->_modelConfig->getAttribute('catalog_product', $key)->getSource();

                foreach ($paramValues as $paramValue){
                    $value = $attributeModel->getOptionText($paramValue);
                    if($additionalChecks && !$value) {
                        $systemParamValues[$key]['additional'] = $additionalChecks;
                        $paramValue = explode($separator, $paramValue);
                        $optionValues = $attributeModel->getAllOptions(false);
                        foreach ($optionValues as $optionValue) {
                            if(in_array($optionValue['label'], $paramValue)){
                                $selectedValues['products_' . $key][] = $optionValue['label'];
                                $this->bxHelperData->setRemoveParams('bx_products_' . $key);
                                $systemParamValues[$key]['values'][] = $optionValue['value'];
                            }
                        }
                    }
                    if($value) {
                        $optionParamsValues = explode($separator, $paramValue);
                        foreach ($optionParamsValues as $optionParamsValue) {
                            $systemParamValues[$key]['values'][] = $optionParamsValue;
                        }
                        $value = is_array($value) ? $value : [$value];
                        foreach ($value as $v) {
                            $selectedValues['products_' . $key][] = $v;
                        }
                    }
                }
            }

        }
        if(sizeof($systemParamValues) > 0) {
            foreach ($systemParamValues as $key => $systemParam) {
                if(isset($systemParam['additional'])){
                    $this->bxHelperData->setSystemParams($key, $systemParam['values']);
                }
            }
        }

        if (!$this->navigation) {
            $values = isset($requestParams['bx_category_id']) ? $requestParams['bx_category_id'] : $this->storeManager->getStore()->getRootCategoryId();
            $values = explode($separator, $values);
            $andSelectedValues = isset($facetOptions['category_id']) ? $facetOptions['category_id']['andSelectedValues']: false;
            $bxFacets->addCategoryFacet($values, 2, -1, $andSelectedValues);
        }

        foreach ($attributeCollection as $code => $attribute) {
            if($attribute['addToRequest'] || isset($selectedValues[$code])){
                $bound = $code == 'discountedPrice' ? true : false;
                list($label, $type, $order, $position) = array_values($attribute);

                $selectedValue = isset($selectedValues[$code]) ? $selectedValues[$code] : null;

                if ($code == 'discountedPrice' && isset($bxSelectedValues[$code])) {
                    $bxFacets->addPriceRangeFacet($bxSelectedValues[$code]);
                } else {
                    $andSelectedValues = isset($facetOptions[$code]) ? $facetOptions[$code]['andSelectedValues']: false;
                    $bxFacets->addFacet($code, $selectedValue, $type, $label, $order, $bound, -1, $andSelectedValues);
                }
            }
        }
        foreach($bxSelectedValues as $field => $values) {
            if($field == 'discountedPrice') continue;
            $andSelectedValues = isset($facetOptions[$field]) ? $facetOptions[$field]['andSelectedValues']: false;
            $bxFacets->addFacet($field, $values, 'string', null, 2, false, -1, $andSelectedValues);
        }
        return $bxFacets;
    }

    /**
     *
     */
    public function getClientResponse()
    {
        try {
            $response = self::$bxClient->getResponse();
            $output = self::$bxClient->getDebugOutput();
            if ($output != '' && !$this->bxDebug) {
                $this->response->appendBody($output);
                $this->bxDebug = true;
            }
            return $response;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function getBlogIds() {
        $this->simpleSearch();
        $choice_id = $this->getSearchChoice('', true);
        return $this->getClientResponse()->getHitIds($choice_id, true, 0, 10, $this->getEntityIdFieldName());

    }

    public function getBlogTotalHitCount() {
        $this->simpleSearch();
        $choice_id = $this->getSearchChoice('', true);
        return $this->getClientResponse()->getTotalHitCount($choice_id);
    }

    public function getHitVariable($id, $field, $is_blog=false) {
        $this->simpleSearch();
        $choice_id = $this->currentSearchChoice;
        if($is_blog) {
            $choice_id = $this->getSearchChoice('', true);
        }
        return $this->getClientResponse()->getHitVariable($choice_id, $id, $field, 0);
    }

    /**
     * @return mixed
     */
    public function getTotalHitCount($choiceId = '', $index = 0){

        $this->simpleSearch();
        $choiceId = $choiceId == '' ? $this->currentSearchChoice : $choiceId;
        return $this->getClientResponse()->getTotalHitCount($choiceId, true, $index);
    }

    /**
     * @param null $choiceId
     * @return mixed
     */
    public function getEntitiesIds($choiceId = '', $index = 0){

        $this->simpleSearch();
        $choiceId = $choiceId == '' ? $this->currentSearchChoice : $choiceId;
        return $this->getClientResponse()->getHitIds($choiceId, true, $index, 10, $this->getEntityIdFieldName());
    }

    /**
     * @return mixed
     */
    public function getOverlayValues($key, $widget)
    {
      $bxRequest = new \com\boxalino\bxclient\v1\BxRequest($this->bxHelperData->getLanguage(), $widget, 0, 0);
      self::$bxClient->addRequest($bxRequest);

      return $this->getResponse()->getExtraInfo($key, '', $widget);
    }

    /**
     * @param bool $getFinder
     * @return null
     */
    public function getFacets($getFinder = false)
    {
        $this->simpleSearch($getFinder);
        $facets = $this->getClientResponse()->getFacets($this->currentSearchChoice);
        if (empty($facets)) {
            return null;
        }

        $facets->setParameterPrefix($this->getUrlParameterPrefix());
        return $facets;
    }

    /**
     * @return mixed
     */
    public function getCorrectedQuery()
    {

        $this->simpleSearch();
        return $this->getClientResponse()->getCorrectedQuery($this->currentSearchChoice);
    }

    /**
     * @return mixed
     */
    public function areResultsCorrected()
    {

        $this->simpleSearch();
        return $this->getClientResponse()->areResultsCorrected($this->currentSearchChoice);
    }

    /**
     * @return mixed
     */
    public function areThereSubPhrases()
    {

        $this->simpleSearch();
        return $this->getClientResponse()->areThereSubPhrases($this->currentSearchChoice);
    }

    /**
     * @return mixed
     */
    public function getSubPhrasesQueries()
    {

        $this->simpleSearch();
        return $this->getClientResponse()->getSubPhrasesQueries($this->currentSearchChoice);
    }

    /**
     * @param $queryText
     * @return mixed
     */
    public function getSubPhraseTotalHitCount($queryText)
    {

        $this->simpleSearch();
        return $this->getClientResponse()->getSubPhraseTotalHitCount($queryText, $this->currentSearchChoice);
    }

    /**
     * @param $queryText
     * @return mixed
     */
    public function getSubPhraseEntitiesIds($queryText)
    {

        $this->simpleSearch();
        return $this->getClientResponse()->getSubPhraseHitIds($queryText, $this->currentSearchChoice, 0, $this->getEntityIdFieldName());
    }

    /**
     * @param $widgetName
     * @param array $context
     * @param string $widgetType
     * @param int $minAmount
     * @param int $amount
     * @param bool $execute
     * @return array|void
     */
    public function getRecommendation($widgetName, $context = array(), $widgetType = '', $minAmount = 3, $amount = 3, $execute = true, $returnFields = array())
    {
        if (!$execute) {
            if (!isset(self::$choiceContexts[$widgetName])) {
                self::$choiceContexts[$widgetName] = array();
            }
            if (in_array(json_encode($context), self::$choiceContexts[$widgetName])) {
                return;
            }
            self::$choiceContexts[$widgetName][] = json_encode($context);
            if ($widgetType == '') {
                $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->bxHelperData->getLanguage(), $widgetName, $amount);
                $bxRequest->setGroupBy('products_group_id');
                $bxRequest->setMin($minAmount);
                $bxRequest->setFilters($this->getSystemFilters());
                if (isset($context[0])) {
                    $product = $context[0];
                    $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                }
                self::$bxClient->addRequest($bxRequest);
            } else {
                if (($minAmount >= 0) && ($amount >= 0) && ($minAmount <= $amount)) {
                    $bxRequest = null;
                    if($widgetType == 'parametrized') {
                      $bxRequest = new \com\boxalino\bxclient\v1\BxParametrizedRequest($this->bxHelperData->getLanguage(), $widgetName, $amount, $minAmount);
                    } else {
                      $bxRequest = new \com\boxalino\bxclient\v1\BxRecommendationRequest($this->bxHelperData->getLanguage(), $widgetName, $amount, $minAmount);
                    }

                    if ($widgetType != 'blog') {
                      $bxRequest->setGroupBy('products_group_id');
                      $bxRequest->setFilters($this->getSystemFilters());
                    }
                    $bxRequest->setReturnFields(array_merge(array($this->getEntityIdFieldName()), $returnFields));

                    $categoryId = is_null($this->registry->registry('current_category')) ? 2 : $this->registry->registry('current_category')->getId();
                    self::$bxClient->addRequestContextParameter('current_category_id', $categoryId);

                    if ($widgetType === 'basket' && is_array($context)) {
                        $basketProducts = array();
                        foreach ($context as $product) {
                            $basketProducts[] = array('id' => $product->getId(), 'price' => $product->getPrice());
                        }
                        $bxRequest->setBasketProductWithPrices($this->getEntityIdFieldName(), $basketProducts);
                    } elseif (($widgetType === 'product' || $widgetType === 'blog') && !is_array($context)) {
                        $product = $context;
                        $bxRequest->setProductContext($this->getEntityIdFieldName(), $product->getId());
                    } elseif ($widgetType === 'category' && $context != null) {
                        $filterField = "category_id";
                        $filterValues = is_array($context) ? $context : array($context);
                        $filterNegative = false;
                        $bxRequest->addFilter(new BxFilter($filterField, $filterValues, $filterNegative));
                    } elseif ($widgetType === 'banner' || $widgetType === 'banner_small') {
                        $bxRequest->setGroupBy('id');
                        $bxRequest->setFilters(array());
                        $contextValues = is_array($context) ? $context : array($context);
                        self::$bxClient->addRequestContextParameter('banner_context', $contextValues);
                    }
                    self::$bxClient->addRequest($bxRequest);
                }
            }
            return array();
        }
        $count = array_search(json_encode(array($context)), self::$choiceContexts[$widgetName]);
        return $this->getClientResponse()->getHitIds($widgetName, true, $count, 10, $this->getEntityIdFieldName());
    }

    /**
     * @param $warning
     */
    public function notifyWarning($warning) {
        self::$bxClient->notifyWarning($warning);
    }


    public function addNotification($type, $notification) {
        self::$bxClient->addNotification($type, $notification);
    }

    /**
     * @param bool $force
     * @param string $requestMapKey
     */
    public function finalNotificationCheck($force = false, $requestMapKey = 'dev_bx_notifications') {
        if(!is_null(self::$bxClient)) {
            $output = self::$bxClient->finalNotificationCheck($force, $requestMapKey);
            if($output != '') {
                $this->response->appendBody($output);
            }
        }
    }

    public function getResponse()
    {
        $this->simpleSearch();
        $response = $this->getClientResponse();
        return $response;
    }

    public function getSEOPageTitle(){
        if ($this->bxHelperData->isPluginEnabled()) {
            $seoPageTitle = $this->getExtraInfoWithKey('bx-page-title');
            return $seoPageTitle;
        }
        return;
    }

    public function getSEOMetaTitle(){
        if ($this->bxHelperData->isPluginEnabled()) {
            $seoMetaTitle = $this->getExtraInfoWithKey('bx-html-meta-title');
            return $seoMetaTitle;
        }
        return;
    }

    public function getSEOMetaDescription(){
        if ($this->bxHelperData->isPluginEnabled()) {
            $seoMetaDescription = $this->getExtraInfoWithKey('bx-html-meta-description');
            return $seoMetaDescription;
        }
        return;
    }

    public function getExtraInfoWithKey($key){
        if ($this->bxHelperData->isPluginEnabled() && !empty($key)) {
            $query = $this->queryFactory->get();
            $queryText = $query->getQueryText();
            $choice = $this->getSearchChoice($queryText);
            $extraInfo = $this->getResponse()->getExtraInfo($key, '', $choice);
            return $extraInfo;
        }
        return;
    }
}
