<?php
/**
 * Mixin to augment the {@link Payment} class.
 * Payment statuses: Incomplete,Success,Failure,Pending
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage order
 */
class Payment_Extension extends DataExtension implements PermissionProvider {

	private static $has_one = array(
		'Order' => 'Order' //Need to add Order here for ModelAdmin
	);

	private static $summary_fields = array(
		'Status' => 'Status',
		'SummaryOfAmount' => 'Amount',
		'Method' => 'Method',
		'PaidBy.Name' => 'Customer'
	);

	public function providePermissions() {
		return array(
			'VIEW_PAYMENT' => array(
				'name' => _t('Permissions.SHOP_VIEW_PAYMENTS', 'View payments'),
				'category' => _t('Permissions.SHOP_CATEGORY', 'Shop permissions'),
				'sort' => 11,
				'help' => _t('Permissions.SHOP_VIEW_PAYMENTS_HELP',
					'Ability to view payments associated with shop orders')
			),
			'EDIT_PAYMENT' => array(
				'name' => _t('Permissions.SHOP_EDIT_PAYMENTS', 'Edit payments'),
				'category' => _t('Permissions.SHOP_CATEGORY', 'Shop permissions'),
				'sort' => 12,
				'help' => _t('Permissions.SHOP_EDIT_PAYMENTS_HELP',
					'Ability to edit payments associated with shop orders (if granted, the associated
					users will be able to mark payments as complete)')
			)
		);
	}

	public function canView($member = null) {
		if ($member == null && !$member = Member::currentUser()) {
			return false;
		} else {
			return Permission::checkMember($member, 'CMS_ACCESS_ShopAdmin')
				&& Permission::checkMember($member, 'VIEW_PAYMENT');
		}
	}

	public function canEdit($member = null) {
		if ($member == null && !$member = Member::currentUser()) {
			return false;
		} else {
			return Permission::checkMember($member, 'CMS_ACCESS_ShopAdmin')
				&& Permission::checkMember($member, 'EDIT_PAYMENT');
		}
	}

	/**
	 * Cannot create {@link Payment}s in the CMS.
	 *
	 * @see DataObjectDecorator::canCreate()
	 * @return Boolean False always
	 */
	public function canCreate($member = null) {
		return false;
	}

	/**
	 * Cannot delete {@link Payment}s in the CMS.
	 * 
	 * @see DataObjectDecorator::canDelete()
	 * @return Boolean False always
	 */
	function canDelete($member = null) {
		return false;
	}
	
	/**
	 * Helper to get a nicely formatted amount for this {@link Payment}
	 * 
	 * @return String Payment amount formatted with Nice()
	 */
	function SummaryOfAmount() {
		return $this->owner->dbObject('Amount')->Nice();
	}
	
	/**
	 * Fields to display this {@link Payment} in the CMS, removed some of the 
	 * unnecessary fields.
	 * 
	 * @see DataObjectDecorator::updateCMSFields()
	 * @return FieldList
	 */
	function updateCMSFields(FieldList $fields) {

		$fields->removeByName('OrderID');
		$fields->removeByName('HTTPStatus');
		$fields->removeByName('Amount');

		$str = $this->owner->dbObject('Amount')->Nice();
		$fields->insertBefore(TextField::create('Amount_', 'Amount', $str), 'Method');

		return $fields;
	}

	/**
	 * After payment success process onAfterPayment() in {@link Order}.
	 * 
	 * @see Order::onAfterPayment()
	 * @see DataObjectDecorator::onAfterWrite()
	 */
	function onAfterWrite() {

		$order = $this->owner->Order();

		if ($order && $order->exists()) {
			$order->PaymentStatus = ($order->getPaid()) ? 'Paid' : 'Unpaid';
			$order->write();
		}
	}
}

class Payment_ProcessorExtension extends Extension {

	public function onBeforeRedirect() {

		$order = $this->owner->payment->Order();
		if ($order && $order->exists()) {
			$order->onAfterPayment();
		}
	}
}