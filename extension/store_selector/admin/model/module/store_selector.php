<?php
namespace Opencart\Admin\Model\Extension\StoreSelector\Module;
/**
 * Class StoreSelector
 *
 * @package Opencart\Admin\Model\Extension\StoreSelector\Module
 */
class StoreSelector extends \Opencart\System\Engine\Model {
	/**
	 * @return void
	 */
	public function install(): void {
		// No database tables required.
	}

	/**
	 * @return void
	 */
	public function uninstall(): void {
		// Settings are kept on uninstall.
	}
}
