<?php
class  Pt_Helper_View_DropdownSelectedText extends Zend_View_Helper_Abstract {
	
	public function dropdownSelectedText( $allRecord, $selection= ""){
		foreach ($allRecord as $key=> $value){
			if($selection == $key) { 
				echo $value;
			}
		}
	}
}