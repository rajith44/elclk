<?php
namespace Opencart\Admin\Controller\Extension\StockNotify\Event;
/**
 * Class Product
 *
 * @package Opencart\Admin\Controller\Extension\StockNotify\Event
 */
class Product extends \Opencart\System\Engine\Controller {
	/** @var array<int, int> */
	private static array $old_quantities = [];

	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 *
	 * @return void
	 */
	public function before(string &$route, array &$args): void {
		if (!$this->config->get('module_stock_notify_status') || !isset($args[0])) {
			return;
		}

		$product_id = (int)$args[0];

		$model = new \Opencart\Catalog\Model\Extension\StockNotify\Module\StockNotify($this->registry);

		self::$old_quantities[$product_id] = $model->getProductQuantity($product_id);
	}

	/**
	 * @param string            $route
	 * @param array<int, mixed> $args
	 * @param mixed             $output
	 *
	 * @return void
	 */
	public function after(string &$route, array &$args, &$output): void {
		if (!$this->config->get('module_stock_notify_status') || !isset($args[0], $args[1]['quantity'])) {
			return;
		}

		$product_id = (int)$args[0];
		$old_quantity = self::$old_quantities[$product_id] ?? null;
		$new_quantity = (int)$args[1]['quantity'];

		unset(self::$old_quantities[$product_id]);

		if ($old_quantity === null || $old_quantity > 0 || $new_quantity <= 0) {
			return;
		}

		$model = new \Opencart\Catalog\Model\Extension\StockNotify\Module\StockNotify($this->registry);

		$model->notifyProductSubscribers($product_id);
	}
}
