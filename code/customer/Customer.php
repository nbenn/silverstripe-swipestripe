<?php
/**
 * Represents a {@link Customer}, a type of {@link Member}.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage customer
 */
class Customer extends Member implements PermissionProvider {

	private static $db = array(
		'Code' => 'Int' //Just to trigger creating a Customer table
	);
	
	/**
	 * Link customers to {@link Address}es and {@link Order}s.
	 * 
	 * @var Array
	 */
	private static $has_many = array(
		'Orders' => 'Order'
	);

	private static $searchable_fields = array(
		'Surname',
		'Email'
	);

	public function providePermissions() {
		return array(
			'VIEW_CUSTOMER' => array(
				'name' => _t('Permissions.SHOP_VIEW_CUSTOMERS', 'View shop customers'),
				'category' => _t('Permissions.SHOP_CATEGORY', 'Shop permissions'),
				'sort' => 1,
				'help' => _t('Permissions.SHOP_VIEW_CUSTOMERS_HELP', 'Ability to view all customers')
			),
			'EDIT_CUSTOMER' => array(
				'name' => _t('Permissions.SHOP_EDIT_CUSTOMERS', 'Edit shop customer data'),
				'category' => _t('Permissions.SHOP_CATEGORY', 'Shop permissions'),
				'sort' => 2,
				'help' => _t('Permissions.SHOP_EDIT_CUSTOMERS_HELP', 'Ability to edit all customer data')
			),
			'DELETE_CUSTOMER' => array(
				'name' => _t('Permissions.SHOP_DELETE_CUSTOMERS', 'Delete shop customer'),
				'category' => _t('Permissions.SHOP_CATEGORY', 'Shop permissions'),
				'sort' => 4,
				'help' => _t('Permissions.SHOP_DELETE_CUSTOMERS_HELP',
					'Ability to delete a customer iff there are no associated orders')
			)
		);
	}

	public function canView($member = null) {
		if ($member == null && !$member = Member::currentUser()) {
			return false;
		} else {
			return Permission::checkMember($member, 'CMS_ACCESS_ShopAdmin')
				&& Permission::checkMember($member, 'VIEW_CUSTOMER');
		}
	}

	public function canEdit($member = null) {
		if ($member == null && !$member = Member::currentUser()) {
			return false;
		} else {
			return Permission::checkMember($member, 'CMS_ACCESS_ShopAdmin')
				&& Permission::checkMember($member, 'EDIT_CUSTOMER');
		}
	}

	/**
	 * Prevent customers from being deleted unless the have no orders associated.
	 * 
	 * @see Member::canDelete()
	 */
	public function canDelete($member = null) {

		$orders = $this->Orders();
		if ($orders && $orders->exists()) {
			return false;
		}
		else if ($member == null && !$member = Member::currentUser()) {
			return false;
		} else {
			return Permission::checkMember($member, 'CMS_ACCESS_ShopAdmin')
				&& Permission::checkMember($member, 'EDIT_CUSTOMER');
		}
	}

	public function delete() {
		if ($this->canDelete(Member::currentUser())) {
			parent::delete();
		}
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		//Create a new group for customers
		$allGroups = DataObject::get('Group');
		$existingCustomerGroup = $allGroups->find('Title', 'Customers');
		if (!$existingCustomerGroup) {
			
			$customerGroup = new Group();
			$customerGroup->Title = 'Customers';
			$customerGroup->setCode($customerGroup->Title);
			$customerGroup->write();

			Permission::grant($customerGroup->ID, 'VIEW_ORDER');
		}
	}

	/**
	 * Add some fields for managing Members in the CMS.
	 * 
	 * @return FieldList
	 */
	public function getCMSFields() {

		$fields = new FieldList();

		$fields->push(new TabSet('Root', 
			Tab::create('Customer')
		));

		$password = new ConfirmedPasswordField(
			'Password', 
			null, 
			null, 
			null, 
			true // showOnClick
		);
		$password->setCanBeEmpty(true);
		if(!$this->ID) $password->showOnClick = false;

		$fields->addFieldsToTab('Root.Customer', array(
			new TextField('FirstName'),
			new TextField('Surname'),
			new EmailField('Email'),
			new ConfirmedPasswordField('Password'),
			$password
		));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}
	
	/**
	 * Overload getter to return only non-cart orders
	 * 
	 * @return ArrayList Set of previous orders for this member
	 */
	public function Orders() {
		return Order::get()
			->where("\"MemberID\" = " . $this->ID . " AND \"Order\".\"Status\" != 'Cart'")
			->sort("\"Created\" DESC");
	}
	
	/**
	 * Returns the current logged in customer
	 *
	 * @return bool|Member Returns the member object of the current logged in
	 *                     user or FALSE.
	 */
	static function currentUser() {
		$id = Member::currentUserID();
		if($id) {
			return DataObject::get_one("Customer", "\"Member\".\"ID\" = $id");
		}
	}
}
