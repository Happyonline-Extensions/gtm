<?php
class ControllerExtensionAnalyticsGtm extends Controller {
	
	public function __construct($registry) {
        parent::__construct($registry);

        $this->route = (isset($this->request->get['route']) ? $this->request->get['route'] : 'common/home');
	}
	
	private function add_to_head($script, &$output) {
		$output = preg_replace('/<\/head>/', PHP_EOL.$script.PHP_EOL . '</head>', $output);
	}
	
	private function add_to_body($script, &$output) {
		$output = preg_replace('/<body(.*?)>/i', '<body$1>' . PHP_EOL.$script.PHP_EOL, $output);
	}
	
	private function event_script($data) {
		$json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
		return '
		<script>
		if (window.location.href.includes(\'debug\')) console.log(JSON.stringify('.$json.'));
		' . (isset($data['ecommerce']) ? 'dataLayer.push({ ecommerce: null });' : '') . '
		dataLayer.push('.$json.');
		</script>
		';
	}
	
	private function clean_price($price) {
	
		if ($price == false) return $price;
		
		if (preg_match('/[^0-9.]/', $price)) {
			$price = preg_replace('/[^0-9.]+/', '', str_replace(',', '.', str_replace('.', '', $price)));
		}
		
		$price = number_format($price, 2, '.', '');
		
		return $price;
	}
	
	private function format_product($product) {
	
		if (isset($product['initial_price'])) {
			$price_unformatted = $this->clean_price($product['initial_price']);
			$special_unformatted = $this->clean_price($product['price']);
		} else if (isset($product['special'])) {
			$price_unformatted = $this->clean_price($product['price']);
			$special_unformatted = $this->clean_price($product['special']);
		} else {
			$price_unformatted = $this->clean_price($product['price']);
			$special_unformatted = false;
		}
		
		$discount = 0;
		if ($special_unformatted) $discount = strval(round($price_unformatted - $special_unformatted, 2));
		
		if (!empty($product['categories'])) {
			$category_names = $product['categories'];
		} else {
			$this->load->model('catalog/product');
			$this->load->model('catalog/category');
			$category_names = [];
			$categories = $this->model_catalog_product->getCategories($product['product_id']);
			if ($categories) {
				foreach ($categories as $category) {
					$category_info = $this->model_catalog_category->getCategory($category['category_id']);
					if ($category_info) {
						$category_names[] = $category_info['name'];
					}
				}
			}
		}
		
		if (empty($product['manufacturer'])) {
			$manufacturer_name = $this->db->query("SELECT md.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "manufacturer_description md ON (p.manufacturer_id = md.manufacturer_id) WHERE p.product_id = '" . $product['product_id'] . "'")->row['name'];
			if ($manufacturer_name) $product['manufacturer'] = $manufacturer_name;
		}
	
		$item = [
			'item_id' => $product['product_id'],
			'item_name' => $product['name'],
			'affiliation' => null,
			'coupon' => null,
			'discount' => $discount,
			'item_brand' => $product['manufacturer'],
			'item_category' => $category_names[0] ?? null,
			'item_category2' => $category_names[1] ?? null,
			'item_category3' => $category_names[2] ?? null,
			'item_category4' => $category_names[3] ?? null,
			'item_category5' => $category_names[4] ?? null,
			'item_variant' => null,
			'price' => $special_unformatted ? $special_unformatted : $price_unformatted,
		];
		
		return $item;
	}
	
	public function index(&$route, &$args, &$output) {
	
		$head = $this->head();
		
		// $head .= $this->user_data();
		$head .= $this->user_data_alt();

		if (!empty($this->session->data['gtm']['events_queue'])) {
			foreach($this->session->data['gtm']['events_queue'] as $event_data) {
				$head .= $this->event_script($event_data);
			}
			unset($this->session->data['gtm']['events_queue']);
		}
		
		$body = $this->body();
		
		if (isset($this->session->data['gtm']['cart'])) $this->session->data['gtm']['old_cart'] = $this->session->data['gtm']['cart'];
		$cart_products = $this->cart->getProducts();
		$this->session->data['gtm']['cart'] = $cart_products;
		
		if (isset($this->request->get['route'])) {
			$route_parts = explode('/', $this->request->get['route']);
			if ($route_parts && count($route_parts) >= 2) {
				$page = ucfirst(str_replace('_', ' ', $route_parts[1]));
				if ($page != 'Not found') $this->session->data['gtm']['page'] = $page;
			}
		}
		
		$this->add_to_head($head, $output);
		$this->add_to_body($body, $output);
	}
	
	public function head() {
	
		$gtm_id = $this->config->get('gtm_id');
	
		return <<<EOT
		<!-- Google Tag Manager -->
		<script>
		window.dataLayer = window.dataLayer || [];
		</script>
		<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','$gtm_id');</script>
		<!-- End Google Tag Manager -->
		EOT;
	}
	
	public function body() {
	
		$gtm_id = $this->config->get('gtm_id');
	
		return <<<EOT
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=$gtm_id"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<script>
		$(document).ajaxComplete(function(event, xhr, settings) {
			if (
				settings.url.includes('index.php?route=checkout/cart/add') || 
				settings.url.includes('index.php?route=checkout/cart/edit') ||
				settings.url.includes('index.php?route=checkout/cart/edit_alt') || 
				settings.url.includes('index.php?route=checkout/cart/remove') || 
				settings.url.includes('index.php?route=account/wishlist/add') 
			) {
				$.getJSON('/?route=extension/analytics/gtm/events_queue', function(data) {
					data.forEach(function(event) {
						if (window.location.href.includes('debug')) console.log(JSON.stringify(event));
						if (event.ecommerce != undefined) dataLayer.push({ ecommerce: null });
						dataLayer.push(event);
					});
				}).fail(function(jqXHR, textStatus, errorThrown) {
					console.error(textStatus, errorThrown);
				});
			}
		});
		</script>
		<!-- End Google Tag Manager (noscript) -->
		EOT;
	}
	
	public function events_queue() {
		$events_queue = [];
		if (!empty($this->session->data['gtm']['events_queue'])) {
			$events_queue = $this->session->data['gtm']['events_queue'];
			unset($this->session->data['gtm']['events_queue']);
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($events_queue));
	}
	
	// public function user_data() {
		
	// 	$user_data = [];
	// 	if ($this->customer->isLogged()) {
	// 		$this->load->model('account/customer');
	// 		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
	// 		$user_data['userId'] = $this->customer->getId();
	// 		$user_data['email'] = $customer_info['email'];
	// 		$user_data['phone_number'] = $customer_info['telephone'];
	// 		$user_data['address']['first_name'] = $customer_info['firstname'];
	// 		$user_data['address']['last_name'] = $customer_info['lastname'];
	// 	} elseif (isset($this->session->data['guest'])) {
	// 		$user_data['userId'] = null;
	// 		$user_data['email'] = $this->session->data['guest']['email'];
	// 		$user_data['phone_number'] = $this->session->data['guest']['telephone'];
	// 		$user_data['address']['first_name'] = $this->session->data['guest']['firstname'];
	// 		$user_data['address']['last_name'] = $this->session->data['guest']['lastname'];
	// 	}
	// 	$user_data['address']['street'] = $this->session->data['payment_address']['address_1'] ?? null;
	// 	$user_data['address']['city'] = $this->session->data['payment_address']['city'] ?? null;
	// 	$user_data['address']['region'] = $this->session->data['payment_address']['zone'] ?? null;
	// 	$user_data['address']['postal_code'] = $this->session->data['payment_address']['postcode'] ?? null;
	// 	$user_data['address']['country'] = $this->session->data['payment_address']['iso_code_2'] ?? null;
		
	// 	return $this->event_script($user_data);
	// }

	public function user_data_alt() {
		// if(!isset($this->session->data['gtm_order_id'])) {
		if ($this->customer->isLogged()) {
			$user_data = [];
			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
			$user_data['userId'] = $this->customer->getId();
			$user_data['email'] = $customer_info['email'];
			$user_data['phone_number'] = $customer_info['telephone'];
			$user_data['address']['first_name'] = $customer_info['firstname'];
			$user_data['address']['last_name'] = $customer_info['lastname'];

			$event_data = [
				'event' => 'user_data',
				'user_data' => [
					'userId' 			=> $user_data['userId'],
					'email' 			=> $user_data['email'],
					'phone_number' 		=> $user_data['phone_number'],
					'address' => [
						'first_name' 	=> $user_data['address']['first_name'],
						'last_name' 	=> $user_data['address']['last_name']
					]
				]
			];
			
			return $this->event_script($event_data);
		}
	}

	public function user_data_purchase(&$route, &$args, &$output) {
		if(isset($this->session->data['gtm_order_id'])) {
			$user_data = [];
			if ($this->customer->isLogged()) {
				$this->load->model('account/customer');
				$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
				$user_data['userId'] = $this->customer->getId();
				$user_data['email'] = $customer_info['email'];
				$user_data['phone_number'] = $customer_info['telephone'];
				$user_data['address']['first_name'] = $customer_info['firstname'];
				$user_data['address']['last_name'] = $customer_info['lastname'];
			} elseif (isset($this->session->data['gtm_guest'])) {
				$user_data['userId'] = null;
				$user_data['email'] = isset($this->session->data['gtm_guest']['email']) ? $this->session->data['gtm_guest']['email'] : null;
				$user_data['phone_number'] = isset($this->session->data['gtm_guest']['telephone']) ? $this->session->data['gtm_guest']['telephone'] :  null;
				$user_data['address']['first_name'] = isset($this->session->data['gtm_guest']['firstname']) ? $this->session->data['gtm_guest']['firstname'] : null;
				$user_data['address']['last_name'] = isset($this->session->data['gtm_guest']['lastname']) ? $this->session->data['gtm_guest']['lastname'] :  null;
			}

			if((isset($this->session->data['gtm_order_id'])) && ((int)$this->session->data['gtm_order_id'] > 0)) {
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($this->session->data['gtm_order_id']);

				$user_data['address']['street'] = (isset($order_info['payment_address_1']) ? $order_info['payment_address_1'] : (isset($this->session->data['gtm_guest']['address_1']) ?? null));
				$user_data['address']['city'] = (isset($order_info['payment_city']) ? $order_info['payment_city'] : (isset($this->session->data['gtm_guest']['city']) ?? null));
				$user_data['address']['region'] = (isset($order_info['payment_zone']) ? $order_info['payment_zone'] : (isset($this->session->data['gtm_guest']['zone']) ?? null));
				$user_data['address']['postal_code'] = (isset($order_info['payment_postcode']) ? $order_info['payment_postcode'] : (isset($this->session->data['gtm_guest']['postcode']) ?? null));
				$user_data['address']['country'] = (isset($order_info['payment_country']) ? $order_info['payment_country'] : (isset($this->session->data['gtm_guest']['iso_code_2']) ?? null));
			}

			$event_data = [
				'event' => 'user_data',
				'user_data' => [
					'userId' 			=> $user_data['userId'],
					'email' 			=> $user_data['email'],
					'phone_number' 		=> $user_data['phone_number'],
					'address' => [
						'first_name' 	=> $user_data['address']['first_name'],
						'last_name' 	=> $user_data['address']['last_name'],
						'street' 		=> $user_data['address']['street'],
						'city' 			=> $user_data['address']['city'],
						'region' 		=> $user_data['address']['region'],
						'postal_code' 	=> $user_data['address']['postal_code'],
						'country'		=> $user_data['address']['country']
					]
				]
			];

			$script = $this->event_script($event_data);
			
			$this->add_to_head($script, $output);

			// unset($this->session->data['gtm_order_id']);
		}
	}
	
	public function view_item_list(&$route, &$args, &$output) {
		
		if (isset($args['products'])) {
		
			$items = [];
			foreach($args['products'] as $index => $product) {
			
				$item_list_name = $this->session->data['gtm']['page'];
				
				$item = $this->format_product($product, $index);
				$item['index'] = $index;
				$item['item_list_name'] = $item_list_name;
				$items[] = $item;
			}
			
			$items_json = json_encode($items, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
			
			$script = <<<EOT
			<script>
			//Google Tag Manager View item list
			const items = {$items_json};
			dataLayer.push({ ecommerce: null });
			const view_item_list_event = {
				"event": "view_item_list",
				"ecommerce": {
					"items": items
				}
			};
			if (window.location.href.includes('debug')) console.log(JSON.stringify(view_item_list_event));
			dataLayer.push(view_item_list_event);
			
			//Google Tag Manager Select item
			$(document).ready(function() {
				$('body').on('click', '[data-id] a', function(e) {
					e.preventDefault();
					const productId = $(this).closest('[data-id]').data('id');
					const redirect = $(this).attr('href');
					items.forEach(function(item) {
						if (item.item_id == productId) {
							dataLayer.push({ ecommerce: null });
							const select_item_event = {
								"event": "select_item",
								"ecommerce": {
									"item_list_name": "{$item_list_name}",
									"items": [
										item
									]
								}
							};
							if (window.location.href.includes('debug')) console.log(JSON.stringify(select_item_event));
							dataLayer.push(select_item_event);
						}
					});
					setTimeout(function() {
						window.location.href = redirect;
					}, 100);
				});
			});
			</script>
			EOT;
			$this->add_to_head($script, $output);
		}
	}
	
	public function add_to_cart(&$route, &$args, &$output) {
		
		$this->load->model('catalog/product');
		
		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			return;
		}

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) return;
		
		if ($product_info['price']) $product_info['price'] = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		if ($product_info['special']) $product_info['special'] = $this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		
		if (isset($this->request->post['quantity'])) {
			$quantity = (int)$this->request->post['quantity'];
		} else {
			$quantity = 1;
		}
		
		$item = $this->format_product($product_info);
		$item['item_list_name'] = $this->session->data['gtm']['page'];
		$item['quantity'] = $quantity;
		
		$value = (float)$item['price'] * (int)$item['quantity'];
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'add_to_cart',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => [
					$item
				],
			],
		];
		
		$this->session->data['gtm']['events_queue'][] = $event_data;
	}
	
