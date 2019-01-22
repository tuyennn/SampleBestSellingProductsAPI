<?php
namespace Central\Product\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\EntityManager\Operation\Read\ReadExtensions;


class BestSellerProductsManagement implements \Central\Product\Api\BestSellerProductsManagementInterface
{

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $metadataService;

    /**
     * @var \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface
     */
    protected $extensionAttributesJoinProcessor;

    /**
     * @var ReadExtensions
     */
    private $readExtensions;

    /**
     * Current store id
     *
     * @var int $_currentStoreId
     */
    protected $_currentStoreId = null;

    public function __construct(
        ProductFactory $productFactory,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface,
        \Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface $extensionAttributesJoinProcessor,
        ReadExtensions $readExtensions
    )
    {
        $this->productFactory = $productFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->metadataService = $metadataServiceInterface;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->readExtensions = $readExtensions;
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $this->determineStoreId($searchCriteria);

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->extensionAttributesJoinProcessor->process($collection);

        foreach ($this->metadataService->getList($this->searchCriteriaBuilder->create())->getItems() as $metadata) {
            $collection->addAttributeToSelect($metadata->getAttributeCode());
        }
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
        $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        $collection->getSelect()
            ->join(
                ['aggregation' => $collection->getTable('sales_bestsellers_aggregated_daily')],
                "e.entity_id = aggregation.product_id AND aggregation.store_id = {$this->_currentStoreId}",
                ['total_qty' => 'SUM(aggregation.qty_ordered)', 'total_sales' => 'SUM(aggregation.product_price)']
            )
            ->group('e.entity_id');

        //Add filters from root filter group to the collection
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }

        //Add SortOrder from root sort Order to the collection
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
            $this->addSortOrderToCollection($sortOrder, $collection);
        }

        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();
        $collection->addCategoryIds();
        $this->addExtensionAttributes($collection);

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        return $searchResult;
    }

    /**
     * Add extension attributes to loaded items.
     *
     * @param Collection $collection
     * @return Collection
     */
    private function addExtensionAttributes(Collection $collection): Collection
    {
        foreach ($collection->getItems() as $item) {
            $this->readExtensions->execute($item);
        }
        return $collection;
    }

    /**
     * Helper function that adds a FilterGroup to the collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param Collection $collection
     * @return void
     */
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        Collection $collection
    )
    {
        $fields = [];

        foreach ($filterGroup->getFilters() as $filter) {
            $conditionType = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $isApplied = $this->applyCustomFilter($collection, $filter, $conditionType);

            if (!$isApplied) {
                $fields[] = ['attribute' => $filter->getField(), $conditionType => $filter->getValue()];
            }
        }

        if ($fields) {
            $collection->addFieldToFilter($fields);
        }
    }

    /**
     * Helper function that adds a SortOrder to the collection.
     *
     * @param \Magento\Framework\Api\SortOrder $sortOrder
     * @param Collection $collection
     * @return void
     */
    protected function addSortOrderToCollection(
        SortOrder $sortOrder,
        Collection $collection
    )
    {
        $field = $sortOrder->getField();

        $isApplied = $this->applyCustomSort($collection, $sortOrder);

        if (!$isApplied) {
            $collection->addOrder(
                $field,
                ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
    }

    /**
     * Apply custom filters to product collection.
     *
     * @param Collection $collection
     * @param \Magento\Framework\Api\Filter $filter
     * @param string $conditionType
     * @return bool
     */
    private function applyCustomFilter(Collection $collection, \Magento\Framework\Api\Filter $filter, $conditionType)
    {
        if ($filter->getField() == 'period') {
            switch ($conditionType) {
                case 'from':
                    $collection->getSelect()->where('period >= ?', $filter->getValue());
                    break;
                case 'to':
                    $collection->getSelect()->where('period <= ?', $filter->getValue());
                    break;
                case 'gt':
                    $collection->getSelect()->where('period > ?', $filter->getValue());
                    break;
                case 'lt':
                    $collection->getSelect()->where('period < ?', $filter->getValue());
                    break;
                default:
            }

            return true;
        }

        if ($filter->getField() == 'category_id') {
            $categoryFilter[$conditionType][] = $filter->getValue();
            $collection->addCategoriesFilter($categoryFilter);
            return true;
        }

        if ($filter->getField() == 'store') {
            $collection->addStoreFilter($filter->getValue());
            return true;
        }

        if ($filter->getField() == 'website_id') {
            $value = $filter->getValue();
            if (strpos($value, ',') !== false) {
                $value = explode(',', $value);
            }
            $collection->addWebsiteFilter($value);
            return true;
        }

        return false;
    }

    /**
     * Apply custom sort order to product collection.
     *
     * @param Collection $collection
     * @param SortOrder $sortOrder
     * @return bool
     */
    private function applyCustomSort(Collection $collection, SortOrder $sortOrder) {
        if ($sortOrder->getField() == 'total_sales') {
            $direction = ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC';
            $collection->getSelect()->order(["total_sales {$direction}"]);
            return true;
        }

        if ($sortOrder->getField() == 'total_qty') {
            $direction = ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC';
            $collection->getSelect()->order(["total_qty {$direction}"]);
            return true;
        }

        return false;
    }

    /**
     * Determine which store is being processed
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return int
     */
    private function determineStoreId(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria) {

        if(null == $this->_currentStoreId) {
            $this->_currentStoreId = $this->storeManager->getStore()->getId();
            foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
                foreach ($filterGroup->getFilters() as $filter) {
                    if ($filter->getField() == 'store') {
                        $this->_currentStoreId = $filter->getValue();
                    }
                }
            }
        }

        return $this->_currentStoreId;
    }

}
