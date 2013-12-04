<?php
class  Pt_Helper_View_DropdownSelection extends Zend_View_Helper_Abstract {
	
	public function dropdownSelection( $allRecord, $selection= "", $ShowEmpty =false){
		//	$allRecord = get_usertype_list();
		if ($ShowEmpty == true){
			echo "<option value=''>--Select--</option>";
		}
		foreach ($allRecord as $key=> $value){
			echo "<option value=" . $key ;
			if($selection == $key) echo " selected ";
			echo ">".$value."</option>";
		}
	}
}