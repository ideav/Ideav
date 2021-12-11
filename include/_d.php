<?php
if((Check_Types_Grant() == "WRITE") && check())
	$next_act = $next_act=="" ? "edit_types" : $next_act;
else
	die(t9n("[RU]У вас нет прав на редактирование типов(".$GLOBALS["GRANTS"][0].").[EN]You don't have permission to edit types (".$GLOBALS["GRANTS"][0].")."));
switch ($a)
{
# Type editor commands
	case "_d_req":
		if(($id == 0) || ($t == 0))
			my_die(t9n("[RU]Неверный реквизит ($t) или ID ($id)[EN] Invalid requisite($t) or ID ($id)"));
		if($row = mysqli_fetch_array(Exec_sql("SELECT $z.up objup, new.t, $z.val, new.id, new.up FROM $z
		                                        LEFT JOIN $z new ON new.id=$t
								                WHERE $z.id=$id"
						                , "Check the new req")))
		{
			if(($row["id"] == 0) || ($row["up"] != 0))
				my_die(t9n("[RU]Неверный реквизит $t [EN]Invalid requisite($t)"));
			if($row["objup"] != 0)
				my_die(t9n("[RU]Некорректный тип $id - ".$row["val"]." (это не метаданные)[EN]"));
			if($row["t"] == $t)
				my_die(t9n("[RU]Некорректный тип $t - это базовый тип[EN]Invalid type $t is the base type"));
		}
		else
			my_die(t9n("[RU]Не найден тип $id [EN]$id type not found"));
		Insert($id, Get_Ord($id), $t, "", "Add Req");
		$obj=$id;
		break;
		
	case "_d_save":
		if($val == "")
			my_die(t9n("[RU]Неверный тип ($val) [EN]Invalid type ($val)"));
		if($row = mysqli_fetch_array(Exec_sql("SELECT $z.t, $z.val, $z.ord FROM $z 
								LEFT JOIN $z dup ON dup.id!=$id AND dup.val='".addslashes($val)
							."' AND dup.t=$t WHERE $z.id=$id AND dup.id IS NULL", "Get Object and check duplicates")))
		{
			if(($row["t"] != 0) && ($t == 0))
				my_die(t9n("[RU]Неверный базовый тип ($t) [EN]Invalid base type ($t)"));
			if(($row["t"] != $t) || ($row["val"] != $val) || ($row["ord"] != $unique))
				Exec_sql("UPDATE $z SET t=$t, val='".addslashes($val)."', ord='$unique' WHERE id=$id", "Change typ");
		}
		else
			my_die(t9n("[RU]Тип $val с базовым типом ".$GLOBALS["REV_BT"][$t]." уже существует. [EN]The $val type with the base type ". $GLOBALS ["REV_BT"][$t]. " already exists."));
		$obj=$id;
		break;

	case "_d_alias":
		if(strpos($val, ":") !== false)
			my_die(t9n("[RU]Недопустимый символ &laquo;:&raquo; в псевдониме $val [EN] Invalid character &laquo;:&raquo; in the alias $val"));
		if($row = mysqli_fetch_array(Exec_sql("SELECT $z.val, par.up, $z.up myup FROM $z, $z par WHERE $z.id=$id AND par.id=$z.t", "Get Ref alias")))
		{
			if($row["up"] != 0)
				my_die(t9n("[RU]Ошибка подчиненности объекта ссылки [EN]Error in subordination of the link object"));
			$up = $row["myup"];
			$alias = explode(ALIAS_DEF, $row["val"]);
			if(isset($alias[1])) # $alias[1] is OldAlias::bla-bla...
			{
				if(mb_strlen($alias[1]) > mb_strpos($alias[1],":")+1)
					$alias[1] = mb_substr($alias[1],mb_strpos($alias[1],":")+1);
				else
					$alias[1] = "";
				if($val != "")
					$alias[1] = ALIAS_DEF.$val.":".$alias[1];
				Exec_sql("UPDATE $z SET val='".implode($alias)."' WHERE id=$id", "Update alias");
			}
			elseif($val != "")
				Exec_sql("UPDATE $z SET val=CONCAT(val,'".ALIAS_DEF.addslashes($val).":') WHERE id=$id", "Set alias");
		}
		else
			my_die(t9n("[RU]Тип $id не найден [EN]Type $id not found"));
		$id = $obj = $up;
		break;

	case "_d_new":
		if($val == "")
			my_die(t9n("[RU]Пустой тип[EN]Empty type"));
		if(!isset($_REQUEST["t"]))
			my_die(t9n("[RU]Не задан базовый тип[EN]Base type is not set"));
		if($_REQUEST["t"]=="")
			my_die(t9n("[RU]Не задан базовый тип[EN]Base type is not set"));
		if(!$row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='".addslashes($val)."' AND t=$t AND id!=t", "Check Typ presence")))
			$obj = Insert(0, $unique, $t, $val, "Create Typ");
		else
			my_die(t9n("[RU]Тип $val уже существует![EN]The Type $val already exists!"));
#print($GLOBALS["TRACE"]); die($id);
		break;

	case "_d_ref":
		if($id == 0)
			my_die(t9n("[RU]Неверная ссылка ($id) [EN]Invalid link ($id)"));
		if($row = mysqli_fetch_array(Exec_sql("SELECT up, t FROM $z WHERE id=$id", "Check the new ref")))
		{
			if(($row["up"] != 0) || ($row["t"] == $id))
				my_die(t9n("[RU]Неверный тип $id - [EN]Invalid $id type -".$row["val"]));
		}
		else
			my_die(t9n("[RU]Не найден тип $id [EN]$Id type not found"));
		$obj = Insert(0, 0, $id, "", "Create Ref");
		break;

	case "_d_null":
	case "_d_not_null":
		$result = Exec_sql("SELECT obj.id FROM $z req LEFT JOIN $z obj ON obj.id=req.up WHERE req.id=$id and obj.up=0"
						, "Check the req and obj");
		if($row = mysqli_fetch_array($result))
			Exec_sql("UPDATE $z SET val=CASE WHEN val LIKE '%".NOT_NULL_MASK."%' THEN REPLACE(val, '".NOT_NULL_MASK
									."', '') ELSE CONCAT(val, '".NOT_NULL_MASK."') END WHERE id=$id", "Switch NULL-able");
		else
		    my_die(t9n("[RU]Неверный реквизит $id [EN]Invalid requisite $id "));
		$id = $obj = $row["id"];
		break;

	case "_d_attrs":
		if(isset($_REQUEST["alias"])) # Append alias, if it's set
			if(strlen($_REQUEST["alias"]))
				$val = ALIAS_DEF.$_REQUEST["alias"].":$val";
		if(isset($_REQUEST["set_null"])) # Append NOT_NULL_MASK
			$val = NOT_NULL_MASK.$val;
		Update_Val($id, $val);
		$id = $obj = $up;
		break;

	case "_d_up":
		$result = Exec_sql("SELECT obj.up, obj.ord, max(peers.ord) new_ord
							FROM $z obj LEFT JOIN $z peers ON peers.up=obj.up AND peers.ord<obj.ord WHERE obj.id=$id"
						, "Get new Order");
		if($row = mysqli_fetch_array($result))
		{
			$id = $row["up"];
			if($row["new_ord"] > 0)
				Exec_sql("UPDATE $z SET ord=(CASE WHEN ord=".$row["ord"]." THEN ".$row["new_ord"]
											." WHEN ord=".$row["new_ord"]." THEN ".$row["ord"]
						." END) WHERE up=$id AND (ord=".$row["ord"]." OR ord=".$row["new_ord"].")", "Change order");
			$obj=$id;
		}
		else
		    my_die(t9n("[RU]Не найден id=$id [EN] Id=$id not found"));
		break;

	case "_d_del":
		$data_set = Exec_sql("SELECT COUNT(id) FROM $z WHERE t=$id", "Check, if the Typ is being used");
		if($row = mysqli_fetch_array($data_set))
			if($row[0] > 0)
				die(t9n("[RU]Нельзя удалить тип при наличии его экземпляров (всего: "
				    ."[EN]Cannot delete the Type in case there are objects of this type (total objects: ").$row[0].")!");
		$sql = "SELECT reqs.id FROM $z, $z reqs WHERE $z.t=".REP_COLS." AND $z.val=reqs.id AND (reqs.up=$id OR reqs.id=$id) LIMIT 1";
		if($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Reqs are being used in Reports")))
			my_die (t9n("[RU]Тип или его реквизиты используются в <a href=\"/$z/object/".REPORT."/?F_".REP_COLS."=".$row["id"]."\">отчетах</a>"
			        ."[EN]The type or its requisites are used in <a href=\"/$z/object/".REPORT."/?F_".REP_COLS."=".$row["id"]."\">reports</a>"));
		$sql = "SELECT objs.t, objs.val FROM $z, $z r, $z objs WHERE r.t=".ROLE." AND r.up=1 AND objs.up=r.id AND objs.val=$z.id AND ($z.up=$id OR $z.id=$id) LIMIT 1";
		if($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Reqs are being used in Roles")))
			die(t9n("[RU]Тип или его реквизиты используются в <a href=\"/$z/object/".ROLE."/?F_".$row["t"]."=".$row["val"]."\">ролях</a>!)
			    .[EN]The type or its requisites are used in <a href=\"/$z/object/".ROLE."/?F_".$row["t"]."=".$row["val"]."\">roles</a>!"));
#print_r($GLOBALS); die($sql);
		Delete($id);
		break;

	case "_d_del_req":
		$data_set = Exec_sql("SELECT def.up, def.t typ, def.ord, r.t, r.val FROM $z def, $z r WHERE def.id=$id AND r.id=def.t", "Get Req's typ");
		if($row = mysqli_fetch_array($data_set))
		{
			$myord = $row["ord"]; # Save Up and Ord to move other reqs up
			$myup = $row["up"];
			if(isset($GLOBALS["basics"][$row["t"]]))	# It's not a reference
				$sql = "SELECT count(1) FROM $z obj, $z req WHERE obj.t=$myup AND (req.t=".$row["typ"]." OR req.t=$id) AND req.up=obj.id";
			else
				$sql = "SELECT count(1) FROM $z obj, $z req WHERE obj.t=$myup AND req.up=obj.id AND req.val='$id'";
			if($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Req is being used")))
			{
				if($row[0] > 0)
					my_die(t9n("[RU]Нельзя удалить реквизит у типа при наличии этого реквизита у экземпляров (всего: "
					        ."[EN]Cannot delete a requisite if there are records of this type (total records: ").$row[0].")!");
				$sql = "SELECT ".REP_COLS." t FROM $z WHERE t=".REP_COLS." AND val='$id' "
						."UNION SELECT reqs.t FROM $z, $z reqs WHERE $z.t=".ROLE." AND $z.up=1 AND reqs.up=$z.id AND reqs.val='$id' LIMIT 1";
				if($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Req is being used in Reports or Roles")))
					my_die(t9n("[RU]Этот реквизит используется в <a href=\"/$z/object/".REPORT."/?F_".REP_COLS
							."=$id\">отчетах</a> или <a href=\"/$z/object/".ROLE."/?F_116=$id\">ролях</a>!"
						."[EN]The requisite is used in <a href=\"/$z/object/".REPORT."/?F_".REP_COLS
       						."=$id\">reports</a> or <a href=\"/$z/object/".ROLE."/?F_116=$id\">roles</a>!"));
				Delete($id);
				Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=$myup AND ord>$myord", "Move up other Reqs");
				$id = $obj = $myup;
			}
		}
		break;
}
?>
