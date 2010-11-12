<?php defined('SYSPATH') or die('No direct script access.');

return array(
	// default options for claeroadmin controller
	'default_list_options' => array(
		'sort_by_column' => NULL, // orm defaults to primary key
		'sort_by_order' => NULL, // orm defaults to DESC
		'page' => 1,
		'search' => NULL,
	),
	'session_key' => 'claeroadmin',
	// default database group to use when a specific model is not loaded, or if the model does not specify a db
	'db_group' => 'default',
	/**
	* Model list to be used in claeroadmin
	* An array of model names (keys) and display names (values)
	* Set the display name to an empty value to disable it (NULL, FALSE, etc)
	* The first one will be used as the default or when there is an attempt to access one that doesn't exist
	* The list will be sorted by the display name (value) before being displayed
	*/
	'model_list' => array(
		// model name => display name
		'useradmin' => 'User',
		'authlog' => 'Auth Log',
		'authtype' => 'Auth Type',
		'group' => 'Group',
		'grouppermission' => 'Group - Permission',
		'permission' => 'Permission',
		'usergroup' => 'User - Group',
		'demo' => 'Demo',
		'demosub' => 'Demo Sub',
	),
	// an array of actions that shouldn't be used in permission checking (because it saves on a lot of extra permissions)
	'action_to_permission' => array(
		'cancel' => 'index',
		'cancel_search' => 'index',
		'download' => 'index',
		'edit_multiple' => 'edit',
		'create' => 'model_create',
	),
);