	public function remove_from_cart(&$route, &$args, &$output) {
		
		$this->load->model('catalog/product');
		
		if (isset($this->request->post['key'])) {
			$key = (int)$this->request->post['key'];
		} else {
			return;
		}
		
		$cart_item = null;
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $_cart_item) {
				if ($_cart_item['cart_id'] == $key) {
					$cart_item = $_cart_item;
					break;
				}
			}
		}
		
		if (!isset($cart_item)) return;

		$product_info = $this->model_catalog_product->getProduct($cart_item['product_id']);

		if (!$product_info) return;
		
		if ($product_info['price']) $product_info['price'] = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		if ($product_info['special']) $product_info['special'] = $this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		
		$quantity = $cart_item['quantity'];
		
		$item = $this->format_product($product_info);
		$item['quantity'] = $quantity;
		
		$value = (float)$item['price'] * (int)$item['quantity'];
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'remove_from_cart',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => [
					$item
				],
			],
		];
		
		$this->session->data['gtm']['events_queue'][] = $event_data;
	}
	
	public function edit_cart(&$route, &$args, &$output) {
		
		$this->load->model('catalog/product');
		
		$cart_item = null;
		$operation = null;
		$quantity = null;
		if (isset($this->session->data['gtm']['cart']) && isset($this->session->data['gtm']['old_cart'])) {
		
			$new_cart = $this->session->data['gtm']['cart'];
			$old_cart = $this->session->data['gtm']['old_cart'];
			
			foreach($old_cart as $old_cart_item) {
			
				$found = false;
				foreach($new_cart as $new_cart_item) {
					if ($old_cart_item['cart_id'] == $new_cart_item['cart_id']) {
						$found = true;
						
						$quantity_diff = $new_cart_item['quantity'] - $old_cart_item['quantity'];
						
						if ($quantity_diff > 0) {
							$cart_item = $old_cart_item;
							$operation = 'add_to_cart';
							$quantity = abs($quantity_diff);
							break 2;
						} else if ($quantity_diff < 0) {
							$cart_item = $old_cart_item;
							$operation = 'remove_from_cart';
							$quantity = abs($quantity_diff);
							break 2;
						}
						
						
					}
				}
				if (!$found) {
					$cart_item = $old_cart_item;
					$operation = 'remove_from_cart';
					$quantity = $old_cart_item['quantity'];
					break;
				}
			}
		}
		
		if (!isset($cart_item) || !isset($operation) || !isset($quantity)) return;
		
		$product_info = $this->model_catalog_product->getProduct($cart_item['product_id']);

		if (!$product_info) return;
		
		if ($product_info['price']) $product_info['price'] = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		if ($product_info['special']) $product_info['special'] = $this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		
		$item = $this->format_product($product_info);
		$item['quantity'] = $quantity;
		
		$value = (float)$item['price'] * (int)$item['quantity'];
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => $operation,
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => [
					$item
				],
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
	}
	
	public function add_to_wishlist(&$route, &$args, &$output) {
		
		$this->load->model('catalog/product');
		
		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			return;
		}

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) return;
		
		if ($product_info['price']) $product_info['price'] = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		if ($product_info['special']) $product_info['special'] = $this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax'));
		
		$item = $this->format_product($product_info);
		$item['item_list_name'] = 'Wishlist';
		
		$value = (float)$item['price'] * (int)$item['quantity'];
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'add_to_wishlist',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => [
					$item
				],
			],
		];
		
		$this->session->data['gtm']['events_queue'][] = $event_data;
	}
	
	public function view_item(&$route, &$args, &$output) {
		
		$product = [
			'product_id' => $args['product_id'],
			'model' => $args['model'],
			'name' => $args['name'],
			'price' => $args['price'],
			'special' => $args['special'],
			'manufacturer' => $args['manufacturer'],
			'categories' => array_column($args['categories'] ?? [], 'name'),
		];
		
		$item = $this->format_product($product);
		$item['item_list_name'] = 'Product';
		
		$value = $item['price'];
		
		$event_data = [
			'event' => 'view_item',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => [$item],
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
	}
	
	public function view_cart(&$route, &$args, &$output) {
		
		$items = [];
		
		$value = 0;
		
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $index => $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['quantity'] = $_cart_item['quantity'];
				$item['index'] = $index;
				$item['item_list_name'] = 'Category';
				$value += (float)$item['price'] * (int)$item['quantity'];
				$items[] = $item;
			}
		}
		
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'view_cart',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'items' => $items,
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
	}
	
	public function begin_checkout(&$route, &$args, &$output) {
	
		if (isset($this->session->data['gtm']['begin_checkout'])) return;
		
		$items = [];
		
		$value = 0;
		
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $index => $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['index'] = $index;
				$item['quantity'] = $_cart_item['quantity'];
				$item['item_list_id'] = 'category';
				$item['item_list_name'] = 'Category';
				$value += (float)$item['price'] * (int)$item['quantity'];
				$items[] = $item;
			}
		}
		
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'begin_checkout',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'coupon' => null,
				'items' => $items,
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
		
		$this->session->data['gtm']['begin_checkout'] = true;
	}
	
	public function add_shipping_info_backup(&$route, &$args, &$output) {
	
		if (isset($this->session->data['gtm']['add_shipping_info'])) return;
		if (!isset($this->session->data['shipping_method']['title'])) return;
		
		$shipping_method = $this->session->data['shipping_method']['title'];
		
		$items = [];
		
		$value = 0;
		
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $index =>  $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['index'] = $index;
				$item['quantity'] = $_cart_item['quantity'];
				$item['item_list_id'] = 'category';
				$item['item_list_name'] = 'Category';
				$value += (float)$item['price'] * (int)$item['quantity'];
				$items[] = $item;
			}
		}
		
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'add_shipping_info',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'coupon' => null,
				'shipping_tier' => $shipping_method,
				'items' => $items,
			],
		];
		
		$script = $this->event_script($event_data);
		
		$script = $this->event_script($event_data);
		$output = preg_replace('/(<\/div>)\s*$/', $script . '$1', $output);
		
		$this->session->data['gtm']['add_shipping_info'] = true;
	}

	public function add_shipping_info(&$route, &$args, &$output) {
		if((isset($this->session->data['gtm_order_id'])) && ((int)$this->session->data['gtm_order_id'] > 0)) {
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($this->session->data['gtm_order_id']);

			if(!empty($order_info['shipping_method'])) {
				$shipping_method = $order_info['shipping_method'];
		
				$items = [];
				
				$value = 0;

				$products = $this->model_checkout_order->getOrderProducts($this->session->data['gtm_order_id']);
				
				if (isset($products)) {
					foreach($products as $index =>  $_cart_item) {
						$item = $this->format_product($_cart_item);
						$item['index'] = $index;
						$item['quantity'] = $_cart_item['quantity'];
						$item['item_list_id'] = 'category';
						$item['item_list_name'] = 'Category';
						$value += (float)$item['price'] * (int)$item['quantity'];
						$items[] = $item;
					}
				}
				
				$value = $this->clean_price($value);
				
				$event_data = [
					'event' => 'add_shipping_info',
					'ecommerce' => [
						'currency' => 'EUR',
						'value' => $value,
						'coupon' => null,
						'shipping_tier' => $shipping_method,
						'items' => $items,
					],
				];

				$script = $this->event_script($event_data);
				$output = preg_replace('/(<\/div>)\s*$/', $script . '$1', $output);

				$this->add_to_head($script, $output);
				
				// $this->session->data['gtm']['add_shipping_info'] = true;
			}
		}
	}
	
	public function add_payment_info_backup(&$route, &$args, &$output) {
	
		if (isset($this->session->data['gtm']['add_payment_info'])) return;
		if (!isset($this->session->data['payment_method']['title'])) return;
		
		$payment_method = $this->session->data['payment_method']['title'];
		
		$items = [];
		
		$value = 0;
		
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $index => $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['index'] = $index;
				$item['quantity'] = $_cart_item['quantity'];
				$item['item_list_id'] = 'category';
				$item['item_list_name'] = 'Category';
				$value += (float)$item['price'] * (int)$item['quantity'];
				$items[] = $item;
			}
		}
		
		$value = $this->clean_price($value);
		
		$event_data = [
			'event' => 'add_payment_info',
			'ecommerce' => [
				'currency' => 'EUR',
				'value' => $value,
				'coupon' => null,
				'payment_type' => $payment_method,
				'items' => $items,
			],
		];
		
		$script = $this->event_script($event_data);
		$output = preg_replace('/(<\/div>)\s*$/', $script . '$1', $output);
		
		$this->session->data['gtm']['add_payment_info'] = true;
	}

	public function add_payment_info(&$route, &$args, &$output) {
		if((isset($this->session->data['gtm_order_id'])) && ((int)$this->session->data['gtm_order_id'] > 0)) {
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($this->session->data['gtm_order_id']);

			if(!empty($order_info['payment_method'])) {
				$payment_method = $order_info['payment_method'];

				$items = [];
		
				$value = 0;
				
				$products = $this->model_checkout_order->getOrderProducts($this->session->data['gtm_order_id']);
				
				if (isset($products)) {
					foreach($products as $index =>  $_cart_item) {
						$item = $this->format_product($_cart_item);
						$item['index'] = $index;
						$item['quantity'] = $_cart_item['quantity'];
						$item['item_list_id'] = 'category';
						$item['item_list_name'] = 'Category';
						$value += (float)$item['price'] * (int)$item['quantity'];
						$items[] = $item;
					}
				}
				
				$value = $this->clean_price($value);
				
				$event_data = [
					'event' => 'add_payment_info',
					'ecommerce' => [
						'currency' => 'EUR',
						'value' => $value,
						'coupon' => null,
						'payment_type' => $payment_method,
						'items' => $items,
					],
				];

				$script = $this->event_script($event_data);
				$output = preg_replace('/(<\/div>)\s*$/', $script . '$1', $output);

				$this->add_to_head($script, $output);
			}
		}
	}
	
	public function purchase(&$route, &$args, &$output) {
		
		if (isset($args['order_id'])) {
			$transaction_id = $args['order_id'];
		} else {
			return;
		}
		
		if (isset($args['products'])) {
		
			$value = 0;
			$items = [];
			foreach($args['products'] as $index => $product) {
				$item_list_name = $this->session->data['gtm']['page'];

				$item = $this->format_product($product, $index);
				$item['quantity'] = $product['quantity'];
				$value += (float)$item['price'] * (int)$item['quantity'];
				$items[] = $item;
			}
		} else {
			return;
		}
		
		$this->load->model('checkout/order');
		$order_totals = $this->model_checkout_order->getOrderTotals($args['order_id']);
		
		$total = 0;
		$tax = 0;
		$shipping = 0;
		foreach($order_totals as $order_total) {
			if ($order_total['code'] == 'total') $total =  $this->clean_price($order_total['value']);
			if ($order_total['code'] == 'tax') $tax = $this->clean_price($order_total['value']);
			if ($order_total['code'] == 'shipping') $shipping = $this->clean_price($order_total['value']);
		}
		
		$event_data = [
			'event' => 'purchase',
			'ecommerce' => [
				'transaction_id' => $transaction_id,
				'value' => $total,
				'tax' => $tax,
				'shipping' => $shipping,
				'currency' => 'EUR',
				'coupon' => null,
				'items' => $items,
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
	}
}
