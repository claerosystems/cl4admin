<?php
$routes = Kohana::config('claero.routes');

if ($routes['claeroadmin']) {
	// claero admin
	// Most cases: /dbadmin/user/edit/2
	// Special case for download: /dbadmin/demo/download/2/public_filename
	Route::set('claeroadmin', '(<lang>/)dbadmin(/<model>(/<action>(/<id>(/<column_name>))))', array('lang' => $lang_options))
	    ->defaults(array(
	        'lang' => DEFAULT_LANG,
	        'controller' => 'claeroadmin',
	        'model' => NULL, // this is the default object that will be displayed when accessing claeroadmin (dbadmin) without a model
	        'action' => 'index',
	        'id' => '',
	        'column_name' => NULL,
	));
}