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
		return '
		<script>
		' . (isset($data['ecommerce']) ? 'dataLayer.push({ ecommerce: null });' : '') . '
		dataLayer.push('.json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).');
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
			$manufacturer_name = $this->db->query("SELECT m.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) WHERE p.product_id = '" . $product['product_id'] . "'")->row['name'];
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
		
		$head .= $this->user_data();
		
		if (!empty($this->session->data['gtm']['events_queue'])) {
			foreach($this->session->data['gtm']['events_queue'] as $event_data) {
				$head .= $this->event_script($event_data);
			}
			unset($this->session->data['gtm']['events_queue']);
		}
		
		$body = $this->body();
		
		$cart_products = $this->cart->getProducts();
		$this->session->data['gtm']['cart'] = $cart_products;
		
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
		<!-- End Google Tag Manager (noscript) -->
		EOT;
	}
	
	public function user_data() {
		
		$user_data = [];
		if ($this->customer->isLogged()) {
			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
			$user_data['userId'] = $this->customer->getId();
			$user_data['email'] = $customer_info['email'];
			$user_data['phone_number'] = $customer_info['telephone'];
			$user_data['address']['first_name'] = $customer_info['firstname'];
			$user_data['address']['last_name'] = $customer_info['lastname'];
		} elseif (isset($this->session->data['guest'])) {
			$user_data['userId'] = null;
			$user_data['email'] = $this->session->data['guest']['email'];
			$user_data['phone_number'] = $this->session->data['guest']['telephone'];
			$user_data['address']['first_name'] = $this->session->data['guest']['firstname'];
			$user_data['address']['last_name'] = $this->session->data['guest']['lastname'];
		}
		$user_data['address']['street'] = $this->session->data['payment_address']['address_1'] ?? null;
		$user_data['address']['city'] = $this->session->data['payment_address']['city'] ?? null;
		$user_data['address']['region'] = $this->session->data['payment_address']['zone'] ?? null;
		$user_data['address']['postal_code'] = $this->session->data['payment_address']['postcode'] ?? null;
		$user_data['address']['country'] = $this->session->data['payment_address']['iso_code_2'] ?? null;
		
		return $this->event_script($user_data);
	}
	
	public function view_item_list(&$route, &$args, &$output) {
		
		if (isset($args['products'])) {
		
			$items = [];
			foreach($args['products'] as $index => $product) {
			
				$item_list_name = 'Category';
				switch($this->route) {
					case 'product/category':
					default:
						$item_list_name = 'Category';
						break;
				}
				
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
			//console.log(JSON.stringify(view_item_list_event));
			dataLayer.push(view_item_list_event);
			
			//Google Tag Manager Select item
			$(document).ready(function() {
				$('body').on('click', '[data-product-id] a', function(e) {
					e.preventDefault();
					const productId = $(this).closest('[data-product-id]').data('product-id');
					const redirect = $(this).attr('href');
					items.forEach(function(item) {
						if (item.item_id == productId) {
							dataLayer.push({ ecommerce: null });
							const select_item_event = {
								"event": "select_item",
								"ecommerce": {
									"$item_list_name": "{$item_list_name}",
									"items": [
										item
									]
								}
							};
							console.log(JSON.stringify(select_item_event));
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
		$item['item_list_name'] = 'Cart';
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
	
	public function view_item(&$route, &$args, &$output) {
		
		$product = [
			'product_id' => $args['product_id'],
			'model' => $args['model'],
			'name' => $args['name'],
			'price' => $args['price'],
			'special' => $args['special'],
			'manufacturer' => $args['manufacturer'],
			'categories' => array_column($args['categories'], 'name'),
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
			foreach($this->session->data['gtm']['cart'] as $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['quantity'] = $_cart_item['quantity'];
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
	
	public function add_shipping_info(&$route, &$args, &$output) {
	
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
		
		$this->add_to_head($script, $output);
		$output .= PHP_EOL.$script;
		
		$this->session->data['gtm']['add_shipping_info'] = true;
	}
	
	public function add_payment_info(&$route, &$args, &$output) {
	
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
		
		$this->add_to_head($script, $output);
		$output .= PHP_EOL.$script;
		
		$this->session->data['gtm']['add_payment_info'] = true;
	}
	
	public function purchase(&$route, &$args, &$output) {
		
		if (isset($args['order_id'])) {
			$transaction_id = $args['order_id'];
		} else {
			return;
		}
		
		$items = [];
		
		$value = 0;
		
		if (isset($this->session->data['gtm']['cart'])) {
			foreach($this->session->data['gtm']['cart'] as $_cart_item) {
				$item = $this->format_product($_cart_item);
				$item['quantity'] = $_cart_item['quantity'];
				$items[] = $item;
			}
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
				'items' => [$item],
			],
		];
		
		$script = $this->event_script($event_data);
		
		$this->add_to_head($script, $output);
	}
}
