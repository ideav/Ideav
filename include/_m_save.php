<?php
# Get the Object's ID value and Type
$result = Exec_sql("SELECT a.val, a.t typ, a.up, a.ord, typs.t, tail.up tail
					FROM $z typs, $z a LEFT JOIN $z tail ON tail.up=a.id AND tail.t=0 WHERE typs.id=a.t AND a.id=$id"
				, "Get current Val and Type");
if($row = mysqli_fetch_array($result))
	$cur_val = $row["val"];
else
	exit("No such record");
if($row["up"]==0)
	exit("Cannot update meta-data");
$typ = $row["typ"];
$base_typ = $GLOBALS["basics"][$row["t"]];
$up = $row["up"];
if($up > 1)
	$arg = "F_U=$up";  # Retain this for Array elements only
if($row["tail"] == $id) # Check if we have tails here
	$cur_val = Get_tail($cur_val);
$GLOBALS["REV_BT"][$typ] = $GLOBALS["REV_BT"][$row["t"]];

$search_str = "";
foreach($_REQUEST as $key => $value) # Check if we have some search criteria for Ref lists
	if((substr($key, 0, 7) == "SEARCH_") && (strlen($value)))
		if($value != $_REQUEST["PREV_$key"])	# we have this updated
		{
			$search[substr($key, 7)] = $value;
			$search_str .= "&$key=$value"; # Pass the criteria via GET - prepare the address string
		}
#print_r($GLOBALS); die();
if(isset($_REQUEST["copybtn"])) # Check if we have to make a copy of the Obj
{
	Check_Grant($id);
	$copy = TRUE;
	if($up > 1)
		$ord = Calc_Order($up, $typ);
	else
		$ord = 1;
	# Copy the object and replace its ID
	$old_id = $id;
	if(strlen($_REQUEST["t$typ"]))	# Get the object Val from the form - this might be updated
		$GLOBALS["REQS"][$typ] = $cur_val = $_REQUEST["t$typ"];	# and update the current Val
	$id = Insert($up, $ord, $typ, $cur_val, "Copy the object");
	Populate_Reqs($old_id, $id);	# Populate requisites' reqs
	Insert_batch("", "", "", "", "Flush Copy");
	if(isset($GLOBALS["BOOLEANS"]))  # Clean the previous set of reqs in case we copy the Obj
		unset($GLOBALS["BOOLEANS"]);
    $arg = "copied1&";
}
else
{
    $arg = "saved1&";
	$copy = FALSE;
}

$GLOBALS["REQ_TYPS"][$typ] = $id;
Get_Current_Values($id, $typ);
$GLOBALS["REQS"][$typ] = $cur_val;
#print_r($GLOBALS); die();

foreach($_REQUEST as $key => $value)
{
	$t = substr($key, 1); # Cut the Typ from the Var named tTyp
	if((substr($key, 0, 1) != "t") || ($t == 0)) # Out of scope or empty
		continue;
	$req_id = isset($GLOBALS["REQ_TYPS"][$t]) ? $GLOBALS["REQ_TYPS"][$t] : 0; # Current Requisite's ID
	
	# Format the value
	if(!isset($GLOBALS["REF_typs"][$t]))
	    $v = Format_Val($t, $value);
	else
	    $v = $value;

	if(isset($_REQUEST["NEW_$t"]) && isset($GLOBALS["REF_typs"][$t]))
		if(strlen($_REQUEST["NEW_$t"])) # Check if we got a new Object to create for reference
		{
			$value = $_REQUEST["NEW_$t"];
			if($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='".addslashes($_REQUEST["NEW_$t"])
													."' AND t=".$GLOBALS["REF_typs"][$t], "Check if the Ref exists")))
				$v = $row["id"];
			elseif(Grant_1level($GLOBALS["REF_typs"][$t]) == "WRITE")
				$v = Insert(1, 1, $GLOBALS["REF_typs"][$t], addslashes($_REQUEST["NEW_$t"]), "Insert the new Ref Obj");
			else
				die(t9n("[RU]У вас нет прав на создание объектов этого типа (".$GLOBALS["REF_typs"][$t].").[EN]You don't have permission to create (".$GLOBALS["REF_typs"][$t].") type of object."));
		}
	# Check if the user tries to set a read-only Object in a report
    if($base_typ == "REPORT_COLUMN")
		if(($t == REP_COL_SET) || ($t == $typ))
		    if((strlen($_REQUEST["t".REP_COL_SET]) != 0))
                if(($_REQUEST["t$typ"] !== $GLOBALS["REQS"][$typ]) || ($_REQUEST["t".REP_COL_SET] != $GLOBALS["REQS"][REP_COL_SET]))
				    CheckRepColGranted($_REQUEST["t$typ"], "WRITE");
    
	if($v != $GLOBALS["REQS"][$t])# Check if the value was modified
	{
		if($t == $typ)
		{
			Check_Grant($id); # Check the grant to change the Object
			# Check if the user tries to set a forbidden Object - barred in their role
		    if($base_typ == "REPORT_COLUMN")
		        CheckRepColGranted((int)$v);
		}
		elseif($t == REP_COL_SET)
		    CheckRepColGranted($cur_val, "WRITE");
		else
			Check_Grant($id, $t); # Check the grant to change the Req
		if(strlen($value) != 0)  # Non empty Value
		{
			if(isset($GLOBALS["REF_typs"][$t]))
			{
				if($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=".(int)$v." AND t=".$GLOBALS["REF_typs"][$t], "Check Ref's Type")))
				{
					Check_Val_granted($GLOBALS["REF_typs"][$t], $row["val"]);
					if($req_id == 0)
						Insert($id, 1, $v, "$t", "Insert new Ref"); # A new Value
					else
						Exec_sql("UPDATE $z SET t=$v WHERE id=$req_id", "Update Reference");
					if(isset($search[$t]))
						unset($search[$t]); # Clean the search criteria to leave the form
				}
				else
					my_die(t9n("[RU]Неверный тип объекта с ID=$v или объект не найден[EN]Invalid object type with ID=$v or the object was not found"));
			}
			else
			{
				if($req_id == 0)
					Insert($id, 1, $t, $v, "Insert new non-empty rec");
				else
					Update_Val($req_id, $v);
			}
		}
		else # The Value was cleared
		{
		    if($req_id == 0)
				$GLOBALS["warning"] .= t9n("[RU]Пустой тип реквизита![EN]Empty attribute type")."<br>";
			elseif($t != $typ) # We cannot clear the Object's Val (just skip the action)
				Exec_sql("DELETE FROM $z WHERE id=$req_id OR up=$req_id", "Delete Empty Obj");
			else
				$GLOBALS["warning"] .= t9n("[RU]Нельзя оставить пустым имя объекта![EN]Object name cannot be blank!")."<br>";
		}
	}
	if($GLOBALS["REV_BT"][$t] == "BOOLEAN")  # Forget the processed boolean Req
		unset($GLOBALS["BOOLEANS"][$t]);
}
#print_r($GLOBALS); die();
# Drop all empty Logical Object's Reqs (those not confirmed by the edit form)
if(isset($GLOBALS["BOOLEANS"]))
	foreach($GLOBALS["BOOLEANS"] as $key => $value)
		if(isset($_REQUEST["b$key"]))	# The Req wasn't present in the edit form
			if(Check_Grant($id, $key, "WRITE", FALSE))	# Don't stop in case the user has no Grant - we won't change anything
				Exec_sql("DELETE FROM $z WHERE id=".$GLOBALS["REQ_TYPS"][$key], "Clear empty boolean Reqs");
