INSERT INTO `oc_event` (`event_id`, `code`, `trigger`, `action`, `status`, `sort_order`) VALUES
(59, 'gtm', 'catalog/view/common/success_checkout/after', 'extension/analytics/gtm/purchase', 1, 0),
(58, 'gtm', 'catalog/view/extension/quickcheckout/cart/after', 'extension/analytics/gtm/add_shipping_info', 1, 0),
(57, 'gtm', 'catalog/view/checkout/header/after', 'extension/analytics/gtm', 1, 0),
(55, 'gtm', 'catalog/view/extension/quickcheckout/checkout/after', 'extension/analytics/gtm/begin_checkout', 1, 0),
(54, 'gtm', 'catalog/view/checkout/cart/after', 'extension/analytics/gtm/view_cart', 1, 0),
(53, 'gtm', 'catalog/view/product/product/after', 'extension/analytics/gtm/view_item', 1, 0),
(52, 'gtm', 'catalog/controller/checkout/cart/remove/after', 'extension/analytics/gtm/remove_from_cart', 1, 0),
(51, 'gtm', 'catalog/controller/checkout/cart/add/after', 'extension/analytics/gtm/add_to_cart', 1, 0),
(50, 'gtm', 'catalog/view/product/category/after', 'extension/analytics/gtm/view_item_list', 1, 0),
(48, 'gtm', 'catalog/view/common/header/after', 'extension/analytics/gtm', 1, 0);

INSERT INTO `oc_setting` (`setting_id`, `store_id`, `code`, `key`, `value`, `serialized`) VALUES
(25253, 0, 'gtm', 'gtm_id', 'GTM-NVKXC5S', 0);