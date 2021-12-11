<?php
if($up == 0)
	my_die(t9n("[RU]Недопустимые данные: up=0. Установите значение=1 для независимых объектов.[EN]Data is invalid: up=0. Set up=1 for independent objects."));
# Check if the Type exists and has reqs
$data_set = Exec_sql("SELECT obj.t, obj.ord, req.id FROM $z obj LEFT JOIN $z req ON req.up=$id WHERE obj.id=$id AND obj.up=0", "Check Obj type&reqs");
if($row = mysqli_fetch_array($data_set))
{
	$val = isset($_REQUEST["t$id"]) ? $_REQUEST["t$id"] : "";
	
    if(($GLOBALS["basics"][$row["t"]] == "REPORT_COLUMN") && ((int)$val != 0))
        CheckRepColGranted($val);
	$base_typ = $row["t"];
	$has_reqs = strlen($row["id"]);
	$unique = $row["ord"];	# Ord=1 means the Obj must be unique
	# Calc the order
	$ord = 1;
	if($up != 1)
	{
		if($row = mysqli_fetch_array(Exec_sql("SELECT up FROM $z WHERE id=$up", "Check the object ")))
		{
			if($row[0] == 0)
			    my_die(t9n("[RU]Родительский объект $up - метаданные.[EN] The parent object $up is metadata."));
		}
		else
		    my_die(t9n("[RU]Родительский объект $up не найден.[EN]The parent object $up not found."));
		Check_Grant($up, $id);
		$ord = Calc_Order($up, $id);
	}
	elseif(Grant_1level($id) != "WRITE")
		die(t9n("[RU]У вас нет прав на создание объектов этого типа.[EN]You don't have permission to create this type of object"));
	# Calc the default value
	if($val == "")
	{
		if($GLOBALS["REV_BT"][$base_typ] == "NUMBER")
		{	# For a numeric Obj find the maximum Val (if there are no non-empty reqs) and use its ID
			$data_set = Exec_sql("SELECT MAX(CAST(val AS UNSIGNED)) val FROM $z WHERE t=$id AND up=$up", "Get max Val of numeric Obj");
			$max_val = 0;
			if($row = mysqli_fetch_array($data_set))
				if($row[0] > 0)
					$max_val = $row[0];

			$data_set = Exec_sql("SELECT id FROM $z obj WHERE t=$id AND val=$max_val AND up=$up
									AND NOT EXISTS(SELECT * FROM $z reqs WHERE up=obj.id)", "Get 'empty' numeric Obj");
			if($row = mysqli_fetch_array($data_set))
			{
				$id = $row[0];	# Get the first empty object and go Editing it
				$a= "edit_obj";
				break;
			}
			else
				$val = $max_val + 1;
		}
		elseif($GLOBALS["REV_BT"][$base_typ] == "DATE") # Default Date is Today
			$val = Format_Val($base_typ, date("d", time() + $GLOBALS["tzone"]));
		elseif($GLOBALS["REV_BT"][$base_typ] == "DATETIME") # Default Datetime is Now
			$val = time();
		elseif($GLOBALS["REV_BT"][$base_typ] == "SIGNED") # Default number is 1
			$val = 1;
		else	# Set the Order instead of the empty Value
			$val = $ord;
	}
	else
		$val = Format_Val($base_typ, $val);
	# The Type must be unique - let's check this
	if($unique && !isset($max_val))
		if($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$id AND val='".addslashes($val)."' AND up=$up LIMIT 1", "Check Obj's uniquity")))
			if(strlen($row[0]))
				die("<b>".Format_Val_View($base_typ, $val)."</b> ".t9n("[RU]уже существует! Перейти к[EN]already exists. Go to")
				    ." <a href=\"/$z/edit_obj/".$row[0]."\">".Format_Val_View($base_typ, $val)."</>");
}
else
	die(t9n("[RU]Проверка типа неуспешна[EN]Type check failed"));
$i = Insert($up, $ord, $id, $val, "Add Object");
#print_r($GLOBALS); die();
# Now insert all the reqs, that might be submitted
foreach($_REQUEST as $key => $value)
if($key != "t$id") // Skip the object itself
{
	$t = substr($key, 1); # Cut the Typ from the Var named tTyp
	if((substr($key, 0, 1) != "t") || ($t == 0)) # Out of scope
	{
		if(substr($key, 0, 7) == "SEARCH_")	# Pass the Req list filter in case we got one
			$arg .= "$key=$value&";
		continue;
	}
	if(!isset($GLOBALS["REQ_TYPS"][$t]))
		Get_Current_Values($i, $id);
	$v = Format_Val($t, BuiltIn($value));

	Check_Grant($i, $t); # Check the grant to change the Req
	if(strlen($value) != 0)  # Non empty Value
	{
		if(isset($GLOBALS["REF_typs"][$t]))
		{
			if((int)$v == 0)
				my_die(t9n("[RU]Неверный тип объекта ID=$v [EN]Invalid object type ID=$v"));
			$v = (int)$v;
			if($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=$v AND t=".$GLOBALS["REF_typs"][$t], "Check Ref's req Type")))
			{
				Check_Val_granted($GLOBALS["REF_typs"][$t], $row["val"]);
				Insert_batch($i, 1, $v, "$t", "Insert new Ref req");
			}
			else
				my_die(t9n("[RU]Неверный тип объекта с ID=$v или объект не найден [EN]Invalid object type with ID=$v or the object was not found"));
		}
		elseif($t == PASSWORD)
			Insert_batch($i, 1, $t, sha1(Salt($val, $v)), "Insert a first time password");
		else
			Insert_batch($i, 1, $t, $v, "Insert new non-empty req");
	}
}
Insert_batch("", "", "", "", "Flush reqs of the new");
#mywrite($GLOBALS["TRACE"]);
# Upload the files
foreach($_FILES as $key => $value)
	if(strlen($value["name"]) > 0)
	{
		$t = substr($key, 1); # Cut the Typ from the Var named tTyp
		if((substr($key, 0, 1) != "t") || ($t == 0)) # Out of scope
			continue;
		if(Check_Grant($i, $t))
		{
			BlackList(substr(strrchr($value["name"], '.'), 1));
			$req_id = Insert($i, 1, $t, $value["name"], "Insert new Filename");
			if(!file_exists(UPLOAD_DIR))
				mkdir(UPLOAD_DIR);
			$subdir = GetSubdir($req_id);
			if(!file_exists($subdir))
				@mkdir($subdir);
			if(!move_uploaded_file($value['tmp_name']
								, $subdir."/".GetFilename($req_id).".".substr(strrchr($value["name"],'.'),1)))
				die (t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
		}
	}
if($has_reqs) # If the Typ has any Reqs - call the Object editor
{
    $a = "edit_obj";
	$id = $i;
	$arg = "new1&$arg";
}
else
{
	$a = "object";
	if($up != 1)
		$arg = "F_U=$up";  # Retain this for Array elements only
}
$obj=$i;
?>
