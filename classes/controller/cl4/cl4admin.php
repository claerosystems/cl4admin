<?php defined('SYSPATH') or die('No direct script access.');

/**
* This controller handles the features of add, edit, delete, etc. of database records
*/
class Controller_cl4_cl4Admin extends Controller_Base {
	protected $db_group = NULL; // the default database config to use, needed for when a specific model is not loaded
	protected $model_name = NULL; // the name of the model currently being manipulated
	protected $model_display_name = NULL; // the fulll, friendly object name as specified in the options or the model itself
	protected $target_object = NULL; // the actual model object for $model_name

	protected $id = NULL;
	// stores the values in the session for the current model (by reference)
	protected $model_session = NULL;
	protected $page_offset = 1;
	protected $search = NULL;
	protected $sort_column = NULL;
	protected $sort_order = NULL;
	protected $session_key = NULL;

	public $page = 'cl4admin';

	// true means users must be logged in to access this controller
	public $auth_required = TRUE;
	// secure actions is false because there is special functionality for cl4admin (see check_perm())
	//public $secure_actions = FALSE; leaving value as default

	public function before() {
		$action = Request::instance()->action;

		parent::before();

		// set up the default database group
		$this->db_group = Kohana::config('cl4admin.db_group');

		// assign the name of the default session array
		$this->session_key = Kohana::config('cl4admin.session_key');

		// set the information from the route/get/post parameters
		$this->model_name = Request::instance()->param('model');
		$this->id = Request::instance()->param('id');
		$page_offset = cl4::get_param('page');
		$sort_column = cl4::get_param('sort_by_column');
		$sort_order = cl4::get_param('sort_by_order');

		// get the model list from the config file
		$model_list = $this->get_model_list();
		// get the default model and check if it's set or use the first model in the model list
		$default_model = $this->get_default_model();
		if (empty($default_model)) {
			$default_model = key($model_list);
		}

		$last_model = isset($this->session[$this->session_key]['last_model']) ? $this->session[$this->session_key]['last_model'] : NULL;

		// check to see if we haven't been passed a model name
		if (empty($this->model_name)) {
			// is there a last model stored in the session? then use it
			if ( ! empty($last_model) && ! empty($last_model)) {
				$go_to_model = $last_model;
			// if not, use the first model in the model list
			} else {
				$go_to_model = $default_model;
			}

			Request::instance()->redirect('dbadmin/' . $go_to_model . '/index');
		} // if

		// check to see the user has permission to access this action
		// determine what action we should use to determine if they have permission
		// get config and then check to see if the current action is defined in the array, otherwise use the action
		$action_to_perm = Kohana::config('cl4admin.action_to_permission');
		$perm_action = Arr::get($action_to_perm, $action, $action);
		if ( ! $this->check_perm($perm_action)) {
			// we can't use the default functionality of secure_actions because we have 2 possible permissions per action: global and per model
			if ($action != 'index') {
				Message::add('You don\'t have the correct permissions to access this action.', Message::$error);
				$this->redirect_to_index();
			} else if ($this->model_name != $default_model) {
				Message::add('You don\'t have the correct permissions to manage that item.', Message::$error);
				Request::instance()->redirect('dbadmin/' . $default_model . '/index');
			} else {
				Request::instance()->redirect('login/noaccess' . URL::array_to_query(array('referrer' => Request::instance()->uri()), '&'));
			}
		} // if

		// redirect the user to a different model as they one they selected isn't valid (not in array of models)
		if ( ! isset($model_list[$this->model_name]) && ((cl4::is_dev() && $action != 'create' && $action != 'model_create') || ! cl4::is_dev())) {
			Message::add('The model you attempted to access (' . $this->model_name . ') doesn\'t exist in the model list defined in the cl4admin config file.', Message::$debug);
			Request::instance()->redirect('dbadmin/' . $default_model . '/index');
		}

		// the first time to the page or first time for this model, so set all the defaults
		// or the action is cancel search or search
		// or we are looking at a new model
		if ( ! isset($this->session[$this->session_key][$this->model_name])) {
			// set all the defaults for this model/object
			$this->session[$this->session_key][$this->model_name] = Kohana::config('cl4admin.default_list_options');
		}

		$this->model_session =& $this->session[$this->session_key][$this->model_name];

		// check to see if anything came in from the page parameters
		// if we did, then set it in the session for the current model
		if ($page_offset !== NULL) $this->model_session['page'] = $page_offset;
		if ($sort_column !== NULL) $this->model_session['sort_by_column'] = $sort_column;
		if ($sort_order !== NULL) $this->model_session['sort_by_order'] = $sort_order;

		// set the values in object from the values in the session
		$this->page_offset = $this->model_session['page'];
		$this->sort_column = $this->model_session['sort_by_column'];
		$this->sort_order = $this->model_session['sort_by_order'];
		$this->search = ( ! empty($this->model_session['search']) ? $this->model_session['search'] : NULL);

		$this->session[$this->session_key]['last_model'] = $this->model_name;

		$this->add_admin_css();
	} // function before

