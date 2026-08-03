<?php
namespace Opencart\Catalog\Controller\Extension\StockNotify\Event;
/**
 * Class Products
 *
 * @package Opencart\Catalog\Controller\Extension\StockNotify\Event
 */
class Products extends \Opencart\System\Engine\Controller {
	/**
	 * @param string            $route
	 * @param array<string,mixed> $args
	 * @param string            $output
	 *
	 * @return void
	 */
	public function productsAfter(string &$route, array &$args, string &$output): void {
		if (!$this->config->get('module_stock_notify_status')) {
			return;
		}

		$product = $args['product'] ?? [];

		if (!$this->isOutOfStockProduct($product) && strpos($output, 'out-of-stock') === false) {
			return;
		}

		$product_id = (int)($product['product_id'] ?? 0);
		$product_name = (string)($product['name'] ?? '');

		if ($product_id < 1 && preg_match('/data-product-id="(\d+)"/', $output, $match)) {
			$product_id = (int)$match[1];
		}

		if ($product_id < 1) {
			return;
		}

		if ($product_name === '' && preg_match('#class="name"><a[^>]*>(.*?)</a>#s', $output, $match)) {
			$product_name = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8'));
		}

		$this->load->model('extension/stock_notify/module/stock_notify');

		$output = $this->model_extension_stock_notify_module_stock_notify->injectAfterCompare(
			$output,
			$this->renderCardButton($product_id, $product_name)
		);
	}

	/**
	 * @param string            $route
	 * @param array<string,mixed> $args
	 * @param string            $output
	 *
	 * @return void
	 */
	public function sideProductsAfter(string &$route, array &$args, string &$output): void {
		if (!$this->config->get('module_stock_notify_status')) {
			return;
		}

		$this->load->model('extension/stock_notify/module/stock_notify');

		$output = $this->model_extension_stock_notify_module_stock_notify->injectCardButtonsHtml(
			$output,
			function (int $product_id, string $product_name): string {
				return $this->renderCardButton($product_id, $product_name);
			}
		);
	}

	/**
	 * @param array<string, mixed> $product
	 *
	 * @return bool
	 */
	private function isOutOfStockProduct(array $product): bool {
		if (!$product) {
			return false;
		}

		$classes = $product['classes'] ?? [];

		if (!empty($classes['out-of-stock'])) {
			return true;
		}

		return (int)($product['quantity'] ?? 0) <= 0;
	}

	/**
	 * @param int    $product_id
	 * @param string $product_name
	 *
	 * @return string
	 */
	private function renderCardButton(int $product_id, string $product_name): string {
		if ($product_id < 1) {
			return '';
		}

		$this->load->language('extension/stock_notify/module/stock_notify');

		return $this->load->view('extension/stock_notify/module/card_button', [
			'product_id'   => $product_id,
			'product_name' => $product_name,
			'tooltip'      => $this->language->get('text_notify_tooltip')
		]);
	}
}
