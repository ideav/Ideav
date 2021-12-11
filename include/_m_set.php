<?php
$t = 0;
foreach($_REQUEST as $key => $val) # Check if we have an attribute set
	if((substr($key, 0, 1) == "t") && ((int)substr($key, 1)!=0))
	{
	    $t = (int)substr($key, 1);
		Check_Grant($id, $t);
		# Get the Object's ID value and Type
		$result = Exec_sql("SELECT a.id, a.val, def.t FROM $z obj JOIN $z req ON req.up=obj.t AND req.id=$t JOIN $z def ON def.id=req.t
								LEFT JOIN $z a ON a.up=$id AND (a.t=$t OR a.val='$t') WHERE obj.id=$id", "Get Attr Type");
#print_r($GLOBALS);die($id);
		if($row = mysqli_fetch_array($result))
		{
			$cur_val = $row["val"];
			$cur_id = $row["id"];
			if(!isset($GLOBALS["basics"][$row["t"]]))	# Reference - its type is not a base one
			{
				$val = (int)$val;
				if($val)
					if(!($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE id=$val AND t=".$row["t"], "Check new Ref"))))
						die(t9n("[RU]Неверная ссылка $val :[EN]Wrong Reference $val : ".$row["t"]));
				if($cur_id)
				{
					if($val)
					{
						if($val != $cur_val)
							Exec_sql("UPDATE $z SET t=$val WHERE id=$cur_id", "Update Reference Attr");
					}
					else
						Delete($row["id"]);
				}
				elseif($val)
					Insert($id, 1, $val, "$t", "Insert new Ref Attr"); # A new Value
			}
			else
			{
				//$val = BuiltIn($val);
				# Format the value
				if(($GLOBALS["REV_BT"][$row["t"]] == "NUMBER") && ($val != 0))
					$val = (int)$val;
				elseif(($GLOBALS["REV_BT"][$row["t"]] == "SIGNED") && ($val != 0))
					$val = (double)$val;
				else
					$val = Format_Val($row["t"], $val);
				if($cur_id) # The Req exists
				{
					if($val=="")
						Delete($cur_id);
					else
					{
						if(in_array($GLOBALS["REV_BT"][$row["t"]], array("CHARS", "MEMO", "FILE", "HTML")))
							$cur_val = Get_tail($cur_id, $cur_val);
						else
							$val = Format_Val($row["t"], $val);
						if($val != $cur_val)
							Update_Val($cur_id, $val);
					}
				}
				elseif($val!="")
					Insert($id, 1, $t, $val, "Insert new non-empty rec");
			}
		}
	}
if($t == 0)
    die(t9n("[RU]Нет набора атрибутов ($key) или пустое значение ($val) [EN]No attribute set ($key) or empty value ($val)"));
$a = "nul";
?>