	/**
	* Adds the CSS for cl4admin
	*/
	protected function add_admin_css() {
		if ($this->auto_render) {
			$this->template->styles['css/admin_base.css'] = 'screen';
		}
	} // function add_admin_css

	/**
	* Stores the current values for page, search and sorting in the session
	*/
	public function after() {
		$this->model_session['page'] = $this->page_offset;
		$this->model_session['sort_by_column'] = $this->sort_column;
		$this->model_session['sort_by_order'] = $this->sort_order;
		$this->model_session['search'] = $this->search;

		parent::after();
	} // function after

	protected function load_model($mode = 'view') {
		try {
			$orm_options = array(
				'mode' => $mode,
				'db_group' => $this->db_group,
			);

			Message::add('We are using model `' . $this->model_name . '` with mode `' . $mode . '` and id `' . $this->id . '`.', Message::$debug);

			$this->target_object = ORM::factory($this->model_name, $this->id, $orm_options);
			if ($this->auto_render) $this->template->page_title = $this->target_object->_table_name_display . ' Administration' . $this->template->page_title;

			// generate the friendly model name used to display to the user
			$this->model_display_name = ( ! empty($this->target_object->_table_name_display) ? $this->target_object->_table_name_display : cl4::underscores_to_words($this->model_name));

			Message::add('The model `' . $this->model_name . '` was loaded.', Message::$debug);

		} catch (Exception $e) {
			// display the error message
			cl4::exception_handler($e);
			Message::add('There was a problem loading the data.', Message::$error);
			Message::add('There was a problem loading the table or model: ' . $this->model_name, Message::$debug);

			// display the help view
			if (cl4::is_dev() && $e->getCode() == 3001) {
				Message::add('The model ' . $this->model_name . ' does not exist.', Message::$debug);
				if ($this->auto_render && $this->model_name != key($model_list)) {
					Request::instance()->redirect('dbadmin/' . key($model_list) . '/model_create?' . http_build_query(array('table_name' => $this->model_name)));
				}
			} else {
				// redirect back to the page and display the error
				Request::instance()->redirect('dbadmin/' . key($model_list) . '/index');
			} // if
		} // try
	} // function load_model

	/**
	* The default action
	* Just displays the editable list using display_editable_list()
	*/
	public function action_index() {
		$this->display_editable_list();
	}

