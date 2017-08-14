<?php
return function($project_id) {

    global $double_data_entry, $user_rights, $quesion_by_section, $pageFields, $Proj;

    // Checking if we are in a data entry or survey page.
    if (!in_array(PAGE, array('DataEntry/index.php', 'surveys/index.php', 'Surveys/theme_view.php'))) {
        return;
    }
    // Checking additional conditions for survey pages.
    if (PAGE == 'surveys/index.php' && !(isset($_GET['s']) && defined('NOAUTH'))) {
        return;
    }

    // Checking current record ID.
    if (empty($_GET['id'])) {
        return;
    }
    $record = $_GET['id'];

    if (Records::formHasData($record, $_GET['page'], $_GET['event_id'], $_GET['instance'])) {
        return;
    }

    /* 
     * Populate forward_map which actually has the key,value pair of the fields present in branching logic 
     * and backward_map has the reverse relationship.
     */

    $add_default_mappings = array();
    $forward_map = array();
    $backward_map = array();
    foreach ($Proj->metadata as $field_name => $field_info) {
    	
    	// Checking for action tags.
        if (empty($field_info['misc'])) {
            continue;
        }

        $default_value=Form::getValueInQuotesActionTag($field_info['misc'],'@ADD_DEFAULT_ON_VISIBLE');
        if (empty($default_value)) {
            continue;
        }
        $branching_logic = $field_info['branching_logic'];
        preg_match_all("/\[([^\]]*)\]/", $branching_logic, $matches);

        $branch_array = array();
        foreach ($matches[1] as $mat1) {
        	$pos = strpos($mat1, '(');
        	if ($pos) {
        		$branch_array[] = substr($mat1, 0, $pos);
        	} else {
        		$branch_array[] = $mat1;
        	}
        }

        $backward_map[$field_name] = $branch_array;

        $options = array();
        if ($field_info['element_enum']) {
        	foreach (explode("\\n", $field_info['element_enum']) as $tuple) {
        		list($key, ) = explode(',', $tuple);
        		$options[] = trim($key);
        	}
        }

        if ($field_info['element_type'] == 'checkbox') {
        	$field_name = '__chkn__' . $field_name;
        }

 		$add_default_mappings[$field_name] = array(
			'id' => $field_name . '-tr',
			'selector' => ($field_info['element_type'] == 'select' ? 'select' : 'input') . '[name="' . $field_name . '"]',
            'element_type' => $field_info['element_type'],
            'options' => json_encode($options),
            'branching_logic' => $field_info['branching_logic'],
            'default_value' => $default_value
        );
	}

	foreach ($backward_map as $key => $value) {
		foreach ($value as $subkey => $sub_value) {
			if (array_key_exists($sub_value, $forward_map)) {
				$forward_map[$sub_value][] = $key;
			} else {
				$forward_map[$sub_value] = array();
				$forward_map[$sub_value][] = $key;
			}
		}
	}

	if (empty($add_default_mappings)) {
        // If no mappings, there is no reason to proceed.
        return;
    }

    ?>

    <script type="text/javascript">
    	
    	$(document).ready(function () {
			var add_default_mappings = <?php print json_encode($add_default_mappings); ?>;

			//this method is used to set value to fields.
    		function setValue (mapping, field_name, selector, value) {
    			var elem_type = mapping['element_type'];
    			if (elem_type == 'checkbox') {
    				var arrValue = value.trim().split(',');
	    			var arr = JSON.parse(mapping['options']);
	    			for (var i = 0; i < arr.length; i++) {
	    				var index = arr[i];
	    				var selector1 = selector + '[code="' + index + '"]';
	    				console.log(selector1);
	    				if (arrValue.includes(index)) {
	    					$(selector1).click();
	    				} else {
	    					$(selector1).prop('checked', false);
	    					$(selector1).siblings('input').val('');
	    				}
	    			}
	    		} else if (elem_type == 'select') {
	    			//ToDo

	    		} else if (elem_type == 'radio') {
	    			//ToDo
	    			
	    		} else {
	    			$(selector).val(value);
	    		}
    		}

    		var backward_map = <?php print json_encode($backward_map)?>;
    		var forward_map = <?php print json_encode($forward_map)?>;


			// add an event listener for all the fields which can hide a field.
			for (var key in forward_map) {
				$("#"+key+"-tr").change(function () {

					// this map is used to store the state of the field before making all of them empty;
					aux = {};

					// add the info to aux map and then remove all the values.
					var children = forward_map[key];
					for (var i = 0; i < children.length; i++) {
						var child = children[i];
						var field_name = (typeof add_default_mappings[child] !== 'undefined') ? child : '__chkn__' + child;
						var $elem = $(add_default_mappings[field_name]['selector']);
		    			aux[field_name] = {'visible': $elem.is(':visible'), 'value': $elem.val()};
		    			var elem_type = add_default_mappings[field_name]['element_type'];

		    			if (elem_type == 'checkbox') {
		    				var arr = JSON.parse(add_default_mappings[field_name]['options']);
		    				for (var i = 0; i < arr.length; i++) {
		    					var index = arr[i];
			    				var selector1 = add_default_mappings[field_name]['selector'] + '[code="' + index + '"]';
			    				console.log(selector1);
			    				$(selector1).prop('checked', false);
			    				$(selector1).siblings('input').val('');
		    				}
		    			} else if (elem_type == 'radio') {
		    				//ToDo

		    			} else if (elem_type == 'select') {
							//ToDo

		    			} else {
		    				$elem.val('');
		    			}
					}

					calculate();
	    			doBranching();

	    			for (var i = 0; i < children.length; i++) {
						var child = children[i];
						var field_name = (typeof add_default_mappings[child] !== 'undefined') ? child : '__chkn__' + child;
						var mapping = add_default_mappings[field_name];
						var selector = mapping['selector'];
						var def_value = mapping['default_value'];

						$elem.val(aux[field_name]['value']);

						if ($(this).prop('name') == field_name) continue;
						
						if ($("#"+field_name.replace('__chkn__', '')+"-tr").is(":visible")) {
							if (!aux[field_name]['visible']) {
								setValue(mapping, field_name, selector, def_value);
							}
						}
						else if (aux[field_name]['visible']) {
							setValue(mapping, field_name, selector, '');
						}
					}
					calculate();
	    			doBranching();
				});
			}
			
    	});

    </script>
    <?php
}

?>