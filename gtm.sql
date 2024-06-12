INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`) VALUES
('gtm', 'catalog/view/common/success_checkout/after', 'extension/analytics/gtm/purchase', 1, 0),
('gtm', 'catalog/view/extension/quickcheckout/cart/after', 'extension/analytics/gtm/add_payment_info', 1, 0),
('gtm', 'catalog/view/extension/quickcheckout/cart/after', 'extension/analytics/gtm/add_shipping_info', 1, 0),
('gtm', 'catalog/view/checkout/header/after', 'extension/analytics/gtm', 1, 0),
('gtm', 'catalog/view/extension/quickcheckout/checkout/after', 'extension/analytics/gtm/begin_checkout', 1, 0),
('gtm', 'catalog/view/checkout/cart/after', 'extension/analytics/gtm/view_cart', 1, 0),
('gtm', 'catalog/view/product/product/after', 'extension/analytics/gtm/view_item', 1, 0),
('gtm', 'catalog/controller/checkout/cart/remove/after', 'extension/analytics/gtm/remove_from_cart', 1, 0),
('gtm', 'catalog/controller/checkout/cart/add/after', 'extension/analytics/gtm/add_to_cart', 1, 0),
('gtm', 'catalog/view/product/category/after', 'extension/analytics/gtm/view_item_list', 1, 0),
('gtm', 'catalog/view/common/header/after', 'extension/analytics/gtm', 1, 0);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'gtm', 'gtm_id', 'GTM-NVKXC5S', 0);