	/**
	* display the editable list of records for the selected object
	*/
	public function display_editable_list($override_options = array()) {
		// display the object / table select
		$this->template->body_html .= $this->display_model_select();

		// set up the admin options
		$options = array(
			'mode' => 'view',
			'sort_by_column' => $this->sort_column,
			'sort_by_order' => $this->sort_order,
			'page_offset' => $this->page_offset,
			'in_search' => ( ! empty($this->search) || ! empty($this->sort_column)),
			'editable_list_options' => array(
				'per_row_links' => array(
					'view' => TRUE,     // view button
					'edit' => $this->check_perm('edit'),     // edit button
					'delete' => $this->check_perm('delete'),   // delete button
					'add' => $this->check_perm('add'),      // add (duplicate) button
					'checkbox' => ($this->check_perm('edit') || $this->check_perm('export')), // checkbox
				),
				'top_bar_buttons' => array(
					'add' => $this->check_perm('add'),             // add (add new) button
					'edit' => $this->check_perm('edit'),            // edit (edit selected) button
					'export_selected' => $this->check_perm('export'), // export selected button
					'export_all' => $this->check_perm('export'),      // export all button
					'search' => $this->check_perm('search'),          // search button
				),
			),
		);
		$options = Arr::merge($options, $override_options);

		$orm_multiple = new MultiORM($this->model_name, $options);

		// there is a search so apply it
		if ( ! empty($this->search)) {
			$orm_multiple->set_search($this->search);
		}

		try {
			$this->template->body_html .= '<section class="claeroDisplay">' . EOL;
			$this->template->body_html .= $orm_multiple->get_editable_list($options) . EOL;
			$this->template->body_html .= '</section>' . EOL;
		} catch (Exception $e) {
			cl4::exception_handler($e);
			$this->template->body_html .= 'There was a problem preparing the item list.';
		}
	} // function

	/**
	* cancel the current action by redirecting back to the index action
	*/
	function action_cancel() {
		// add a notice to be displayed
		Message::add('The previous action has been cancelled.', Message::$notice);
		// redirect to the index
		$this->redirect_to_index();
	} // function

