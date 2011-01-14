<h2>Model Code Generation</h2>
<p>It looks like you don't have a model set up yet. The following tool can help generate a starting point from your database table. Just select a table and click "create" to see some sample model code in the textarea below.</p>
<p>If you want to use this code, create a new file in your application/classes/model directory and name it objectname.php and copy and paste the code in to this file.  You will then want to make sure the meta data is all correct, espcially with respect to displaying sensitive data.</p>
Select a table to generate the cl4/orm model code:
<?php

echo Form::select('m_table_name', $table_list, $table_name, array('id' => 'm_table_name')) . '&nbsp;';
echo Form::input('create', 'Create', array('type' => 'button', 'id' => 'create_model'));
echo Form::textarea('', '', array(
	'id' => 'model_code_container',
	'class' => 'model_code_container',
));

?>