# Sample BestSelling Products API - Magento 2
---

This Magento 2 extension is A sample Magento 2 WEB API returns list of best-selling products.

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

## Main Features

* Use REST API end point > GET `/v1/products/bestsellers`
* Use Magento standard format of product api
* Use `sales_bestsellers_aggregated_daily` table for data collection and `SUM(aggregation.qty_ordered) AS total_sales`.

## Configure and Manage

* No configuration
* Resource for api link is anonymous and use GET method.
* Use `period` as custom searchCriteria field for filtered by date range value format: `YYYY-MM-DD` (With `condition_type` available: `from`, `to`, `gt`, `lt`).
* Use `special_price` searchCriteria field for filtered by only discounted products (has special price): `notnull`
* Use `total_sale` as custom sort order for sort by total sales(number of sold items): `ASC` or `DESC`.

| Key  | Value |
| ------------- | ------------- |
| searchCriteria[pageSize]  | 10  |
| searchCriteria[currentPage]  | 1  |
| searchCriteria[sortOrders][0][field] | total_sales  |
| searchCriteria[sortOrders][0][direction]  | DESC  |
| fields  | items[id,sku,price,name,custom_attributes],search_criteria,total_count  |
| searchCriteria[filter_groups][0][filters][0][field]  | period  |
| searchCriteria[filter_groups][0][filters][0][condition_type]  | from  |
| searchCriteria[filter_groups][0][filters][0][value]  | 2018-01-01  |
| searchCriteria[filter_groups][0][filters][1][field]  | period  |
| searchCriteria[filter_groups][0][filters][1][condition_type]  | to  |
| searchCriteria[filter_groups][0][filters][1][value]  | 2019-01-01  |
| searchCriteria[filter_groups][0][filters][2][field]  | special_price  |
| searchCriteria[filter_groups][0][filters][2][condition_type]  | notnull  |

```
GET /rest/V1/products/bestsellers?searchCriteria[pageSize]=10&searchCriteria[currentPage]=1&searchCriteria[sortOrders][0][field]=total_sales&searchCriteria[sortOrders][0][direction]=DESC&searchCriteria[filterGroups][0][filters][3][field]=special_price&searchCriteria[filterGroups][0][filters][3][conditionType]=notnull&fields=items[id,sku,price,name,custom_attributes],search_criteria,total_count&searchCriteria[filter_groups][0][filters][0][field]=period&searchCriteria[filter_groups][0][filters][0][condition_type]=from&searchCriteria[filter_groups][0][filters][0][value]=2017-01-01&searchCriteria[filter_groups][0][filters][1][field]=period&searchCriteria[filter_groups][0][filters][1][value]=2018-01-01&searchCriteria[filter_groups][0][filters][1][condition_type]=to
```

## Installation without Composer

* Download the files from github: [Direct download link](https://github.com/tuyennn/SampleBestSellingProductsAPI/tarball/master)
* Extract archive and copy all directories to app/code/Central/Product
* Go to project home directory and execute these commands

```bash
php bin/magento setup:upgrade
php bin/magento di:compile
php bin/magento setup:static-content:deploy
```

## Note

* The API will response the real exist products while the bestseller statistic report might return the deleted products.
* The API will not use the `rating_post` from report table so the data will not be limited by default number `5` which used for number of row and items cannot have `rating_post` more than `5`.

[Reference](https://github.com/magento/magento2/blob/2.3-develop/app/code/Magento/Sales/Model/ResourceModel/Report/Bestsellers/Collection.php#L149-L193)

## Licence

[Open Software License (OSL 3.0)](http://opensource.org/licenses/osl-3.0.php)