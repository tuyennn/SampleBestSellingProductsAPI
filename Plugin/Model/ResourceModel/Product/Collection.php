<?php

namespace Central\Product\Plugin\Model\ResourceModel\Product;


class Collection
{
    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $subject
     * @param $countSelect
     * @return mixed
     */
    public function afterGetSelectCountSql(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $subject,
        $countSelect
    ) {
        if (
            (string)$countSelect
            && !array_key_exists('sales_bestsellers_aggregated_daily', $countSelect->getPart('from'))
        ) {
            $countSelect->reset(\Magento\Framework\DB\Select::GROUP);
        }
        return $countSelect;
    }
}