foreach($_FILES as $key => $value)
	if(strlen($value["name"]) > 0)
	{
		$t = substr($key, 1); # Cut the Typ from the Var named tTyp
		if((substr($key, 0, 1) != "t") || ($t == 0)) # Out of scope
			continue;

		if(Check_Grant($id, $t))
		{
			BlackList(substr(strrchr($value["name"], '.'), 1));
			if(!file_exists(UPLOAD_DIR))
				mkdir(UPLOAD_DIR);

			$req_id = $GLOBALS["REQ_TYPS"][$t]; # Current Requisite's ID
			if($req_id == 0)  # The filename was empty
				$req_id = Insert($id, 1, $t, $value["name"], "Insert new Filename");
			else
				Update_Val($req_id, $value["name"]); # update the filename in the DB

			$subdir = GetSubdir($req_id);
			if(!file_exists($subdir))
				@mkdir($subdir);
			if(!move_uploaded_file($value['tmp_name']
								, $subdir."/".GetFilename($req_id).".".substr(strrchr($value["name"],'.'),1)))
				die (t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
		}
	}
#print_r($GLOBALS); die();
if(isset($search))  # We're searching the dropdown list
{
	$a = "edit_obj";
	$arg = str_replace("%", "%25", $search_str);
	break;
}
# Check, if there are NOT NULL reqs not filled in, and stay in Edit mode, if any
if(isset($GLOBALS["NOT_NULL"]))
	foreach($GLOBALS["NOT_NULL"] as $key => $value)
		if(Check_Grant($typ, $key, "WRITE", FALSE)) # The object is NOT_NULL and we have the grant to change it
		{
			if((isset($_REQUEST["t$key"]) ? strlen($_REQUEST["t$key"]) : FALSE)
			  || (isset($_REQUEST["NEW_$key"]) ? strlen($_REQUEST["NEW_$key"]) : FALSE)
			  || (isset($GLOBALS["ARR_typs"][$key]) && ($GLOBALS["REQS"][$key] != 0))
			  || isset($_REQUEST["copybtn"]))
				continue;
			if(!isset($GLOBALS["warning"]))
			    $GLOBALS["warning"] = "";
			$GLOBALS["warning"] .= t9n("[RU]Необходимо заполнить реквизиты, выделенные красным[EN]The attributes marked red are mandatory")."!<br>";
		    $next_act = ""; # Prevent redirection in case something is missing
			
			break;
		}
if(isset($GLOBALS["warning"])) # In case we got warnings - stay in Edit mode
{
	$a = "edit_obj";
	$arg .= (isset($_REQUEST["tab"]) ? "tab=".(int)$_REQUEST["tab"] : "")."&warning=".$GLOBALS["warning"];
	$obj = $id;
}
else
{
	$arg .= "F_U=$up&F_I=$id";
	$a = "object"; # Show this Object after we finish editing it
	$obj = $id;
	$id = $typ;
}
?>