	public function action_add() {
		$this->load_model('add');

		if ( ! empty($_POST)) {
			$this->save_model();
		}

		try {
			$this->template->body_html .= '<h2>Adding a New ' . HTML::chars($this->model_display_name) . ' Item</h2>' . EOL;

			// display the edit form
			$form_options = array(
				'mode' => 'add',
			);
			if ( ! empty($this->id)) {
				// set the form action because the current url includes the id of the record which will cause an update, not an add
				$form_options['form_action'] = URL::site(Request::current()->uri(array('id' => NULL))) . URL::query();
			}

			$this->template->body_html .= $this->target_object->get_form($form_options);
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while preparing the add form.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function action_add

	public function action_edit() {
		$this->load_model('edit');

		if ( ! empty($_POST)) {
			$this->save_model();
		}

		try {
			// preload the data if we have an id and this is the edit case
			$this->template->body_html .= '<h2>Editing a ' . HTML::chars($this->model_display_name) . ' Item</h2>' . EOL;

			// display the edit form
			$this->template->body_html .= $this->target_object->get_form(array(
				'mode' => 'edit',
			));
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while preparing the edit form.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		} // try
	} // function action_edit

	public function save_model() {
		try {
			// validate the post data against the model
			$validation = $this->target_object->save_values()->check();

			if ($validation === TRUE) {
				// save the record
				$this->target_object->save();

				if ($this->target_object->saved()) {
					Message::add('The item has been saved.', Message::$notice);
					$this->redirect_to_index();
				} else {
					Message::add('There was an error, and the item may not have been saved.', Message::$error);
				} // if
			} else {
				Message::add('The submitted values did not meet the validation requirements: ' . Message::add_validate_errors($this->target_object->validate(), $this->model_name), Message::$error);
			}
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was a problem saving the item. All the data may not have been saved.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		} // try
	} // function save_model

	/**
	* Views the record in a similar fashion to an edit, but without actual input fields
	*/
	public function action_view() {
		try {
			if ( ! ($this->id > 0)) {
				throw new Kohana_Exception('No ID received for view');
			}

			$this->load_model('view');

			$this->template->body_html .= '<h2>' . HTML::chars($this->model_display_name) . '</h2>' . EOL;
			$this->template->body_html .= $this->target_object->get_view();
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while viewing the item.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function

	public function action_edit_multiple() {
		try {
			// set up the admin options
			$options = array(
				'mode' => 'edit',
			);
			$orm_multiple = MultiORM::factory($this->model_name, $options);

			if (empty($_POST['ids'])) {
				try {
					$orm_multiple->save_edit_multiple();

					$this->redirect_to_index();
				} catch (Exception $e) {
					cl4::exception_handler($e);
					Message::add('There was an error while saving the records.', Message::$error);
				}
			} // if

			$this->template->body_html .= '<h2>Edit Multiple ' . HTML::chars($this->model_display_name) . ' Records</h2>' . EOL;
			$this->template->body_html .= $orm_multiple->get_edit_multiple($_POST['ids']);
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while preparing the edit form.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function

	public function action_delete() {
		try {
			if ( ! ($this->id > 0)) {
				Message::add('No ID was received so no item could be deleted.', Message::$error);
				$this->redirect_to_index();
			} // if

			$this->load_model();

			if ( ! empty($_POST)) {
				// see if they want to delete the item
				if (strtolower($_POST['cl4_delete_confirm']) == 'yes') {
					try {
						if ($this->target_object->delete() == 0) {
							Message::add('No item was deleted.', Message::$error);
						} else {
							Message::add('The item has been deleted from ' . html::chars($this->model_display_name) . '.', Message::$notice);
							Message::add('Record ID ' . $this->id . ' was deleted or expired.', Message::$debug);
						} // if
					} catch (Exception $e) {
						cl4::exception_handler($e);
						Message::add('There was an error while deleting the item.', Message::$error);
						if ( ! cl4::is_dev()) $this->redirect_to_index();
					}
				} else {
					Message::add('The item was <em>not</em> deleted.', Message::$notice);
				}

				$this->redirect_to_index();

			} else {
				$this->template->body_html .= '<h2>Delete Item in ' . HTML::chars($this->model_display_name) . '</h2>' . EOL;

				Message::add(View::factory('cl4/cl4admin/confirm_delete', array(
					'object_name' => $this->model_display_name,
				)));

				$this->template->body_html .= $this->target_object->get_view();
			}
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while preparing the delete form.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function action_delete

	// todo; figure out how to handle errors since this is not a page load, and auto-render is false
	public function action_download() {
		$this->auto_render = FALSE;

		try {
			// get the target column
			$column_name = Request::instance()->param('column_name');

			$this->load_model();

			// get the target table name
			$table_name = $this->target_object->table_name();

			// load the record
			if ( ! ($this->id > 0)) {
				throw new Kohana_Exception('No record ID was received, therefore no file could be downloaded');
			} // if

			// get the file name
			$filename = $this->target_object->$column_name;

			// check to see if the record has a filename
			if ( ! empty($filename)) {
				$this->target_object->stream_file($column_name);

			} else if (empty($filename)) {
				echo 'There is no file attached to this item.';
				throw new Kohana_Exception('There is no file associated with the record');
			} // if
		} catch (Exception $e) {
			cl4::exception_handler($e);
			echo 'There was a problem while downloading the file.';
		}
	} // function download

	public function action_export() {
		$this->template->body_html .= '<h2>' . HTML::chars($this->model_display_name) . '</h2>' . EOL;
		$this->template->body_html .= 'Export has not been implemented yet.';
	}

	/**
	* Prepares the search form
	*/
	public function action_search() {
		try {
			$this->load_model('search');

			if ( ! empty($_POST)) {
				// send the user back to page 1
				$this->page_offset = 1;
				// store the post (the search) in the session and the object
				$this->search = $this->model_session['search'] = $_POST;

				// redirect to the index page so the nav will work properly
				$this->redirect_to_index();

			} else {
				// display the search form or generate the search results
				$this->template->body_html .= '<h2>' . HTML::chars($this->model_display_name) . ' Search</h2>' . EOL;
				$this->template->body_html .= $this->target_object->get_form(array(
					'mode' => 'search',
				));
			}
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while preparing the search form.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function

	/**
	* Clears the search from the session and redirects the user to the index page for the model
	*/
	public function action_cancel_search() {
		try {
			// reset the search and search in the session
			$this->model_session = Kohana::config('cl4admin.default_list_options');

			$this->redirect_to_index();
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an error while problem while clearing the search.', Message::$error);
			if ( ! cl4::is_dev()) $this->redirect_to_index();
		}
	} // function

	/**
	* Generates the page with a table list, some JS and a textarea for the generated PHP for a model
	*/
	public function action_model_create() {
		try {
			$this->template->body_html = View::factory('cl4/cl4admin/model_create', array('db_group' => $this->db_group))
				->set('table_name', cl4::get_param('table_name'));

			$this->template->on_load_js = <<<EOA
$('#m_table_name').change(function() {
	$.get('/dbadmin/' + $(this).val() + '/create', function(data) {
		$('#model_code_container').val(data);
	});
});
EOA;
		} catch (Exception $e) {
			cl4::exception_handler($e);
			Message::add('There was an problem while preparing the model create view.', Message::$error);
		}
	} // function

	/**
	* Runs ModelCreate::create_model(); adds what is returned to the the request->response and turns off auto render so we don't get the extra HTML from the template
	*/
	public function action_create() {
		try {
			// we don't want the template controller automatically adding all the html
			$this->auto_render = FALSE;
			// generate a sample model file for the given table based on the database definition
			$this->request->response = ModelCreate::create_model($this->model_name);
		} catch (Exception $e) {
			cl4::exception_handler($e);
			echo 'There was an error while problem while creating the PHP model.';
		}
	} // function

	/**
	* Checks the permission based on action and the cl4admin controller
	* The 3 possible permissions are cl4admin/ * /[action] (no spaces around *) or cl4admin/[model name]/[action] or cl4admin/[model name]/ * (no spaces around *)
	*
	* @param 	string		$action		The action (permission) to check for; if left as NULL, the current action will be used
	* @param	string		$model_name	The model name to use in the check; if left as NULL, the current model will be used
	* @return 	bool
	*/
	public function check_perm($action = NULL, $model_name = NULL) {
		if ($action === NULL) {
			$action = Request::instance()->action;
		}
		if ($model_name === NULL) {
			$model_name = $this->model_name;
		}

		$auth = Auth::instance();

		if ($action != 'model_create') {
			// check if the user has access to all the models or access to this specific model
			return ($auth->logged_in('cl4admin/*/' . $action) || $auth->logged_in('cl4admin/' . $model_name . '/' . $action) || $auth->logged_in('cl4admin/' . $model_name . '/*'));
		} else {
			return $auth->logged_in('cl4admin/model_create');
		}
	} // function

	public function display_model_select() {
		// display the list of tables and the default table data
		try {
			$model_list = $this->get_model_list();
			asort($model_list);
			$model_select = Form::select('model', $model_list, $this->model_name, array('id' => 'cl4_model_select'));

			$return_html = View::factory('cl4/cl4admin/header', array(
				'model_select' => $model_select,
				'form_action' => URL::site(Request::current()->uri()) . URL::query(),
			));
		} catch (Exception $e) {
			cl4::exception_handler($e);
			// return an empty string because there is no proper message that can be displayed
			$return_html = '';
		}

		return $return_html;
	} // function

	/**
	* grab the model list from the cl4admin config file
	*
	*/
	public function get_model_list() {
		$model_list = Kohana::config('cl4admin.model_list');
		if ($model_list === NULL) $model_list = array();

		// remove any models that have name that are empty (NULL, FALSE, etc)
		// or that the user doesn't have permission to see the list of records (index)
		foreach ($model_list as $model => $name) {
			if (empty($name) || ! $this->check_perm('index', $model)) unset($model_list[$model]);
		}

		return $model_list;
	} // function

	/**
	* Gets the default model from the config file
	* Returns the model name
	*
	* @return string
	*/
	public function get_default_model() {
		return Kohana::config('cl4admin.default_model');
	}

	/**
	* Redirects the user to the index for the current model based on the current route
	*/
	function redirect_to_index() {
		try {
			Request::instance()->redirect('/' . Route::get(Route::name(Request::instance()->route))->uri(array('model' => $this->model_name, 'action' => 'index')));
		} catch (Exception $e) {
			cl4::exception_handler($e);
		}
	} // function
} // class