<?php
# Check Req val granted by mask
function Val_barred_by_mask($t, $val)
{
	if(isset($GLOBALS["GRANTS"]["masklevel"][$t]))	# Mask grants with level definition has a higher priority
	{
	    trace("Mask set for $t");
		foreach($GLOBALS["GRANTS"]["masklevel"][$t] as $grant => $mask)
		{
    	    trace(" Mask: $grant => $mask");
			$mask = Fetch_WHERE_for_mask($t, $val, $mask);
			if($row = mysqli_fetch_array(Exec_sql("SELECT $mask", "Apply mask")))
				if($row[0])
					return ($grant != "WRITE");	# Invert what's granted
		}
		if($grant == "WRITE")
			return TRUE;
	}
	return FALSE;
}
# Human-friendly file size
function NormalSize($size)
{
   if($size < 1024)
       return $size." B";
   elseif($size < 1048576)
       return round($size/1024, 2)." KB";
   elseif($size < 1073741824)
       return round($size/1048576, 2)." MB";
   elseif($size < 1099511627776)
       return round($size/1073741824, 2)." GB";
   else
       return round($size/1099511627776, 2)." TB";
}
function MaskDelimiters($v)
{
    return str_replace(";", "\;", str_replace(":", "\:", str_replace("\\", "\\\\", $v)));
}
function UnMaskDelimiters($v)
{
    return str_replace("\;", ";", str_replace("\:", ":", str_replace("\\\\", "\\", UnHideDelimiters($v))));
}
function HideDelimiters($v)
{
    return str_replace("\;", "%3B", str_replace("\:", "%3A", str_replace("\\\\", "%5C", $v)));
}
function UnHideDelimiters($v)
{
    return str_replace("%3B", "\;", str_replace("%3A", "\:", str_replace("%5C", "\\\\", $v)));
}
function Get_Align($typ)
{
	switch($GLOBALS["REV_BT"][$typ])
	{
		case "PWD":
		case "DATE":
		case "BOOLEAN":
			return "CENTER";
		case "NUMBER":
		case "SIGNED":
			return "RIGHT";
	}
	return "LEFT";
}
function CheckSubst($i)
{
    if(isset($GLOBALS["local_struct"]["subst"]))
        if(isset($GLOBALS["local_struct"]["subst"][$i]))
            return $GLOBALS["local_struct"]["subst"][$i];
    return $i;
}
function CheckObjSubst($i)
{
    if(isset($GLOBALS["obj_subst"]))
        if(isset($GLOBALS["obj_subst"][$i]))
            return $GLOBALS["obj_subst"][$i];
    return $i;
}
function IsOccupied($id)
{
	global $z;
	if($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE id=$id", "Check if ID is occupied")))
	    return true;
	return false;
}
function ResolveType($typ)
{
	global $z;
	$data_set = Exec_sql("SELECT id FROM $z WHERE val='".addslashes($typ[1])."' AND up=0 AND t=".$GLOBALS["BT"][$typ[2]], "Seek Typ");
	if($row = mysqli_fetch_array($data_set))
	{
	    $id = $row["id"];
	    Export_header($id);
	}
	else # No analogue, register the new one
	{
	    $id = Insert(0, (isset($typ[3])?"1":"0"), $GLOBALS["BT"][$typ[2]], $typ[1], "Insert type substitute");
    	$GLOBALS["local_struct"][$id][0] = "$id:".MaskDelimiters($typ[1]).":".$GLOBALS["BT"][$typ[2]].(isset($typ[3])?":unique":"");
	}
	if($id != $typ[0])
	{
    	$GLOBALS["local_struct"]["subst"][$typ[0]] = $id;
    	trace("Substitute for ".$typ[0]." - ".$typ[1]." is $id");
	}
	return $id;
}
function Export_header($id, $parent=0)
{
	global $z;
	if(!isset($GLOBALS["local_struct"][$id]))
	{
		$GLOBALS["parents"][$id] = $parent;
		$data_set = Exec_sql("SELECT CASE WHEN length(obj.val)=0 THEN obj.id ELSE obj.t END t, CASE WHEN length(obj.val)=0 THEN obj.t ELSE obj.val END val
		                            , req.id, req.t req_t, refr.val req, refr.t ref_t, req.val attr, base.t base_t, arr.id arr, linx.i, obj.ord uniq
								FROM $z obj LEFT JOIN ($z req CROSS JOIN $z refr CROSS JOIN $z base) ON req.up=obj.id AND refr.id=req.t AND base.id=refr.t
									LEFT JOIN $z arr ON arr.up=req.t AND arr.t!=0 AND arr.ord=1
									CROSS JOIN (SELECT count(1) i FROM $z WHERE up=0 AND t=$id) linx
								WHERE obj.id=$id ORDER BY req.ord", "Get Obj structure");
		while($row = mysqli_fetch_array($data_set))
		{
			if(!isset($GLOBALS["local_struct"][$id]))
			{
				$GLOBALS["local_struct"][$id][0] = "$id:".MaskDelimiters($row["val"]).(isset($GLOBALS["REV_BT"][$row["t"]])?":".$GLOBALS["REV_BT"][$row["t"]]:"")
				                            .($row["uniq"]=="1"?":unique":"");
	            $GLOBALS["base"][$id] = $row["t"];
				if($row["i"])	# We might have refs to this object, so we need to export its ID
					$GLOBALS["linx"][$id] = "";
			}
			if($row["req_t"])	# Do we have Reqs?
			{
			    $ord++;
				if($row["ref_t"] != $row["base_t"]) // This is a reference Req
				{
				    trace("add link: ".$row["id"]." -> ".$row["req_t"]);
					$GLOBALS["local_struct"][$id][$row["id"]] = "ref:".$row["id"].":".$row["req_t"].($row["attr"]?":".MaskDelimiters($row["attr"]):"");
					if(!isset($GLOBALS["local_struct"][$row["req_t"]])) # Export ref object
						Export_header($row["req_t"], $id);
					if(!isset($GLOBALS["local_struct"][$row["ref_t"]]))
						Export_header($row["ref_t"], $id);
		            $GLOBALS["refs"][$row["id"]] = $row["ref_t"];
				}
				elseif($row["arr"])
				{
					$GLOBALS["local_struct"][$id][$row["id"]] = "arr:".$row["req_t"].($row["attr"]?":".MaskDelimiters($row["attr"]):"");
					if(!isset($GLOBALS["local_struct"][$row["req_t"]]))
					{
						Export_header($row["req_t"], $id);
						$GLOBALS["arrays"][$row["req_t"]] = "";
					}
				}
				else
				{
					$GLOBALS["local_struct"][$id][$row["id"]] = MaskDelimiters($row["req"]).":".$GLOBALS["REV_BT"][$row["ref_t"]].($row["attr"]?":".MaskDelimiters($row["attr"]):"");
					if($GLOBALS["REV_BT"][$row["ref_t"]] == "PWD")	# Do not export PWD hashes
						$GLOBALS["pwds"][$row["id"]] = "";
		            $GLOBALS["base"][$row["id"]] = $row["base_t"];
				}
			}
		}
#		my_die();
	}
#{print_r($GLOBALS);die($id);}
    $head_str = "";
	if(is_array($GLOBALS["local_struct"]))
		foreach($GLOBALS["local_struct"] as $value)
			$head_str .= implode(";", $value).";\r\n";
	return $head_str;
}
function Export_reqs($id, $obj, $val, $ref="")
{
	global $z;
	$str = $children = $refs = "";
	if(!isset($GLOBALS["data"][$obj]))
	{
		$reqs = array();
		$data_set = Exec_sql("SELECT DISTINCT obj.id, obj.t, obj.val, req.t req_t, req.val req_val, req.up rup, tail.up, par.up ref
								FROM $z obj LEFT JOIN $z tail ON tail.t=0 AND tail.up=obj.id AND tail.ord=0
									LEFT JOIN $z req ON req.id=obj.t LEFT JOIN $z par ON par.id=req.up
								WHERE obj.up=$obj ORDER BY obj.ord"
							, "Get Obj data $id");
		while($row = mysqli_fetch_array($data_set))
			if(($row["rup"] != $id) && ($row["rup"] != 0))
			{
				$reqs[$row["val"]] = $row["t"];
				# Submit the Ref value in case it's a referenced object
				$refs .= Export_reqs($row["req_t"], $row["t"], MaskDelimiters($row["req_val"]), $row["ref"]==1?$row["val"].":":0);
			}
			elseif(isset($GLOBALS["arrays"][$row["t"]]))
				$children .= Export_reqs($row["t"], $row["id"], MaskDelimiters($row["val"]));
			elseif(!isset($GLOBALS["pwds"][$row["t"]]))
				$reqs[$row["t"]] = MaskDelimiters($row["up"] ? Get_tail($row["up"],$row["val"]) : $row["val"]);
		foreach($GLOBALS["local_struct"][$id] as $key => $value)
			if($key == 0)
				$str = MaskDelimiters($val).";";
			else
				$str .= isset($reqs[$key]) ? $reqs[$key].";" : ";";
		if(isset($GLOBALS["arrays"][$id]) || !isset($GLOBALS["linx"][$id])	# Array or Link values
				|| (($GLOBALS["id"] == $id) && ($_REQUEST["F_U"] > 1)))
			$str = "$id::$str\r\n";
		else	# Save the ID for we could have links to it later
		{
			$str ="$ref$id:$obj:$str\r\n";
			$GLOBALS["data"][$obj] = ""; # $str;
		}
	}
#my_die($id);
	return $refs.$str.$children; // Refs declarations go first, before the object using them 
}
# Remove a directory on the server
function RemoveDir($path)
{
	if(is_dir($path))
	{
		$dirHandle = opendir($path);
		while(false !== ($file = readdir($dirHandle)))
			if($file!='.' && $file!='..')
			{
				$tmpPath = $path.'/'.$file; 
				if(is_dir($tmpPath))
					RemoveDir($tmpPath);
				elseif(!unlink($tmpPath))
					die(t9n("[RU]Не удалось удалить файл[EN]Cannot delete the file")." '$tmpPath'.".BACK_LINK);
			}
		closedir($dirHandle);
		if(!rmdir($path))
			die(t9n("[RU]Не удалось удалить директорию '[EN]Couldn't drop folder '").$path."'.".BACK_LINK);
	}
	elseif(!unlink($path))
		die(t9n("[RU]Не удалось удалить файл '[EN]Couldn't drop file '").$path."'.".BACK_LINK);
}
function Get_block_data($block, $exe=TRUE)
{
    $tmp = explode(".", $block);
	$block_name = array_pop($tmp);
	if(!strlen($block_name) || (substr($block_name, 0, 1) == "_")) # The "_" prefix means a local block, no report implied
		return;
	global $blocks, $id, $f_u, $a, $obj, $z, $com, $args;
	switch ($block_name)
	{
		case "&top_menu":
			$blocks[$block]["top_menu"][] = t9n("[RU]Словарь[EN]Dictionary");
			$blocks[$block]["top_menu_href"][] = "dict";
			if(in_array(Check_Types_Grant(FALSE), array("READ", "WRITE")))
			{
				$blocks[$block]["top_menu"][] = t9n("[RU]Типы[EN]Types");
				$blocks[$block]["top_menu_href"][] = "edit_types";
			}
            if(RepoGrant() != "BARRED")
			{
				$blocks[$block]["top_menu"][] = t9n("[RU]Файлы[EN]Files");
				$blocks[$block]["top_menu_href"][] = "dir_admin";
			}
			#$blocks[$block]["top_menu"][] = t9n("[RU]Выход[EN]Exit");
			#$blocks[$block]["top_menu_href"][] = "exit";
			break;

		case "&main":
			$blocks[$block]["z"][] = $z;
			switch($GLOBALS["GLOBAL_VARS"]["action"]) # Show the current mode & object in the page header
			{
				case "object":
					if($id == 0)
						die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
					$data_set = Exec_sql("SELECT obj.val, obj.t, par.id FROM $z obj
										LEFT JOIN ($z par CROSS JOIN $z req USE INDEX (up_t)) ON par.up=0 AND req.up=par.id AND req.t=obj.id
										WHERE obj.id=$id AND (obj.up=0 OR par.up=0)"
										, "Get Object type name");
					if($row = mysqli_fetch_array($data_set))
					{
						$blocks[$block]["title"][] = $row[0];
						$blocks[$block]["typ"][] = $row[1];
						$blocks[$block]["parent_obj"][] = $row[2];
					}
					else
					    die(t9n("[RU]Тип $id не найден[EN]Type $id not found"));
					break;
				case "edit_obj":
					if($id == 0)
						die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
					$data_set = Exec_sql("SELECT typs.val, typs.t, a.val, typs.id
											FROM $z a, $z typs WHERE a.id=$id AND a.up!=0 AND typs.id=a.t AND typs.up=0"
										, "Get Object & type name");
					if($row = mysqli_fetch_array($data_set))
					{
						$blocks[$block]["title"][] = $row[0]." ".Format_Val_View($row[1], $row[2], $id);
						$GLOBALS["REV_BT"][$row["id"]] = $GLOBALS["REV_BT"][$row["t"]];
					}
        			else
        				die(t9n("[RU]Объект $id не найден, вероятно, он был удален[EN]Object $id not found (it might be deleted)"));
					break;
/*				case "backup":
					if(!isset($GLOBALS["GRANTS"]["EXPORT"][1]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
						die("У вас нет прав на выгрузку базы");
					$data_set = Exec_sql("SELECT id FROM $z WHERE up=0", "Get Objects list for export");
					while($row = mysqli_fetch_array($data_set))
						$header = Export_header($row["id"]);
#print($header);#print_r($blocks["typ"]);print_r($blocks["typ"]);print_r($GLOBALS);die();
					download_send_headers("data_export.bki");
					ob_start();
					$GLOBALS["CSV_handler"] = fopen("php://output", 'w');
					fwrite($GLOBALS["CSV_handler"], $header);
					fclose($GLOBALS["CSV_handler"]);
					echo ob_get_clean();
					die();
					break;
*/
				default:
					$blocks[$block]["title"][] = "Integral";
			}
#print_r($GLOBALS);die($a);
			break;

		case "&edit_typs":
#			Check_Types_Grant(FALSE);
			$data_set = Exec_sql("SELECT typs.id, typs.t, refs.id ref_val, typs.ord uniq
							, CASE WHEN refs.id!=refs.t THEN refs.val ELSE typs.val END val
							, reqs.id req_id, reqs.t req_t, reqs.ord, reqs.val attrs, ref_typs.t reft
						FROM $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.id!=refs.t
							LEFT JOIN $z reqs ON reqs.up=typs.id
							LEFT JOIN $z req_typs ON req_typs.id=reqs.t AND req_typs.id!=req_typs.t
							LEFT JOIN $z ref_typs ON ref_typs.id=req_typs.t AND ref_typs.id!=ref_typs.t
						WHERE typs.up=0 AND typs.id!=typs.t
						ORDER BY ISNULL(reqs.id), CASE WHEN refs.id!=refs.t THEN refs.val ELSE typs.val END, refs.id DESC, reqs.ord"
					   , "Get Typs & Reqs");
#[AS]07.01.2019				, CASE WHEN ref_typs.id!=ref_typs.t THEN ref_typs.val ELSE req_typs.val END req_val

			while($row = mysqli_fetch_array($data_set))
				foreach($row as $key => $value)
					$blocks[$block][$key][] = str_replace("\\","\\\\","$value");
			if(isApi())
			{
    			$GLOBALS["GLOBAL_VARS"]["api"]["edit_types"] = $blocks[$block];
                $GLOBALS["GLOBAL_VARS"]["api"]["types"] = $GLOBALS["basics"];			    
    			if(Check_Types_Grant() == "WRITE")
                    $GLOBALS["GLOBAL_VARS"]["api"]["editable"] = 1;			    
                die(json_encode($GLOBALS["GLOBAL_VARS"]["api"], JSON_HEX_QUOT));
			}
			break;

		case "&editables":
			if(Check_Types_Grant() == "WRITE")
				$blocks[$block]["ok"][] = ""; # Display the New Type and New Link blocks
			break;
			
		case "&types":
			foreach($GLOBALS["basics"] as $key => $value)
			{
				$blocks[$block]["typ"][] = "$key";
				$blocks[$block]["val"][] = $value;
			}
			break;

		case "&object":
			if($id == 0)
				die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
			$data_set = Exec_sql("SELECT a.*, typs.val typ_name, typs.t base_typ FROM $z a, $z typs WHERE a.id=$id AND typs.id=a.t AND typs.up=0"
								, "Get Object");
			if($row = mysqli_fetch_array($data_set))
			{
				Check_Val_Granted($row["t"], is_null($row["val"]) ? NULL : $row["val"]);

				$GLOBALS["parent_val"] = $row["val"];

				$blocks[$block]["id"][] = $GLOBALS["cur_id"] = $row["id"];
				$blocks[$block]["up"][] = $GLOBALS["parent_id"] = $row["up"];
				$blocks[$block]["typ"][] = $GLOBALS["parent_typ"] = $row["t"];
				$blocks[$block]["typ_name"][] = $row["typ_name"];
				$blocks[$block]["base_typ"][] = $GLOBALS["parent_base"] = $row["base_typ"];
				
				trace("Check_Grant for ".$row["id"]);
				if(Check_Grant($row["id"], 0, "WRITE", FALSE)) # Disable read-only values
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"] = "";
				else
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"] = "DISABLED";
				trace("_Grant for ".$row["id"]." is ".$GLOBALS["parent_disabled"]);

				$v = $row["val"];
				if(in_array($GLOBALS["REV_BT"][$row["base_typ"]], array("CHARS", "MEMO", "FILE", "HTML")))
					$v = Get_tail($row["id"], $v);
				if($GLOBALS["REV_BT"][$row["base_typ"]] != "SIGNED")
					$v = Format_Val_View($row["base_typ"], $v, $id);
				$blocks[$block]["val"][] = htmlspecialchars($v);
				
				GetObjectReqs($GLOBALS["parent_typ"], $id);
			}
			#  Check if we have some search criteria for Ref lists
			foreach($_REQUEST as $key => $value)
				if(substr($key, 0, 7) == "SEARCH_")
					if(strlen($value))	# Pass the criteria via GET - prepare the address string
						$GLOBALS["search"][substr($key, 7)] = $value;
#print_r($GLOBALS);die();
			break;

		case "&new_req":
		    $base = $GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]];
			if(($base != "REPORT_COLUMN") && ($base != "GRANT"))
			{
				$blocks[$block]["new_req"][] = "";
				$blocks[$block]["type"][] = ($base == "DATE" ? "date" : "text");
			}
			break;

		case "&new_req_report_column":
			if($GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]] == "REPORT_COLUMN")
				$blocks[$block]["new_req"][] = "";
			break;

		case "&new_req_grant":
			if($GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]] == "GRANT")
				$blocks[$block]["new_req"][] = "";
			break;

		case "&grant_list":
			$existing = $req = array();  # Existing grants
			$parent_id = $GLOBALS["parent_id"];
			$parent_val = $GLOBALS["parent_val"];
#print_r($GLOBALS);die();
			$data_set = Exec_sql("SELECT gr.id, gr.val, reqs.id req_id, reqs.t req_t, req_typ.val req_val, ref_reqs.val ref_val
									FROM $z gr LEFT JOIN ($z reqs CROSS JOIN $z req_typ) ON gr.id!=1 AND reqs.up=gr.id AND req_typ.id=reqs.t
										LEFT JOIN $z ref_reqs ON ref_reqs.id!=ref_reqs.t AND ref_reqs.id=req_typ.t
									WHERE gr.up=0 AND gr.t!=gr.id AND gr.val!='' AND !COALESCE(gr.t=0 OR req_typ.t=0, false)
									ORDER BY gr.val, reqs.ord"
					, "Get available Grants");
			while($row = mysqli_fetch_array($data_set))
			{
				$i = $row["id"];
				if(!isset($existing[$i]) && !isset($req[$i])) # Add the parent Object to the list
				{
					$existing[$i] = "";
					
					$blocks[$block]["id"][$i] = $i;
					$blocks[$block]["val"][$i] = $row["val"];
					if($GLOBALS["parent_val"] == $i)
						$blocks[$block]["selected"][$i] = "SELECTED";
					else
						$blocks[$block]["selected"][$i] = "";
				}
				if(($row["req_id"] != 0) && !isset($existing[$row["req_id"]]))# Add the requisites
				{
					$req[$row["req_t"]] = "";
					if(isset($existing[$row["req_t"]]))	# Drop the record on this Req
						unset($blocks[$block]["id"][$row["req_t"]], $blocks[$block]["val"][$row["req_t"]], $blocks[$block]["selected"][$row["req_t"]]);
					$blocks[$block]["id"][$row["req_id"]] = $row["req_id"];
					$blocks[$block]["val"][$row["req_id"]] = $row["val"]." -> ".$row["req_val"].$row["ref_val"];
					if($GLOBALS["parent_val"] == $row["req_id"])
						$blocks[$block]["selected"][$row["req_id"]] = "SELECTED";
					else
						$blocks[$block]["selected"][$row["req_id"]] = "";
				}
			}
			foreach(array(0, 1, 10) as $key) # Add "All objects" & "Type Editor" grants on the list
				if(($GLOBALS["GLOBAL_VARS"]["action"] != "object") || !isset($existing[$key]))
				{
					$blocks[$block]["id"][] = $key;
					$blocks[$block]["val"][] = Format_Val_View($GLOBALS["BT"]["GRANT"], "$key");
					if((string)$GLOBALS["parent_val"] == "$key")
						$blocks[$block]["selected"][] = "SELECTED";
					else
						$blocks[$block]["selected"][] = "";
				}
#print_r($GLOBALS);die();
			break;

		case "&editreq_grant":
			if($GLOBALS["REV_BT"][$GLOBALS["parent_base"]] == "GRANT")
				$blocks[$block]["typ"][] = $GLOBALS["parent_typ"];
			break;

		case "&editreq_report_column":
			if($GLOBALS["REV_BT"][$GLOBALS["parent_base"]] == "REPORT_COLUMN")
				$blocks[$block]["typ"][] = $blocks[$block]["val"][] = $GLOBALS["parent_typ"];
			break;

		case "&edit_req":
		    $base = $GLOBALS["REV_BT"][$GLOBALS["parent_base"]];
			if(($base != "REPORT_COLUMN") && ($base != "GRANT"))
			{
				$blocks[$block]["typ"][] = $GLOBALS["parent_typ"];
				$blocks[$block]["type"][] = ($base == "DATE" ? "date" : "text");
			}
			break;

		case "&rep_col_list":
			$existing = $in_list = array();  # Existing columns with parent Objects, columns added to the list
			$parent_id = $GLOBALS["parent_id"];
			$parent_val = $GLOBALS["parent_val"];
			$data_set = Exec_sql("SELECT a.val col_id, CASE WHEN pars.id IS NULL THEN a.val ELSE pars.id END par_id
						FROM $z typs, $z a LEFT JOIN ($z reqs CROSS JOIN $z pars) ON pars.id=reqs.up AND reqs.id=a.val
						WHERE $parent_id!=0 AND a.up=$parent_id AND a.val!=0 AND a.t=typs.id AND typs.t=".$GLOBALS["BT"]["REPORT_COLUMN"]
						." ORDER BY a.ord"
					, "Get Existing Report Columns");
			if($row = mysqli_fetch_array($data_set))
			{
				do
				{
					$v = $row["par_id"];
					if(!isset($in))		# Prepare the column-separated list of Cols
						$in = ":$v:";
					elseif(strpos($in, ":$v:") === false)
						$in .= ",:$v:";
				} while($row = mysqli_fetch_array($data_set));

				if(strlen($in))
					$in = str_replace(":", "", $in);
				else
					$in = 0;
				$data_set = Exec_sql("SELECT refs.t, links.up FROM $z refs, $z links, $z typs
											WHERE refs.t IN ($in) AND typs.up=0 AND links.t=refs.id AND typs.id=links.up AND typs.val!=''
									UNION SELECT linx.up, refs.t FROM $z refs, $z linx 
											WHERE linx.up IN ($in) AND linx.t=refs.id
									UNION SELECT arr_refs.up, arrs.id FROM $z arrs, $z reqs, $z arr_refs
											WHERE arrs.val!='' AND arrs.up=0 AND reqs.up=arrs.id 
												AND arr_refs.t=arrs.id AND arr_refs.up IN ($in) AND reqs.ord=1
									UNION SELECT arrs.id, arr_refs.up FROM $z arrs, $z reqs, $z arr_refs USE INDEX (up_t), $z objs 
											WHERE arrs.up=0 AND reqs.up=arrs.id AND arr_refs.t=arrs.id AND objs.up=0
												AND objs.id=arr_refs.up AND arrs.id+0 IN ($in) AND reqs.ord=1"
											# "arrs.id+0 IN ($in)" prevents using range search sometimes
						, "Get all referenced Objects");
				$refs = "";
				while($row = mysqli_fetch_array($data_set))
				{
					if(!isset($GLOBALS["basics"][$row[0]]))
					    if(strpos($refs, ":".$row[0].":") === false)
						    $refs .= ",:".$row[0].":";
					if(!isset($GLOBALS["basics"][$row[1]]))
    					if(strpos($refs, ":".$row[1].":") === false)
    						$refs .= ",:".$row[1].":";
				}
				if(strlen($refs))
					$in = str_replace(":","",substr($refs, 1));  # Cut first comma and remove columns
#print_r($GLOBALS);die();
				$data_set = Exec_sql("SELECT pars.id par_id, reqs.id req_id, pars.val par_name, pars.t par_base
								, req_typs.id req_typ, CASE WHEN req_typs.val='' THEN ref_reqs.val ELSE req_typs.val END req_name
								, ref_reqs.id ref_typ, reqs.val ref_name, cols.val cols, arr.id arr
								, CASE WHEN req_typs.val='' THEN ref_reqs.t ELSE req_typs.t END base
							FROM $z pars LEFT JOIN $z reqs ON reqs.up=pars.id 
							    LEFT JOIN $z req_typs ON req_typs.id=reqs.t
								LEFT JOIN $z ref_reqs ON ref_reqs.id=req_typs.t AND ref_reqs.id!=ref_reqs.t
								LEFT JOIN $z arr ON ref_reqs.id IS NULL AND arr.up=req_typs.id AND arr.ord=1
								LEFT JOIN (SELECT val FROM $z WHERE up=$parent_id AND val!='$parent_val' LIMIT 1) cols ON cols.val=reqs.id
							WHERE pars.id IN ($in) AND pars.id!=pars.t ORDER BY pars.val, reqs.ord"
						, "Get Available Report Columns");
			}
			else
				$data_set = Exec_sql("SELECT pars.id par_id, reqs.id req_id, pars.val par_name, reqs.val ref_name, NULL cols
								, CASE WHEN req_typs.val='' THEN ref_reqs.val ELSE req_typs.val END req_name, arr.id arr
							FROM $z pars, $z reqs
								LEFT JOIN $z req_typs ON req_typs.id=reqs.t
								LEFT JOIN $z ref_reqs ON ref_reqs.id=req_typs.t AND ref_reqs.id!=ref_reqs.t
								LEFT JOIN $z arr ON ref_reqs.id IS NULL AND arr.up=req_typs.id AND arr.ord=1
							WHERE pars.up=0 AND pars.val!='' AND reqs.up=pars.id AND req_typs.t!=0 ORDER BY pars.val, reqs.ord"
							, "Get All Report Columns");

			while($row = mysqli_fetch_array($data_set))
			{
				$pid = $row["par_id"];
				# Do not allow to create reports including the object forbidden in the role
			    if(isset($blocks["&main..&uni_obj.&new_req_report_column"]) && !Grant_1level($pid))
			        continue;
				if(!isset($in_list[$pid])  # Add a separate record for the Object's Value
					|| (!isset($parent_listed) && ($pid == $GLOBALS["parent_val"])))
				{
					if((!isset($parent_listed) && ($pid == $GLOBALS["parent_val"])))
						$parent_listed = TRUE;
					
					$in_list[$pid] = ""; # Mark it listed
					$blocks[$block]["id"][] = $pid;
					$blocks[$block]["val"][] = $row["par_name"];
					if($GLOBALS["parent_val"] == $pid)
						$blocks[$block]["selected"][] = "SELECTED";
					else
						$blocks[$block]["selected"][] = "";
						
        			if(isApi() && $row["base"])
        			{
            		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["id"] = $row["par_id"];
            		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["name"] = $row["par_name"];
            		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["type"] = $GLOBALS["REV_BT"][$row["par_base"]];
        			}
				}
#print_r($blocks);die();
				if(strlen($row["arr"]) || !isset($row["req_id"])) # Skip Array reqs or objects without reqs
					continue;
				if(!Check_Grant($pid, $row["req_id"], "READ", FALSE))
					continue;
				$alias = $row["par_name"]." -> ".$row["req_name"];
				# Correct the column name in case there is an alias
				if(isset($row["ref_typ"]) && strlen($row["ref_name"]))
				{
					$tmp = FetchAlias($row["ref_name"], $row["req_name"]);
					if($tmp != $row["req_name"])
						$alias = $row["par_name"]." -> $tmp (".$row["req_name"].")";
				}
				$blocks[$block]["val"][] = $alias;
				$blocks[$block]["id"][] = $row["req_id"];
				if($GLOBALS["parent_val"] == $row["req_id"])
					$blocks[$block]["selected"][] = "SELECTED";
				else
					$blocks[$block]["selected"][] = "";
				if(isset($existing[$pid]) && isset($existing[$row["ref_typ"]]))
					if(isset($existing[$pid."_".$row["ref_typ"]]))
					{
						if($existing[$pid."_".$row["ref_typ"]] == 0)
							$GLOBALS["warning"] .= t9n("[RU]Тип <b>".$row["req_name"]."</b> используется более 1 раза как реквизит типа "
							            ."[EN]Type <b>".$row["req_name"]."</b> is used more than once as attribute of type ")
							            ."<b>".$row["par_name"]."</b><br/>";
						$existing[$pid."_".$row["ref_typ"]]++;
					}
					else
						$existing[$pid."_".$row["ref_typ"]] = 0;
    			if(isApi() && $row["base"])
    			{
        		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["id"] = $row["req_typ"];
        		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["name"] = $alias;
        		    $GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["type"] = $GLOBALS["REV_BT"][$row["base"]];
    			}
 			}
			# Add Calculated field, which is to be calculated by an expression, constructed from aliases
			$blocks[$block]["id"][] = "0";
			$blocks[$block]["val"][] = CUSTOM_REP_COL;
			if($GLOBALS["parent_val"] == "0")
				$blocks[$block]["selected"][] = "SELECTED";
			else
				$blocks[$block]["selected"][] = "";
#print_r($GLOBALS);print_r($existing);die();
			break;

		case "&warnings":
			if(isset($GLOBALS["warning"]))
				$blocks[$block]["warning"][] = $GLOBALS["warning"];
			break;

		case "&tabs":
			if(!isset($GLOBALS["TABS"]))
				break; #	$GLOBALS["TABS"][0] = "Реквизиты";
			$tab = isset($_REQUEST["tab"]) ? $_REQUEST["tab"] : 0;
			foreach($GLOBALS["TABS"] as $key => $value)
			{
				$blocks[$block]["tab"][] = "$key";
				$blocks[$block]["val"][] = "$value";
				if($tab == $key || ($tab == 0 && !count($blocks[$block]["class"])))
				{
					$has_active = true;
					$blocks[$block]["class"][] = "class=\"tab-link active\"";
				}
				else
					$blocks[$block]["class"][] = "class=\"tab-link\"";
			}
			$blocks[$block]["tab"][] = "1";	# Show all Reqs in one tab
			$blocks[$block]["val"][] = t9n("[RU]Все[EN]All");
			$blocks[$block]["class"][] = $has_active ? "class=\"tab-link\"" : "class=\"tab-link active\"";
			break;

		case "&object_reqs":
			$rows = isset($GLOBALS["ObjectReqs"]) ? $GLOBALS["ObjectReqs"] : array();
			foreach($GLOBALS["REQS"] as $key => $value)
			{
				if(isset($rows[$key]))
					$row = $rows[$key];
				elseif(isset($GLOBALS["REF_typs"][$key]))
					$row = isset($rows[$GLOBALS["REF_typs"][$key]]) ? $rows[$GLOBALS["REF_typs"][$key]] : NULL;
				elseif(isset($GLOBALS["ARR_typs"][$key]))
					$row = array("arr_num" => isset($rows[$GLOBALS["ARR_typs"][$key]]["arr_num"]) ? (int)$rows[$GLOBALS["ARR_typs"][$key]]["arr_num"] : NULL);
				else
					$row = array();
				$row["attrs"] = $GLOBALS["REQS"][$key]["attrs"];
#print_r($GLOBALS);print_r($rows);die();
				$base_typ = $GLOBALS["REQS"][$key]["base_typ"];
				$GLOBALS["REV_BT"][$key] = $GLOBALS["REV_BT"][$base_typ];
				if(isset($GLOBALS["GRANTS"][$key])) # Skip barred Reqs - hide them
					if($GLOBALS["GRANTS"][$key] == "BARRED")
						continue;
				$v = isset($row["val"]) ? $row["val"] : "";
				if($GLOBALS["REV_BT"][$base_typ] == "BUTTON") # Remember Buttons to show them later as buttons
				{
					$blocks["BUTTONS"][$GLOBALS["REQS"][$key]["val"]] = $GLOBALS["REQS"][$key]["attrs"];
					continue;
				}
				if((isset($row["id"]) ? $row["id"] : 0) > 0)
				{
#					if(in_array($GLOBALS["REV_BT"][$base_typ], array("CHARS", "MEMO", "FILE", "HTML")))
#						$v = Get_tail($row["id"], $v);
					if(($GLOBALS["REV_BT"][$base_typ] != "SIGNED") && !isset($GLOBALS["REF_typs"][$key]))
						$v = Format_Val_View($base_typ, $v, $row["id"]);
				}
				else  # No requisite yet - add the default value, if any
				{
					if(strlen($row["attrs"]))  # We got either NOT_NULL or default value
					{
						$attrs = str_replace(NOT_NULL_MASK, "", $row["attrs"]); # Remove NOT_NULL and ALIAS by mask
						$attrs = preg_replace(ALIAS_MASK, "", $attrs);
						$v = BuiltIn($attrs); # Calc predefined value
						if($v == $attrs) # BuiltIn gave nothing - try calculatables
						{  
							$id_bak = $id;
							$block_bak = $block;
							Get_block_data($attrs);
							$id = $id_bak;   # Restore ID and Block info
							$block = $block_bak;
							if(isset($blocks[$attrs][strtolower($attrs)]))
							{
								if(count($blocks[$attrs][strtolower($attrs)]))
									$v = array_shift($blocks[$attrs][strtolower($attrs)]);
							}
    						elseif(isset($blocks[$attrs]))
    							foreach($blocks[$attrs] as $tmp)
    						    {
    								$v = array_shift($tmp);
    								break;
    						    }
						}
					}
					else
						$v = "";
				}
				if($GLOBALS["REV_BT"][$base_typ] != "FILE") # File contains hyperlink tags
					$blocks[$block]["val"][] = htmlspecialchars($v);
				else
					$blocks[$block]["val"][] = $v;
    			if(isApi()){
    				$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$key]["type"] = $GLOBALS["REQS"][$key]["val"];
    				$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$key]["value"] = $v;
    			}
				$blocks[$block]["typ"][] = $key;
				$blocks[$block]["up"][] = $id;
				$blocks[$block]["typ_name"][] = $GLOBALS["REQS"][$key]["val"];
				$blocks[$block]["not_null"][] = strpos($row["attrs"], NOT_NULL_MASK) === false ? 0 : 1;
				$blocks[$block]["arr_num"][] = isset($row["arr_num"]) ? $row["arr_num"] : 0;
				$blocks[$block]["arr"][] = isset($GLOBALS["ARR_typs"][$key]) ? $GLOBALS["ARR_typs"][$key] : 0;
				$blocks[$block]["attrs"][] = $row["attrs"];
				if(isset($GLOBALS["ARR_typs"][$key]))
					$GLOBALS["REV_BT"][$key] = "ARRAY";
			    trace("Check GRANTS for $key");
				if(Val_barred_by_mask($key, isset($row["val"]) ? $v : NULL))
						$blocks[$block]["disabled"][] = "DISABLED";
				elseif(isset($GLOBALS["GRANTS"][$key]))
				{
				    trace("GRANTS for $key: ".$GLOBALS["GRANTS"][$key]);
					if($GLOBALS["GRANTS"][$key] == "WRITE")
						$blocks[$block]["disabled"][] = $GLOBALS["enable_save"] = ""; # Activate the Save button
					else
						$blocks[$block]["disabled"][] = "DISABLED";
				}
				else
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"];
				# In case we have some reqs granted for edit, enable the Save button
				if(isset($GLOBALS["enable_save"]))
				{
    				$blocks[$block]["enable_save"][] = "<script>enable_save=1;</script>";
					unset($GLOBALS["enable_save"]);
				}
				else
    				$blocks[$block]["enable_save"][] = "";
#{print_r($GLOBALS);print_r($rows);print_r($row);die("!".$v);}

				if(isset($GLOBALS["REF_typs"][$key]))
				{
					if(strlen($v))
						Check_Val_granted($key, $row["ref_val"]); # Check if we got this Req val granted
					$GLOBALS["REV_BT"][$key] = "REFERENCE";
					$blocks[$block]["ref"][] = $GLOBALS["REF_typs"][$key];
					$blocks[$block]["base_typ"][] = $base_typ;
				}
				else
				{
					if(strlen($v))
						Check_Val_granted($key, $v); # Check if we got this Req val granted
					$blocks[$block]["ref"][] = "";
					$blocks[$block]["base_typ"][] = $base_typ;
				}
			}
#{print_r($GLOBALS);print_r($rows);print_r($blocks[$block]);die("".$GLOBALS["REF_typs"][$key]);}
			break;

		case "&editreq_array":
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"] != 0){
				$blocks[$block]["typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"];
				$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			}
			break;

		case "&editreq_pwd":
			if($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == "PWD")
				$blocks[$block]["val"][] = "******";
			break;

		case "&editreq_boolean":
			# Logical Reqs are sent with "CHECKED" attribute
			if($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == strtoupper(substr($block_name, 9)))
				if($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"]] == "BOOLEAN")
					$blocks[$block]["checked"][] = ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]!="" ? "CHECKED" : "");
		case "&editreq_short":
		case "&editreq_chars":
		case "&editreq_html":
		case "&editreq_file":
		case "&editreq_memo":
		case "&editreq_date":
		case "&editreq_datetime":
		case "&editreq_reference":
		case "&editreq_signed":
		case "&editreq_number":
		case "&editreq_calculatable":
#print_r($GLOBALS);die();
			if($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == strtoupper(substr($block_name, 9)))
			{
				$blocks[$block]["typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
				$blocks[$block]["ref"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];
				$blocks[$block]["base_typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"];
				$blocks[$block]["disabled"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["disabled"];
				if(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == "") # The current value is empty
					# and we got the value predefined from $_REQUEST
					&& isset($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
					$blocks[$block]["val"][] = $_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]];
				else
					$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			}
			break;
			
		case "&array_val": # This might contain some value, in case this Req once had no reqs of his own
		    if(isset($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"]))
    			if(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"] != 0) && strlen($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]))
    				$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			break;

		case "&nullable_req":
		case "&nullable_req_close":
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["not_null"] != 0)   # The Req marked as Not-NULL
				if(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == "")    # ... and actually is either NULL
					&& ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr_num"] == 0))   # ... or is an empty array
					$blocks[$block]["not_null"][] = "*";
			break;

		case "&ref_create_granted":
			if(Grant_1level(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"])) == "WRITE")
				$blocks[$block]["typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			$blocks[$block]["orig"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];
			break;

		case "&add_obj_ref_reqs":
			$cur_ref_req = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];  # Get the current link's type
			$cur_ref_typ = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			$search_val = "";
			if(isset($GLOBALS["search"][$cur_ref_typ])) # Check if we got some filter for the list
				$search_arr = explode("/", addslashes($GLOBALS["search"][$cur_ref_typ]));
			$data_set = Exec_sql("SELECT def_reqs.id, ref_reqs.id ref_req, base.t base, is_ref.val ref_name
											, CASE WHEN length(base.val)!=0 THEN 0 ELSE base.t END is_ref
									FROM $z r JOIN $z def_reqs ON def_reqs.up=r.t
									JOIN $z base ON base.id=def_reqs.t JOIN $z is_ref ON base.t=is_ref.id
									LEFT JOIN $z ref_reqs ON ref_reqs.up=r.id AND ref_reqs.t=def_reqs.t
								WHERE r.t=$cur_ref_req and r.up=0 ORDER BY ref_reqs.ord", "Get ref's reqs");
			# Fetch extra fields for the value of the Ref list
			$joins = $reqs = $reqs_granted = $sub_reqs = $join_granted = $search_req = "";
			$req_count = 0;
			if(isset($search_arr[0]))
				if(strlen($search_arr[0]))
				{
					$GLOBALS["where"] = "";
					Construct_WHERE($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], array("F" => $search_arr[$req_count])
									, $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], FALSE, TRUE);
					$search_req = str_replace("a".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"].".val", "vals.val", $GLOBALS["where"]);
#print_r($GLOBALS);die($search_req);
				}
			while($row = mysqli_fetch_array($data_set))
			{
				$req = $row["id"];
				if(isset($row["ref_req"]))
				{
					$req_count++;
					if($row["is_ref"])	# Join requisites' tables
						$joins .= " LEFT JOIN ($z r$req CROSS JOIN $z a$req) ON r$req.up=vals.id AND a$req.id=r$req.t AND a$req.t=".$row["is_ref"];  
					else
						$joins .= " LEFT JOIN $z a$req ON a$req.up=vals.id AND a$req.t=$req";
					$reqs .= ", $req"."val";	# Fetch Requisites' values
					$sub_reqs .= ", a$req.val $req"."val";
					if(isset($search_arr[$req_count]))	# Apply Req's filters, if any
						if(strlen($search_arr[$req_count]))
						{
							$GLOBALS["REV_BT"][$req] = $GLOBALS["REV_BT"][$row["base"]];
							$GLOBALS["where"] = "";
							Construct_WHERE($req, array("F" => $search_arr[$req_count]), 1, FALSE, TRUE);
							$search_req .= $GLOBALS["where"];
						}
				}
				# Fetch grants to the reqs of the Referenced Object
				if(isset($GLOBALS["GRANTS"]["mask"][$req]))
				{
					unset($granted);
					foreach($GLOBALS["GRANTS"]["mask"][$req] as $mask) # Apply all masks
					{
						$GLOBALS["where"] = $GLOBALS["join"] = "";
						if($GLOBALS["REV_BT"][$row["base"]])
							$GLOBALS["REV_BT"][$req] = $GLOBALS["REV_BT"][$row["base"]];
						else
						{
							$GLOBALS["REV_BT"][$req] = "REFERENCE";
							$GLOBALS["REF_typs"][$req] = $row["base"];
						}
						$GLOBALS["where"] = "";
#die("$req $cur_ref_typ ".$row["ref_req"]);
						Construct_WHERE($req, array("F" => $mask), $cur_ref_req, $cur_ref_typ);
						if(isset($granted))
							$granted .= " OR ".substr($GLOBALS["where"], 4);
						else
							$granted = substr($GLOBALS["where"], 4);
						if(strpos($join_granted.$joins, "$z a$req") === FALSE) # Is the table joined already?
							$join_granted .= $GLOBALS["join"];
					}
					$reqs_granted .= " AND ($granted) ";
				}
			}
			# Fetch grants to the Referenced Object itself
			if(isset($GLOBALS["GRANTS"]["mask"][$cur_ref_req]))
			{
				unset($granted);
				foreach($GLOBALS["GRANTS"]["mask"][$cur_ref_req] as $mask) # Apply all masks
				{
					$GLOBALS["where"] = "";
					Construct_WHERE($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], array("F" => $mask), 1, FALSE, TRUE);
					if(isset($granted))
						$granted .= " OR ".substr($GLOBALS["where"], 4);
					else
						$granted = substr($GLOBALS["where"], 4);
				}
				$reqs_granted .= str_replace("a".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"].".val", "vals.val", " AND ($granted) ");
			}
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] != 0)
				$cur_val = " UNION (SELECT vals.id, vals.val $sub_reqs FROM $z vals $joins WHERE vals.id=".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"].") ";
			elseif(isset($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
				$cur_val = " UNION (SELECT vals.id, vals.val $sub_reqs FROM $z vals $joins WHERE vals.id=".addslashes($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]).") ";
			else
				$cur_val = "";

			$sql = "SELECT vals.id, vals.val ref_val $reqs 
						FROM (SELECT vals.id, vals.val $sub_reqs FROM $z vals  $join_granted $joins, $z pars
						WHERE pars.id=vals.up AND pars.up!=0 AND vals.t=$cur_ref_req $search_val $reqs_granted $search_req LIMIT ".DDLIST_ITEMS.") vals
					$cur_val ORDER BY ref_val";
			$data_set = Exec_sql($sql, "Get Object ref reqs"); # Add_Obj_Ref_Reqs
			$blocks["ref_count"] = mysqli_num_rows($data_set); # Expand the list in case there are more
			while($row = mysqli_fetch_array($data_set))
			{
#if($cur_ref_typ)
#{print_r($GLOBALS);die($cur_ref_typ." : ".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]);}
				$i = 1;
				$reqs = "";
				while($i <= $req_count)  # Append more identifying info to the dropdown list values
				{
					$i++;
					if(strlen($row[$i]))
						$reqs .= " / ".$row[$i];
					else
						$reqs .= " / --";
				}
				$blocks[$block]["r"][] = $cur_ref_typ;
				$blocks[$block]["id"][] = $row["id"];
				$blocks[$block]["val"][] = Format_Val_View($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"]
															, htmlspecialchars($row["ref_val"])).$reqs;
				if(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == 0) # The current value is empty
					# and we got the list value predefined from $_REQUEST
				  && isset($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
				{
					if($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == $row["id"])
						$blocks[$block]["selected"][] = " SELECTED";
					else
						$blocks[$block]["selected"][] = "";
				}
				elseif($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == $row["id"])
					$blocks[$block]["selected"][] = " SELECTED";
				elseif(($blocks["ref_count"] == 1) 
						&& (isset($_REQUEST["t".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]])
								|| isset($_REQUEST["SEARCH_".$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]])))
					$blocks[$block]["selected"][] = " SELECTED";
				else
					$blocks[$block]["selected"][] = "";
			}
			break;
			
		case "&seek_refs":
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["disabled"] == "")
				if(($blocks["ref_count"] >= DDLIST_ITEMS)  # We got more items, than DDLIST_ITEMS
					|| isset($GLOBALS["search"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]])) # or got the search criteria
					# Fill in the received search criteria, if any
					$blocks[$block]["search"][] = "".$GLOBALS["search"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]];
			break;
		
		case "&uni_obj_list":
		    if(isset($_GET["val"]))
		        $cond = "AND a.val LIKE '".addslashes($_GET["val"])."'";
		    else
		        $cond = "";
			$sql = "SELECT a.id, a.val, a.t, reqs.t reqs_t FROM $z a LEFT JOIN $z reqs ON reqs.up=a.id
						WHERE a.up=0 AND a.id!=a.t AND a.val!='' AND a.t!=0 $cond ORDER BY a.val";
			$data_set = Exec_sql($sql, "Get all independent Typs");
			while($row = mysqli_fetch_array($data_set))  # All but buttons and calculatables
				if(($GLOBALS["REV_BT"][$row["t"]] != "CALCULATABLE") && ($GLOBALS["REV_BT"][$row["t"]] != "BUTTON"))
				{
					if(!isset($req[$row["id"]]))  # Not used as Req yet
						$typ[$row["id"]] = $row["val"];
					if($row["reqs_t"])	# Check if our Reqs are on list of independents and remove them
					{
						unset($typ[$row["reqs_t"]]);
						$req[$row["reqs_t"]] = "";	# Remember the Req ID
					}
				}
			if(count($typ))
				foreach($typ as $id => $val)
					if(Grant_1level($id))
					{
						$blocks[$block]["id"][] = $id;
						$blocks[$block]["val"][] = htmlspecialchars($val);
					}
			break;

		case "&uni_obj":
#print_r($GLOBALS);die();
			if($f_u > 1)
			{
				if(isset($_REQUEST["_m_del_select"])) # The user tries to drop the selection
				{
					check();
					Check_Grant($f_u, $id);  # Check the delete grant
				}
				elseif(Check_Grant($f_u, $id, "READ", FALSE) === FALSE)
					break;
			}
			elseif(isset($_REQUEST["_m_del_select"])) # The user tries to drop the selection
			{
				check();
				if(Grant_1level($id) != "WRITE")
					die(t9n("[RU]У вас нет доступа на изменение этих данных[EN]You have no grant to delete this data"));
			}
			elseif(Grant_1level($id) === FALSE)
				if($blocks["&main"]["CUR_VARS"]["parent_obj"])	# Array req via links
					Check_Grant($blocks["&main"]["CUR_VARS"]["parent_obj"], $id, "READ");
				else
					break;
			if((Grant_1level($id) == "WRITE") || Check_Grant($f_u, $id, "WRITE", FALSE))
				$blocks[$block]["create_granted"][] = "block";
			else
				$blocks[$block]["create_granted"][] = "none";

			if(isset($_REQUEST["order_val"]))
				$GLOBALS["ORDER_VAL"] = $_REQUEST["order_val"]=="val" ? "val" : (int)$_REQUEST["order_val"];
			else
				$GLOBALS["ORDER_VAL"] = 0;
# Gather all filter values to preserve them in HREF
			$f = "";
			foreach($_REQUEST as $key => $value)
				if(($value!="") && (preg_match("/(F\_|FR\_|TO\_)/", $key)))
					$f .= "&".$key."=".str_replace("\"", "&#34;", $value);
			if(isset($_REQUEST["f_show_all"]))
				$f .= "&f_show_all=1";
			if(isset($_REQUEST["full"]))
				$f .= "&full=0";
			if(isset($_REQUEST["lnx"]))
				if($_REQUEST["lnx"] == 1)
					$f .= "&lnx=1";
			$GLOBALS["FILTER"] = $f; # Remember the filter to use it in other blocks
			if(!isset($_REQUEST["desc"]) && ($GLOBALS["ORDER_VAL"]==="val")) # Revert the sort order, if needed
				$blocks[$block]["filter"][] = "$f&desc=0";
			else
				$blocks[$block]["filter"][] = $f;
			if(isset($blocks["&main"]["CUR_VARS"]["title"]))
			{
				$GLOBALS["parent_id"] = $f_u;
				$GLOBALS["parent_val"] = 0;

				$blocks[$block]["id"][] = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["id"] = $id;
				$blocks[$block]["up"][] = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["up"] = ($f_u > 1) ? $f_u : 1;
				$blocks[$block]["typ"][] = $id;
				$blocks[$block]["val"][] = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["val"] = $blocks["&main"]["CUR_VARS"]["title"];
				$blocks[$block]["base_typ"][] = $GLOBALS["GLOBAL_VARS"]["api"]["base"]["id"] = $blocks["&main"]["CUR_VARS"]["typ"];
				$GLOBALS["REV_BT"][$id] = $GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]]; # the base type
				$blocks[$block]["f_i"][] = isset($_REQUEST["F_I"]) ? (int)$_REQUEST["F_I"] : "";
				$blocks[$block]["f_u"][] = isset($_REQUEST["F_U"]) ? (int)$_REQUEST["F_U"] : "";

				if(isset($_REQUEST["switch_links"]))
					$GLOBALS["lnx"] = ($_REQUEST["lnx"] == 1) ? 0 : 1;
				else
					$GLOBALS["lnx"] = isset($_REQUEST["lnx"]) ? (int)$_REQUEST["lnx"] : 0;

				$blocks[$block]["lnx"][] = $GLOBALS["lnx"];
				if($GLOBALS["lnx"] == 1)
				{
					$data_set = Exec_sql("SELECT typs.id, typs.up, objs.val, refs.val refr, typs.val attr
									FROM $z a, $z typs, $z objs, $z refs
									WHERE a.t=$id AND a.up=0 AND typs.t=a.id AND objs.id=typs.up AND refs.id=a.t"
							, "Get Links to this object");
					$GLOBALS["links"] = $GLOBALS["links_val"] = Array();
					while($row = mysqli_fetch_array($data_set))
					{
						$GLOBALS["links"][$row["id"]] = $row["up"];
						$GLOBALS["links_val"][$row["id"]] = $row["val"].".".FetchAlias($row["attr"], $row["refr"]);
					}
				}
#print_r($GLOBALS);die();
			}
			break;

		case "&uni_obj_parent":
			if($f_u > 1)
			{
				$data_set = Exec_sql("SELECT typs.id, typs.val typ, objs.val name, objs.up, base.t base FROM $z objs, $z typs, $z base
									WHERE typs.id=objs.t AND objs.id=$f_u AND base.id=typs.t", "Get Typ name and type");
				if($row = mysqli_fetch_array($data_set))
				{
					$blocks[$block]["tid"][] = $row["id"];
					$blocks[$block]["typ"][] = $row["typ"];
					$blocks[$block]["name"][] = Format_Val_View($row["base"], $row["name"]);
					$blocks[$block]["up"][] = $row["up"];
        			if(isApi())
        			{
            		    $GLOBALS["GLOBAL_VARS"]["api"]["parent"]["id"] = $row["id"];
            		    $GLOBALS["GLOBAL_VARS"]["api"]["parent"]["name"] = $row["name"];
            		    $GLOBALS["GLOBAL_VARS"]["api"]["parent"]["type"] = $row["typ"];
            		    $GLOBALS["GLOBAL_VARS"]["api"]["parent"]["up"] = $row["up"];
        			}
				}
			}
			break;

		case "&uni_obj_head":
			if(isset($_POST["import"]))
			{
				check();
				Export_header($id);
				$max_size = 4194304;
				if(!is_file($_FILES["bki_file"]["tmp_name"]))
					die(t9n("[RU]Выберите файл (максимальный размер: $max_size Б)[EN]Please select a file (max size is $max_size Bytes)"));
				if($_FILES["bki_file"]["size"] > $max_size)
					die(t9n("[RU]Ошибка. Максимальный размер файла: $max_size Б[EN]The maximum file size is $max_size B)"));
				$up = ($GLOBALS["parent_id"] > 1) ? $GLOBALS["parent_id"] : 1;
				$handle = fopen($_FILES["bki_file"]["tmp_name"], "r");
				$buffer = fgets($handle);
                # Remove BOM, if exists
            	if(substr($buffer, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf))
                    $buffer = substr($buffer, 3);
				$i = (int)substr($buffer, 0, strpos($buffer, ":"));
				if(!isset($GLOBALS["GRANTS"]["EXPORT"][$i]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на загрузку объектов этого типа[EN]You are not granted to upload this type of objects"));
				if($i > 1)
					Export_header($i);	# Retrieve the existing data structure
				elseif($i == "DATA")
				{
				    $plain_data = true; // Plain data with no definitions - the exact structure is already in place
				    trace("Plain DATA");
				}
				else
					die(t9n("[RU]Недопустимый тип метаданных $i [EN]Invalid metadata type $i"));
				if(!$plain_data)
				{
#				print_r($GLOBALS);die($i);
    				# Check Typ's independence
    				if($i != $GLOBALS["GLOBAL_VARS"]["id"])
    				{
    					$sql = "SELECT a.val FROM $z a LEFT JOIN $z refs ON refs.id=a.t AND refs.t!=refs.id 
    								LEFT JOIN ($z obj CROSS JOIN $z req) ON obj.up=0 AND req.up=obj.id AND req.t=a.id
    							WHERE a.id=$i";
    					$data_set = Exec_sql($sql, "Check Typ's independence");
    					if($row = mysqli_fetch_array($data_set))
    					{
    						if(($up == 1) && ($row["up"] != 0))
    							die(t9n("[RU]Реквизит типа \"".$row[0]."\" (id=$i) необходимо загружать в его родительской записи".
    							    "[EN]The object \"".$row[0]."\" (id=$i) should be uploaded under its parent"));
    					}
    					else
    						if($up != 1)
    							die(t9n("[RU]Несуществующий реквизит типа $i (реквизиты можно импортировать только в составе типа)".
    							       "[EN]Non-exiting attribute of $i (attributes are uploaded within its parent definition)"));
    #print_r($GLOBALS);die($i);
    				}
    				# Validate Parent ID
    				if($up != 1)
    				{
    					$data_set = Exec_sql("SELECT reqs.t req, a.t par FROM $z a LEFT JOIN $z reqs ON reqs.up=a.t AND reqs.t=$i WHERE a.id=$up"
    										, "Validate Parent ID");
    					if($row = mysqli_fetch_array($data_set))
    					{
    						if($row["req"] != $i)
    							die(t9n("[RU]Реквизит типа $i отсутствует у родителя $up типа[EN]The $i type is missing from the $up type parent".$row["par"]));
    					}
    					else
    						die(t9n("[RU]Родительская запись с id=$i не найдена[EN]Parent record with id=$i not found"));
    				}
    				$count = 1;
    				while(true)
    				{
    					if($buffer=="DATA\r\n")	# Types end, data begins
    					{
    					    trace(" Types end, data begins");
    						break;
    					}
    					$object = explode(";", HideDelimiters($buffer));	# Get Types array
    					array_pop($object);	# Cut off the empty item after the last semi-colon
    					$order = 0;
    					$typ = explode(":",  $object[0]);	# Get Type's attributes
    					$obj = $typ[0];
    					foreach($object as $value)
    					    $GLOBALS["imported"][$obj][$order++] = UnHideDelimiters($value);
    			        trace("(".$count++.") check $obj");
    			        if(count($typ) > 2) # Not a Reference type
        				    if(IsOccupied($obj)) # Check if the ID is occupied and resolve the conflict, if any
        				    {
            			        trace(" $obj is occupied");
            				    Export_header($obj);
        				        if($GLOBALS["local_struct"][$obj][0] != $GLOBALS["imported"][$obj][0])
        				            ResolveType($typ);
        				    }
        				    else # The ID is free - create the Object with this ID
        				    {
        				        trace(" create the Object ".$obj);
                                exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($obj, 0, ".(isset($typ[3])?"1":"0").", ".$GLOBALS["BT"][$typ[2]].", '".addslashes($typ[1])."')"
                                            , "Import Obj with ID");
                                $GLOBALS["local_struct"][$obj][0] = $GLOBALS["imported"][$obj][0];
        				    }
        				if(feof($handle))
        				    break;
    					$buffer = fgets($handle);
    				}
    #print_r($GLOBALS);my_die();
                    trace("Start reconciling the objects");
    				foreach($GLOBALS["imported"] as $par => $reqs)
    				{
    			        $parent = CheckSubst($par);
    			        foreach($reqs as $order => $req)
    			        {
    				        if($order == 0)
    				            continue;
    				        trace(" Imported req $order ".$reqs[$order]);
    			            $typ = UnHideDelimiters(explode(":",  HideDelimiters($req)));
    			            $value = $typ[0].":".CheckSubst($typ[1]);
    			            if($typ[0] == "ref")
    			            {
    			                trace($typ[1]." is a ref");
    	    		            $value .= $typ[2];
    			            }
    			            $found = false;
    			            foreach($GLOBALS["local_struct"][$parent] as $local_type => $local_value)
    			                if($found = ($value == substr($local_value, 0, strlen($value))))
    			                    break;
    				        if($found)
    				        {
        				        trace("  match found for $value");
        				        if($req == $local_value)
            				        trace("   this is a full match $req => $local_value");
        				        else
        				        {
            				        trace("   adjust $req => $local_value");
        				            $local = UnHideDelimiters(explode(":",  HideDelimiters($local_value)));
        				        }
        				        if($typ[0] == "ref")
            				        $GLOBALS["local_types"][$par][$order] = $typ[2];
            				    else
            				        $GLOBALS["local_types"][$par][$order] = $local_type;
    				        }
    				        elseif($typ[0] == "ref")
    				        {
    						    trace(" Define ref req $order ".$typ[1]." as $req. Ref ID is ".$typ[2]);
    						    $reqID = $typ[1];
    						    $refID = $typ[2];
                                $obj = explode(":",  $GLOBALS["imported"][$typ[2]][0]);
    						    trace("  ref Obj is ".$obj[1]." substituted by ".CheckSubst($obj[1]));
    						    $obj = CheckSubst($obj[1]);
        						# Create a ref to Object, if not exists
    							if(IsOccupied($refID))
    							{
    							    $row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE up=0 AND t=$obj AND val=''", "Seek Ref"));
    							    if($row["id"])
    							    {
    							        $refID = $row["id"];
    							        trace("  the ref $refID to $obj exists");
    							    }
    							    else
                                        $refID = $GLOBALS["local_struct"]["subst"][$refID] = Insert(0, 0, $obj, "", "Import Ref without ID");
    							}
                                else
                                    exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($refID, 0, 0, $obj, '')", "Import Ref with ID");
    	    		            $GLOBALS["refs"][$refID] = "";
        						# Create a ref Req, if not exists
    							if(IsOccupied($reqID))
    							{
    							    $row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refID AND up=$parent", "Seek ref Req"));
    							    if($row["id"])
    							    {
    							        $reqID = $row["id"];
    							        trace("  the ref req $reqID to $obj exists");
    							    }
    							    else
                                        $reqID = $GLOBALS["local_struct"]["subst"][$reqID] = Insert($parent, $order, $refID, isset($typ[3])?UnMaskDelimiters($typ[3]):""
                                                        , "Import Ref without ID");
    							}
                                else
                                    exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($reqID, $parent, $order, $refID, '')", "Import ref Req with ID");
    	    		            $GLOBALS["refs"][$reqID] = $refID;
                                $GLOBALS["local_types"][$par][$order] = CheckSubst($reqID);
    				        }
    				        elseif($typ[0] == "arr")
    				        {
        				        $i = CheckSubst($typ[1]);
    						    trace("   Define array req ".$typ[1]." $reqs[$order] as ".$GLOBALS["imported"][$typ[1]][0]);
            				    $reqID = Insert($parent, $order, $i, isset($typ[2])?UnMaskDelimiters($typ[2]):"", "Import arr Req");
    							$GLOBALS["local_struct"][$parent][$reqID] = $reqs[$order];	# Register the new Req
        				        $GLOBALS["local_types"][$par][$order] = $reqID;
        				        $GLOBALS["parents"][$i] = $parent;
    				        }
        				    else # A plain req - find an analogue or register the new one
        				    {
        				        trace("   $req is a plain req - find an analogue or register the new one");
    							$data_set = Exec_sql("SELECT id FROM $z WHERE val='".addslashes($typ[0])."' AND up=0 AND t=".$GLOBALS["BT"][$typ[1]], "Seek Req Typ");
    							if($row = mysqli_fetch_array($data_set))	# The Type of the Req exists - add the Req to the Type
    								$i = $row["id"];
    							else	# No analogue, register the new one
    								$i = Insert(0, 0, $GLOBALS["BT"][$typ[1]], $typ[0], "Import new Type for Req");
    							$i = Insert($parent, Get_Ord($parent), $i, isset($typ[2])?UnMaskDelimiters($typ[2]):"", "Import new Req");
    							$GLOBALS["local_struct"][$parent][$i] = $req;	# Register the new Req
        				        $GLOBALS["local_types"][$par][$order] = $i;
        				    }
        		        }
    				}
				}
				trace("Data");
#print_r($GLOBALS);my_die();
				$GLOBALS["cur_parent"][0] = $up;
				if($plain_data)
				{
    				while(!feof($handle))
    				{
    					$buffer = fgets($handle);	# Read the line
    					if(strlen($buffer)==0)
    						continue;
    					$object = UnHideDelimiters(explode(";", HideDelimiters($buffer)));	# Get fields array
                        if($object[0] == "")
                            my_die(t9n("[RU]Пустой объект типа $id (строка $count)[EN]Empty object of type $id (string $count)"));
    					while(count($object) <= count($GLOBALS["local_types"][$id]))	# There might be line breaks
    					{
    					    if(feof($handle))
    					        my_die(t9n("[RU]Неожиданный конец файла [EN]Unexpected end of file"));
    						$buffer .= fgets($handle);	# Continue retrieving lines until we collect all the Reqs
    						$object = UnHideDelimiters(explode(";", UnHideDelimiters($buffer)));
        					$count++;
    					}
    					end($object);
    					$object[key($object)] = rtrim(current($object), "\t\n\r\0\x0B"); // Remove CR, LF and other ending chars, if any
        				trace("(".$count++.") Buffer: $buffer");
    					if(isset($GLOBALS["cur_parent"][$GLOBALS["parents"][$id]]))
    						$parent = $GLOBALS["cur_parent"][$GLOBALS["parents"][$id]];
    					else
    						$parent = 1;
    					$object[0] = Format_Val($GLOBALS["base"][$id], UnMaskDelimiters($object[0]));
    					$new_id = Insert($parent, ($parent > 1 ? Get_ord($parent, $id) : 1), $id, $object[0], "Plain import");
    					#array_pop($object); 	# Cut off the empty item after the last semi-colon
    					$order = 0;
    					foreach($GLOBALS["local_struct"][$id] as $key => $value)
                        {
                            if($key == 0)
                                continue;
                            $order++;
        				    trace(" Parse $key ".$object[$order])." of $value";
    						if(strlen($object[$order]))
    							if(!isset($GLOBALS["refs"][$key])) // Ordinary attribute of some base type
    								Insert_batch($new_id, 1, $key, Format_Val($GLOBALS["base"][$key], UnMaskDelimiters($object[$order])), "Import plain req");
    							else // Reference object
    						    {
    #						        print_r($GLOBALS);my_die();
    							    $refType = $GLOBALS["refs"][$key];
    							    if(isset($GLOBALS["refs"][$refType]))
        							    if(isset($GLOBALS["refs"][$refType][$object[$order]]))
        							    {
            							    Insert_batch($new_id, 1, $GLOBALS["refs"][$refType][$object[$order]], $key, "Import cached plain ref");
            							    continue;
            						    }
    								if($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refType AND val='".$object[$order]."'", "Check plain ref Obj Value")))
    								    $refObjID = $row["id"];
    								else
    								    $refObjID = Insert(1, 1, $refType, $object[$order], "Import plain ref Object");
        							Insert_batch($new_id, 1, $refObjID, $key, "Import plain ref");
        							$GLOBALS["refs"][$refType][$object[$order]] = $refObjID;
    						    }
    				    }
    				}
				}
				else
				while(!feof($handle))
				{
					$buffer = fgets($handle);	# Read the line
					if(strlen($buffer)==0)
						continue;
					$object = UnHideDelimiters(explode(";", HideDelimiters($buffer)));	# Get Types array
    				trace("");
					$typ = UnHideDelimiters(explode(":", HideDelimiters($object[0])));	# Get Type's attributes
    				trace("Object: ".$object[0].", typ: ".$typ[0]);
                    if(count($typ) == 4) # Reference attribute
                    {
					    trace("Reference attribute ".$object[0]);
                        $isref = CheckSubst((int)array_shift($typ)); # Remember the Ref attribute
                    }
    				$orig = (int)$typ[0];	# Get the imported Type
					while(count($object) <= count($GLOBALS["imported"][$orig]))	# There might be line breaks
					{
					    if(feof($handle))
					        my_die(t9n("[RU]Неожиданный конец файла[EN]Unexpected end of file"));
						$buffer .= fgets($handle);	# Continue retrieving lines until we collect all the Reqs
						$object = UnHideDelimiters(explode(";", UnHideDelimiters($buffer)));
    					$count++;
					}
    				$t = CheckSubst($orig);	# Detect the target Type
					if(!isset($GLOBALS["local_struct"][$t]))
					{
					    print_r($GLOBALS);
						my_die(t9n("[RU]Недопустимый тип $t, остутствующий в мета-данных[EN]Invalid type $t that is not present in the metadata"));
					}
    				trace("(".$count++.") Buffer: $buffer");
#print_r($GLOBALS);my_die();
					array_pop($object);	# Cut off the empty item after the last semi-colon
					$new_id = $order = 0;
					foreach($object as $value)
					{
					    trace(" Parse  $value, t:$t, orig:$orig");
						if($new_id)
						{
                            $order++;
							$key = $GLOBALS["local_types"][$orig][$order];
                            trace(" order:$order: key:$key of t:$orig ($t)");
#print_r($GLOBALS);my_die();
                            if($key == "")
                                my_die(t9n("[RU]Тип $orig ($t) не имеет реквизита №$order [EN]The type $orig ($t) does not have the $order attribute"));
							if(strlen($value))
								if(!isset($GLOBALS["refs"][$key])) // Ordinary attribute of some base type
									Insert_batch($new_id, 1, $key, UnMaskDelimiters($value), "Import req");
								elseif((strpos($value, ":") !== FALSE) // Referenced object set by Value like "ID:Value" or ":Value"
								        || ((int)$value == 0))  // or just "Value"
								{
								    if((strpos($value, ":") === FALSE))  // just "Value"
								    {
								        $refObjID = 0;
    								    $refObjVal = $value;
								    }
								    else // ":Value"
								    {
    								    $tmp = UnHideDelimiters(explode(":", HideDelimiters($value)));
    								    $refObjID = (int)$tmp[0];
    								    $refObjVal = $tmp[1];
								    }
								    if(!isset($GLOBALS["local_types"][$key])) // Remember the $key to fetch it faster
								    {
    								    $tmp = explode(":", $GLOBALS["local_struct"][$key][0]);
								        $GLOBALS["local_types"][$key] = $tmp[1];
								    }
								    $refType = $GLOBALS["local_types"][$key];
                                    trace("   ref type: $refType");
								    $refObjVal = addslashes(UnMaskDelimiters($refObjVal));
								    if($refObjID > 0) // "ID:Value"
								    {
        								if($row = mysqli_fetch_array(Exec_sql("SELECT t, val FROM $z WHERE id=$refObjID", "Check ref Obj ID")))
        								{
        								    trace("The object exists t=".$row["t"]." value=".$row["val"]);
        									if($row["t"] != $refType)	# The object exists, but the type is wrong
        									{
            								    trace(" the type is wrong ".$row["t"]." != $refType");
            									$refObjID = $GLOBALS["obj_subst"][$refObjID] = Insert(1, 1, $refType, $refObjVal, "Import new ID");
            									
        									}
        								}
        								elseif(strlen($refObjVal))	# The object does not exist
            								exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($refObjID, 1, 1, $refType, '$refObjVal')", "Import ref Obj with ID");
								    }
								    elseif(strlen($refObjVal)) // ":Value" - just create the new Object
								    {
        								if($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refType AND val='$refObjVal'", "Check ref Obj Value")))
        								    $refObjID = $row["id"];
        								else
        								    $refObjID = Insert(1, 1, $refType, $refObjVal, "Import direct ref Object");
								    }
									Insert_batch($new_id, 1, $refObjID, $key, "Import direct ref");
								}
								elseif((int)$value != 0) // Reference set by ID
									Insert_batch($new_id, 1, CheckObjSubst((int)$value), $key, "Import ref");
						}
						else
						{
                            if($typ[2] == "")
                                my_die(t9n("[RU]Пустой объект типа $t (строка $count)[EN]Empty object of type $t (string $count)"));
							if(isset($GLOBALS["cur_parent"][$GLOBALS["parents"][$t]]))
							{
								$parent = $GLOBALS["cur_parent"][$GLOBALS["parents"][$t]];
								if(isset($GLOBALS["cur_order"][$parent]))
								    $ord = ++$GLOBALS["cur_order"][$parent];
								else
								    $ord = $GLOBALS["cur_order"][$parent] = Get_ord($parent, $t);
							}
							else
								$parent = $ord = 1;
							$typ[2] = UnMaskDelimiters($typ[2]);
							if($typ[1] == "")
								$new_id = Insert($parent, $ord, $t, $typ[2], "Import no ID");
							else
							{
								$new_id = $typ[1];
								if($row = mysqli_fetch_array(Exec_sql("SELECT t, val FROM $z WHERE id=$new_id", "Check ID presence")))
								{
									if(($row["t"] == $t) && ($row["val"] == $typ[2]))	# The object exists
										break;
									$new_id = $GLOBALS["obj_subst"][$new_id] = Insert($parent, $ord, $t, $typ[2], "Import new ID");
								}
								elseif($isref) # Insert Reference, ToDo: fix to support ref's parent - up
    								exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($new_id, 1, 1, $t, '".addslashes($typ[2])."')", "Import with ID");
    							else
									exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($new_id, $parent, $ord, $t, '".addslashes(substr($typ[2], 0, VAL_LIM))."')", "Import with ID");
							}
							$GLOBALS["cur_parent"][$t] = $new_id;
						}
					}
#					echo $buffer;
				}
				Insert_batch("", "", "", "", "Import");
				fclose($handle);
#				print_r($GLOBALS["parents"]);print_r($GLOBALS["local_struct"]);die("$ftell=".$buffer);
#print($header);#print_r($blocks["typ"]);print_r($blocks["typ"]);print_r($GLOBALS);die();
			}
			# We might need Reference type in meta-data - add a synthetic one
			$GLOBALS["BT"]["REFERENCE"] = 0;  # Reference type
			$GLOBALS["REV_BT"][0] = "REFERENCE";
			$GLOBALS["DESC"] = isset($_REQUEST["desc"]) ? "DESC" : "";
			$GLOBALS["PG"] = isset($_REQUEST["pg"]) ? max($_REQUEST["pg"], 1) : 1;
			$sql = "SELECT CASE WHEN arrs.id IS NULL THEN a.id ELSE typs.id END t, CASE WHEN refs.id IS NULL THEN typs.t ELSE refs.t END base_typ
						, CASE WHEN refs.id IS NULL THEN typs.val ELSE refs.val END val, refs.id ref_id, arrs.id arr_id, a.val attrs, a.id
					FROM $z a, $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.t!=refs.id
							LEFT JOIN $z arrs ON refs.id IS NULL AND arrs.up=typs.id AND arrs.ord=1
					WHERE a.up=$id AND typs.id=a.t ORDER BY a.ord";
			$data_set = Exec_sql($sql, "Get all Names of Reqs of the Typ");
			$GLOBALS["no_reqs"] = mysqli_num_rows($data_set) == 0; # Check if the Type has any Reqs
			while($row = mysqli_fetch_array($data_set))
			{
				if(isset($GLOBALS["GRANTS"][$row["id"]])) # Skip barred Reqs - hide them
					if($GLOBALS["GRANTS"][$row["id"]] == "BARRED")
						continue;
#print_r($GLOBALS);die();
				$blocks[$block]["val"][] = isset($row["ref_id"]) ? FetchAlias($row["attrs"], $row["val"]) : $row["val"];
				$blocks[$block]["typ"][] = $row["t"];
				$blocks[$block]["base_typ"][] = $row["base_typ"];
				$blocks[$block]["id"][] = $id;

				$GLOBALS["attrs"][$row["t"]] = $row["attrs"];  # The template name for BUTTON
				$GLOBALS["REV_BT"][$row["t"]] = $GLOBALS["REV_BT"][$row["base_typ"]]; # Remember the base type for each Req

				if($row["arr_id"] != 0) # Remember this to simplify the request for Reqs later
				{
					$GLOBALS["REV_BT"][$row["t"]] = "ARRAY";
					$GLOBALS["HAVE_ARR"] = "";
					$GLOBALS["ARR_typs"][$row["t"]] = $GLOBALS["REV_BT"][$row["base_typ"]];
				}
				if($row["ref_id"] != 0)
				{
					$GLOBALS["HAVE_REF"] = "";
					$GLOBALS["REF_typs"][$row["t"]] = $row["ref_id"];  # Save the Typ of the referenced Object
				}
				else
				    $GLOBALS["NonREF_typs"][$row["t"]] = "";

				$GLOBALS["REQS"][$row["t"]] = $row["base_typ"]; # Store Reqs for filter constructor
				$GLOBALS["REQNAMES"][$row["t"]] = $row["val"]; # Names of reqs
				$f = $GLOBALS["FILTER"];
				if(!isset($_REQUEST["desc"]) && ($GLOBALS["ORDER_VAL"]==$row["t"])) # Revert the sort order, if needed
					$blocks[$block]["filter"][] = "$f&desc=0";
				else
					$blocks[$block]["filter"][] = $f;
			}
			if(isset($_REQUEST["csv"]))
			{
				if(!isset($GLOBALS["GRANTS"]["EXPORT"][$id]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на выгрузку объектов этого типа[EN]You do not have access to upload this type of object"));
				# First add the first column
				if(is_array($blocks[$block]["val"]))
					array_unshift($blocks[$block]["val"], $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]);
				else
					$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
				foreach ($blocks[$block]["val"] as $key => $value)
					$blocks[$block]["val"][$key] = iconv("utf-8", "windows-1251", $value);
				download_send_headers("data_export.csv");
				ob_start();
				$GLOBALS["CSV_handler"] = fopen("php://output", 'w');
				fputcsv($GLOBALS["CSV_handler"], $blocks[$block]["val"], ';');
			}
			if(isset($_REQUEST["bki"]))
			{
				if(!isset($GLOBALS["GRANTS"]["EXPORT"][$id]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на выгрузку объектов этого типа[EN]You do not have access to upload this type of object"));
				# First add the first column
				$header = Export_header($id);
#print($header);#print_r($blocks["typ"]);print_r($blocks["typ"]);print_r($GLOBALS);die();
				download_send_headers("data_export.bki");
				ob_start();
				$GLOBALS["CSV_handler"] = fopen("php://output", 'w');
				fwrite($GLOBALS["CSV_handler"], $header."DATA\r\n");
			}
			break;

		case "&delete":
		case "&export":
			if(isset($GLOBALS["GRANTS"][strtoupper(substr($block_name,1))][$id]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin") || ($GLOBALS["GLOBAL_VARS"]["user"] == $z))
				$blocks[$block]["ok"][] = "";
			break;

		case "&uni_obj_head_links":
		case "&uni_obj_head_filter_links":
		case "&uni_object_view_reqs_links":
			if($GLOBALS["lnx"] ==  1)
				$blocks[$block]["val"][] = "";
			break;

		case "&uni_obj_head_filter":
			if(isset($GLOBALS["REQS"]))  # There might be no Reqs
				foreach($GLOBALS["REQS"] as $key => $value)
				{
					$blocks[$block]["typ"][] = $key;
					$blocks[$block]["base_typ"][] = $value;
					$blocks[$block]["dd"][] = isset($GLOBALS["REF_typs"])?(isset($GLOBALS["REF_typs"][$key])?"dropdown-toggle":""):"";
					$blocks[$block]["ref"][] = isset($GLOBALS["REF_typs"])?(isset($GLOBALS["REF_typs"][$key])?$GLOBALS["REF_typs"][$key]:$key):$key;
				}
			break;

		case "&filter_val_rcm":
		case "&filter_val_dns":
		case "&filter_req_rcm":
		case "&filter_req_dns":
			$cur_typ = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			if(in_array($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"]]
						, array("DATE","NUMBER","SIGNED","DATETIME")))
			{
				$blocks[$block]["f_typ_fr"][] = "FR_".$cur_typ;
				$blocks[$block]["filter_fr"][] = isset($_REQUEST["FR_".$cur_typ]) ? str_replace(" ","",$_REQUEST["FR_".$cur_typ]) : "";
				$blocks[$block]["f_typ_to"][] = "TO_".$cur_typ;
				$blocks[$block]["filter_to"][] = isset($_REQUEST["TO_".$cur_typ]) ? str_replace(" ","",$_REQUEST["TO_".$cur_typ]) : "";
			}
			else
			{
				$blocks[$block]["f_typ"][] = "F_$cur_typ";
				$blocks[$block]["filter"][] = isset($_REQUEST["F_$cur_typ"]) ? str_replace("\"", "&#34;", $_REQUEST["F_$cur_typ"]) : "";
			}
			break;

		case "&uni_obj_all":
			if(isset($GLOBALS["HAVE_ARR"]))  # Get base types for array Reqs (to simplify the filtering later)
			{
				$data_set = Exec_sql("SELECT arr_reqs.t req_typ, base_typs.t base_typ
										FROM $z reqs, $z arr_reqs, $z base_typs
										WHERE reqs.up=$id AND arr_reqs.up=reqs.t AND base_typs.id=arr_reqs.t"
								, "Get base types for array Reqs");
				$ref_list = "";
				while($row = mysqli_fetch_array($data_set))
				{
					if($row["req_typ"] == "") # Remember the Reference types list
					{
						if($ref_list == "")
							$ref_list = $row["req_typ"];
						else
							$ref_list .= ",".$row["req_typ"];
					}
					# Remember the base type
					$GLOBALS["REV_BT"][$row["req_typ"]] = isset($GLOBALS["REV_BT"][$row["base_typ"]]) ? $GLOBALS["REV_BT"][$row["base_typ"]] : "SHORT";
				}
			}
# Prepare Filter conditions
			$joins = $filter_tables = $filter_cond = $GLOBALS["distinct"] = $filter_by_id = $parent_cond = "";
			$cur_typ = $id;
			$cur_base_typ = $blocks["&main"]["CUR_VARS"]["typ"];
			$filter_by_id_off = FALSE;
			$GLOBALS["where"] = $GLOBALS["join"] = $GLOBALS["join_cond"] = "";
			if(isset($_REQUEST["F_U"]) && $blocks["&main"]["CUR_VARS"]["parent_obj"])
				$filter_cond .= " AND vals.up=".(int)$_REQUEST["F_U"]." ";
			else
				$parent_cond = " AND vals.up!=0 ";  # By default for Root's children

			foreach($_REQUEST as $key => $value)
				if(($value != "") && (preg_match("/(F\_|FR\_|TO\_)/", $key)))
					$GLOBALS["CONDS"][substr($key, strpos($key, "_")+1)][substr($key, 0, strpos($key, "_"))] = $value;

			if(isset($GLOBALS["CONDS"]))
				foreach($GLOBALS["CONDS"] as $key => $value)
				{
					if($key == "U") # Filter on Up
						continue;
					elseif(($key == "I") && ($value["F"] != 0)) # Filter on ID
						$filter_by_id = " AND vals.id=".(int)$value["F"]." ";
					elseif($key != 0) # Not U or I
					{

						$filter_by_id_off = TRUE; # Clear $filter_by_id in case we have other conditions
						Construct_WHERE($key, $value, $cur_typ, $key);
					}
				}
#print_r($GLOBALS);die();
			$filter_tables = $GLOBALS["join"];
			$filter_cond .= $GLOBALS["where"];

			if($blocks["&main"]["CUR_VARS"]["parent_obj"]	# In case we have a dependent type
					&& !$f_u)	#  and no parent filter set - cut off meta records
				$filter_tables = "JOIN $z par ON par.id=vals.up AND par.up!=0 $filter_tables";

			$GLOBALS["REQS"][$cur_typ] = $cur_base_typ; # Add the Object value to the Reqs list
			foreach($GLOBALS["REQS"] as $req => $base)
				if(isset($GLOBALS["GRANTS"]["mask"][$req]))
				{
					foreach($GLOBALS["GRANTS"]["mask"][$req] as $mask) # Apply all masks
					{
						$GLOBALS["where"] = $GLOBALS["join"] = $GLOBALS["CONDS"] = "";
						Construct_WHERE($req, array("F" => $mask), $cur_typ, $req);
						if(isset($reqs_granted))
							$reqs_granted .= " OR ".substr($GLOBALS["where"], 4);
						else
							$reqs_granted = substr($GLOBALS["where"], 4);
						if(strpos($filter_tables, "$z a$req") === FALSE) # Is the table joined already?
							$filter_tables .= $GLOBALS["join"];
					}
					$filter_cond .= " AND ($reqs_granted) ";
					unset($reqs_granted);
				}
#print_r($GLOBALS);die($filter_tables."<br>$filter_cond");

			if(!strlen($filter_cond) && isset($_REQUEST["f_show_all"]))
				$filter_cond = " ";
#				$filter_cond = " and vals.id IS NOT NULL"; 
						
			$tmp_base_typ = $cur_base_typ;
			if($GLOBALS["ORDER_VAL"] === "val")
				$order = "vals.val";
			elseif(($GLOBALS["ORDER_VAL"] != 0) && ($GLOBALS["REV_BT"][$GLOBALS["ORDER_VAL"]] != "ARRAY"))
			{
				$tmp_base_typ = $GLOBALS["ORDER_VAL"];
				$order = "a$tmp_base_typ.val";
				if(strpos($filter_tables, "a$tmp_base_typ") === FALSE) # We don't have this table in the FROM clause - get it
				{
					if(isset($GLOBALS["REF_typs"][$tmp_base_typ]))
						$filter_tables = " LEFT JOIN ($z r$tmp_base_typ JOIN $z a$tmp_base_typ) "
										."ON r$tmp_base_typ.up=vals.id AND r$tmp_base_typ.t=a$tmp_base_typ.id AND r$tmp_base_typ.val='$tmp_base_typ' "
										."AND a$tmp_base_typ.t=".$GLOBALS["REF_typs"][$tmp_base_typ].$filter_tables;
					else
						$filter_tables = " LEFT JOIN $z a$tmp_base_typ ON a$tmp_base_typ.up=vals.id
																	AND a$tmp_base_typ.t=$tmp_base_typ".$filter_tables;
				}
			}
			else
				$order = "";	# Clean the ORDER clause for potentially vast result sets

			if(!$filter_by_id_off)  # Implement ID filter in case there are no other conditions
				$filter_cond .= $filter_by_id;

			if(strlen($order))
				if(($GLOBALS["REV_BT"][$tmp_base_typ] == "NUMBER") || ($GLOBALS["REV_BT"][$tmp_base_typ] == "SIGNED"))
					$order = "$order + 0.0";  # Convert all numeric values to number

			$desc = $GLOBALS["DESC"];  # Set the Descending order, if required
			$pg = (DEFAULT_LIMIT * ($GLOBALS["PG"] - 1)).",";

			$vals_ord = "";
			if($GLOBALS["parent_id"] > 1)
				$vals_ord = ", vals.ord val_ord"; # We need to get the order of the array elements

			if(($GLOBALS["parent_id"] > 1) && ($GLOBALS["ORDER_VAL"] === 0))
				$order = " ORDER BY vals.ord";  # Arrays are to be sorted by their order by default
			elseif(strlen($order))
				$order = " ORDER BY $order $desc";

			if(!isset($_REQUEST["csv"]) && !isset($_REQUEST["bki"]))	# Retrieve all data in case of XLS export
				$order .= " LIMIT $pg ".DEFAULT_LIMIT;

			if(in_array($GLOBALS["REV_BT"][$cur_base_typ], array("CHARS", "MEMO", "FILE", "HTML")))
				$tails = ", (SELECT COUNT(*) FROM $z tails WHERE tails.up=vals.id AND tails.t=0) tails";

			$distinct = $GLOBALS["distinct"];
			# Delete the selection and then retrieve the data again 
			if(isset($_REQUEST["_m_del_select"]))
			{
				if(!isset($GLOBALS["GRANTS"]["DELETE"][$cur_typ]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на массовое удаление объектов этого типа[EN]You do not have access to delete this type of object in bulk"));
	            include_once "batchdelete.php";
				# Don't drop those referenced from somewhere
				$data_set = Exec_sql("SELECT $distinct vals.id FROM $z vals	LEFT JOIN $z refr ON refr.t=vals.id /*AND !length(refr.val)*/ $filter_tables
										WHERE vals.t=$cur_typ $parent_cond $filter_cond AND refr.id IS NULL"
									, "Get filtered Objs set to delete");
				while($row = mysqli_fetch_array($data_set))
					BatchDelete($row["id"]);
				BatchDelete(""); // Flush batch
				header("Location: /$z/object/$id/?".$GLOBALS["FILTER"]);
				myexit();
			}
			$data_set = Exec_sql("SELECT $distinct vals.id, vals.t, vals.val $vals_ord ". (isset($tails) ? $tails : "")
			                        ." FROM $z vals $filter_tables WHERE vals.t=$cur_typ $parent_cond $filter_cond $order"
			                    , "Get filtered Objs set");
			$blocks["object_count"] = mysqli_num_rows($data_set);
			$i = 0; # Row count for Array elements
			while($row = mysqli_fetch_array($data_set))
			{
    			if(isset($_REQUEST["bki"]))
    			{
    				$str = Export_reqs($id, $row["id"], $row["val"]);
    				fwrite($GLOBALS["CSV_handler"], $str);
#{print($head_str);}#print_r($GLOBALS["data"]);print_r($GLOBALS);die();}
    				continue;
    			}
				if((isset($row["tails"]) ? $row["tails"] : 0) > 0)
				{
					if(isset($_REQUEST["full"]))
						$v = htmlspecialchars(Get_tail($row["id"], $row["val"]));
					else
						$v = htmlspecialchars($row["val"]."...");
				}
				else
					$v = htmlspecialchars($row["val"]);

    			if(isApi())
    			{
    				if($f_u > 1)  # Fetch the order
            		    $GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["ord"] = $row["val_ord"];
        		    $GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["id"] = $row["id"];
        		    $GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["val"] = Format_Val_View($cur_base_typ, $v, $row["id"]);
                    if(in_array($GLOBALS["REV_BT"][$cur_base_typ], array("REPORT_COLUMN", "GRANT")))
            		    $GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["ref"] = $v;
        		    $GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["base"] = $row["t"];
            		$i++;
    			}

				$blocks[$block]["id"][] = $row["id"];
				$blocks[$block]["ord"][] = $i;
				$blocks[$block]["align"][] = Get_Align($cur_base_typ);
				if(trim($v) == "")	# In case no chars except spaces, make it a single space to let the A-tag work
					$v = "&nbsp;";
				if(isset($_REQUEST["bki"]))	# Export data AS IS
					$blocks[$block]["val"][] = $row["val"];
				else
					$blocks[$block]["val"][] = Format_Val_View($cur_base_typ, $v, $row["id"]);
				if($f_u > 1)  # Fetch the order
					$blocks[$block]["val_ord"][] = $row["val_ord"];
			}
			if(isset($_REQUEST["bki"]))
			    break;
			if((($blocks["object_count"] == DEFAULT_LIMIT)  # There could be more results beyond the DEFAULT_LIMIT
					|| ($GLOBALS["PG"] > 1))	# The last page has less than DEFAULT_LIMIT rows
				&& strlen($filter_cond))
			{
				if(strlen($filter_tables))
				{
					if($row = mysqli_fetch_array(Exec_sql("SELECT COUNT($distinct vals.id) cnt FROM $z vals $filter_tables
									WHERE vals.t=$cur_typ $parent_cond $filter_cond", "Get number of filtered Objs")))
						$blocks["object_count_total"] = $row["cnt"];
				}
				else
				{
					$row = mysqli_fetch_array(Exec_sql("SELECT COUNT(1) cnt FROM $z vals WHERE t=$cur_typ $filter_cond AND up!=0", "Get number of Objs"));
					//$n = $row["cnt"];
					//$row = mysqli_fetch_array(Exec_sql("SELECT COUNT(1) cnt FROM $z vals WHERE t=$cur_typ AND up=0", "Get number of metas"));
					$blocks["object_count_total"] = $row["cnt"];
				}
			}
			elseif(strlen($filter_cond))
				$blocks["object_count_total"] = $blocks["object_count"];
#print_r($blocks);die();
			break;

		case "&head_ord":
		case "&head_ord_n":
		case "&head_move_n_delete":
			if($f_u > 1)
				$blocks[$block]["filler"][] = "";
			break;

		case "&move_n_delete":
			if($f_u > 1)
				$blocks[$block]["id"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			break;

		case "&ord":
			if($f_u > 1)
				$blocks[$block]["ord"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val_ord"];
			break;

		case "&move":  # Hide movers in case of sorting (original order's not adequate)
			if(($f_u > 1) && ($GLOBALS["ORDER_VAL"] == 0))
				$blocks[$block]["id"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			break;
			
		case "&no_page": # Inform of the non-complete resultset
			if(!isset($blocks["object_count_total"]) && ($blocks["object_count"] == DEFAULT_LIMIT))
			{
				$blocks[$block]["limit"][] = DEFAULT_LIMIT;
				$blocks[$block]["id"][] = $id;
				$blocks[$block]["f_u"][] = $f_u;
				$blocks[$block]["lnx"][] = (isset($_REQUEST["lnx"]) ? "&lnx=".(int)$_REQUEST["lnx"] : "")
				                            .($GLOBALS["ORDER_VAL"] === 0 ? "" : "&order_val=".$GLOBALS["ORDER_VAL"]);
			}
			break;

		case "&uni_obj_pages":
			if(isset($_REQUEST["csv"]) || isset($_REQUEST["bki"]))
			{
				fclose($GLOBALS["CSV_handler"]);
				echo ob_get_clean();
				die();
			}
			if(isset($blocks["object_count_total"]))
			{
				$pages = ceil($blocks["object_count_total"] / DEFAULT_LIMIT);
				$blocks[$block]["val"][] = $blocks["object_count_total"];
				$blocks[$block]["pages"][] = $pages;

				$last_dig = $blocks["object_count_total"]%10;
				if(($last_dig >= 5) || ($last_dig == 0))  # Set the proper ending
					$blocks[$block]["ending"][] = t9n("[RU]ей[EN]s");
				elseif($blocks["object_count_total"] == 1)
					$blocks[$block]["ending"][] = t9n("[RU]ь[EN]");
				elseif(($blocks["object_count_total"]%100 > 14) || ($blocks["object_count_total"]%100 < 5))
				{
					if($last_dig == 1)	# %1, %21, %31...
						$blocks[$block]["ending"][] = t9n("[RU]ь[EN]s");
					else	# %2, %3, %4
						$blocks[$block]["ending"][] = t9n("[RU]и[EN]s");
				}
				else	# %11, %12, %13, %14
					$blocks[$block]["ending"][] = t9n("[RU]ей[EN]s");

				if($pages > 1)
					$blocks[$block]["delim"][] = ":";  # Delimiter in case of multi-pages
				else
					$blocks[$block]["delim"][] = ".";  # One page only

#print_r($blocks);die($blocks["object_count_total"]);
			}
			break;
			
		case "&page": # Construct the Pages navigation bar
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["pages"] > 1)
			{
				if($GLOBALS["PG"] != 1)  # Link to the Previous page
				{
					$blocks[$block]["page"][] = $GLOBALS["PG"] - 1;
					$blocks[$block]["val"][] = " < ";
					$blocks[$block]["class"][] = "";
				}
					
				$pages = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["pages"];
				$i = 1;
				while($i <= $pages) # List available pages
				{
					if($i == $GLOBALS["PG"])  # Current page's link is inactive
						$blocks[$block]["class"][] = "active";
					else
						$blocks[$block]["class"][] = "";
					$blocks[$block]["page"][] = $i; # Page number
					$blocks[$block]["val"][] = $i++; # Page link appearance ("..." for the gap between pages)
					
					if(($i > 3) && ($i < $pages - 2))  # Show first 5 and last 5 pages
						if(abs($i - $GLOBALS["PG"]) > 1) # also show three pages nearest to the current one
						{
							$blocks[$block]["val"][] = ".&nbsp;.&nbsp;."; # Link to the middle of the gap
							$blocks[$block]["class"][] = "";
							if($i < $GLOBALS["PG"])
							{
								$i = $GLOBALS["PG"] - 1;
								$blocks[$block]["page"][] = round((3 + $i) / 2);
							}
							else
							{
								$blocks[$block]["page"][] = round(($pages - 2 + $i) / 2);
								$i = $pages - 2;
							}
						}
				}
				if($GLOBALS["PG"] != $pages)  # Link to the Next page 
				{
					$blocks[$block]["page"][] = $GLOBALS["PG"] + 1;
					$blocks[$block]["val"][] = " > ";
					$blocks[$block]["class"][] = "";
				}
				if(isApi())
			        $GLOBALS["GLOBAL_VARS"]["api"]["pages"] = $blocks[$block]["val"];
			}
			break;

		case "&page_href":
			if($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] != $GLOBALS["PG"])
			{
				$blocks[$block]["filter"][] = $GLOBALS["FILTER"].(isset($_REQUEST["lnx"]) ? "&lnx=".(int)$_REQUEST["lnx"] : "")
											.($GLOBALS["ORDER_VAL"] === 0 ? "" : "&order_val=".$GLOBALS["ORDER_VAL"]
												.(isset($_REQUEST["desc"]) ? "&desc=1" : ""));
#print_r($GLOBALS);die();
				$blocks[$block]["id"][] = $id;
			}
			break;

		case "&uni_object_view_reqs":
			$parent_id = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			if($GLOBALS["no_reqs"])  # Return in case there's no Reqs
			{
				if(isset($_REQUEST["csv"]))	# Export the first column
					fputcsv($GLOBALS["CSV_handler"], array(iconv("utf-8", "windows-1251", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"])), ';');
				break;
			}
			if($GLOBALS["lnx"] == 1)
				$flags = "&lnx=1";
			else
				$flags = "";
			if(isset($GLOBALS["HAVE_ARR"]))
				$sql = "SELECT CASE WHEN typs.up=0 THEN 0 ELSE reqs.id END id, CASE WHEN typs.up=0 THEN 0 ELSE reqs.val END val
							, typs.id t, typs.up, typs.val refr, count(1) arr_num";
			else
				$sql = "SELECT reqs.id, reqs.val, typs.id t, typs.up, typs.val refr";
			$sql .= " FROM $z reqs JOIN $z typs ON typs.id=reqs.t WHERE reqs.up=$parent_id";
			if(isset($GLOBALS["HAVE_ARR"]))
				$sql .=	" GROUP BY val, id, t, refr";
			$data_set = Exec_sql($sql, "Get all Object reqs");

			while($row = mysqli_fetch_array($data_set))
				if(isset($GLOBALS["NonREF_typs"][$row["t"]]))
				{
					$rows[$row["t"]]["id"] = $row["id"];
					$rows[$row["t"]]["val"] = $row["val"];
					$rows[$row["t"]]["arr_num"] = isset($row["arr_num"]) ? $row["arr_num"] : "";
				}
				else # It is a Reference
				{
					$rows[$row["val"]]["id"] = $row["id"];
					$rows[$row["val"]]["val"] = $row["refr"];
					$rows[$row["val"]]["ref_id"] = $row["t"];
				}
			$type_order = 0;
			foreach($GLOBALS["REQS"] as $key => $value)
			{
#print_r($GLOBALS);print_r($rows);die();
				if($key == $id)	# The last Req is the object itself
					break;
				elseif(isset($rows[$key]))
					$row = $rows[$key];
				else
					$row = array("t" => $key);
#print_r($row);
				if(isset($GLOBALS["GRANTS"][$key])) # Skip barred Reqs - hide them
					if($GLOBALS["GRANTS"][$key] == "BARRED")
						continue;
				$val = isset($row["val"]) ? $row["val"] : "";
				$literal_typ = $GLOBALS["REV_BT"][$key];
				$base_typ = isset($GLOBALS["BT"][$literal_typ]) ? $GLOBALS["BT"][$literal_typ] : $GLOBALS["BT"]["SHORT"];

				$req_id = isset($row["id"]) ? $row["id"] : 0;
				if(isApi())
    			{
    			    if(!isset($GLOBALS["GLOBAL_VARS"]["api"]["order"][$type_order]))
    			    {
    			        $GLOBALS["GLOBAL_VARS"]["api"]["req_base"][$key] = $literal_typ;
    			        $GLOBALS["GLOBAL_VARS"]["api"]["req_type"][$key] = $GLOBALS["REQNAMES"][$key];
    			        $GLOBALS["GLOBAL_VARS"]["api"]["req_order"][$type_order] = $key;
    			        $type_order++;
    			    }

					if(isset($GLOBALS["ARR_typs"][$key])) # Array of linked values
						$v = $row["arr_num"];
					elseif(isset($row["ref_id"]))  # Reference
					{
						$v = $val;
						$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$parent_id]["ref_$key"] = $GLOBALS["REF_typs"][$key].":".$row["ref_id"];
					}
					elseif($req_id > 0) # We got some value
					{
						if(in_array($literal_typ, array("CHARS", "MEMO", "HTML")))
							$v = str_replace("\n", " ", Get_tail($req_id, $val));
						elseif($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$v = Format_Val_View($base_typ, Get_tail($req_id, $val), $req_id);
						else
							$v = Format_Val_View($base_typ, $val);
					}
					elseif($literal_typ == "BUTTON")
						$v = "***";
					else
					    $v = $val;
    			    if($v != "")
    					$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$parent_id][$key] = $v;
    			}
				if(isset($_REQUEST["csv"]))
				{
					if(isset($GLOBALS["ARR_typs"][$key])) # Array of linked values
						$blocks[$block]["val"][] = $row["arr_num"];
					elseif(isset($row["ref_id"]))  # Reference
						$blocks[$block]["val"][] = $val;
					elseif($req_id > 0) # We got some value
					{
						if(in_array($literal_typ, array("CHARS", "MEMO", "HTML")))
							$blocks[$block]["val"][] = str_replace("\n", " ", Get_tail($req_id, $val));
						elseif($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$blocks[$block]["val"][] = Format_Val_View($base_typ, Get_tail($req_id, $val), $req_id);
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, $val);
					}
					elseif($literal_typ == "BUTTON")
						$blocks[$block]["val"][] = "***";
					else
						$blocks[$block]["val"][] = "";
				}
				else
				{
					$blocks[$block]["align"][] = Get_Align($base_typ);
					if(isset($GLOBALS["ARR_typs"][$key])) # Array of linked values
						$blocks[$block]["val"][] = "<A HREF=\"/$z/object/$key/?F_U=$parent_id$flags\">(".(isset($row["arr_num"])?(int)$row["arr_num"]:0).")</A>";
					elseif(isset($row["ref_id"]))  # Reference
					{
						if(Grant_1level($key)) # Do we have access to this Type
							$blocks[$block]["val"][] = "<A HREF=\"/$z/object/".$GLOBALS["REF_typs"][$key]."/?F_I=".$row["ref_id"]."$flags\">".Format_Val_View($base_typ, htmlspecialchars($val))."</A>";
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, htmlspecialchars($val));
					}
					elseif($req_id > 0) # We got some value
					{
						if(in_array($literal_typ, array("CHARS", "MEMO", "HTML")))
						{
							if(isset($_REQUEST["full"]) && (mb_strlen($val) == VAL_LIM))
								$blocks[$block]["val"][] = htmlspecialchars(Get_tail($req_id, $val));
							elseif(mb_strlen($val) == VAL_LIM)
								$blocks[$block]["val"][] = str_replace("\n", " ", htmlspecialchars("$val ..."));
							else
								$blocks[$block]["val"][] = str_replace("\n", " ", htmlspecialchars($val));
						}
						elseif($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$blocks[$block]["val"][] = Format_Val_View($base_typ, Get_tail($req_id, $val), $req_id);
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, htmlspecialchars($val));
					}
					elseif($literal_typ == "BUTTON")
						$blocks[$block]["val"][] = " <A HREF=\"/$z/".str_replace("[ID]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"]
														, str_replace("[VAL]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]
																		, $GLOBALS["attrs"][$key]))."\">***</A>";
					else
						$blocks[$block]["val"][] = "";
				}
			}
			if(isApi())
				$GLOBALS["GLOBAL_VARS"]["api"]["&object_reqs"][$parent_id] = $blocks[$block]["val"];
#if($parent_id == 227)
#{print_r($GLOBALS);print_r($rows);print_r($blocks[$block]);die();}
			if(isset($_REQUEST["csv"]))
			{	# First add the first column
				array_unshift($blocks[$block]["val"], $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]);
				foreach ($blocks[$block]["val"] as $key => $value)
					$blocks[$block]["val"][$key] = iconv("utf-8", "windows-1251", $value);
				fputcsv($GLOBALS["CSV_handler"], $blocks[$block]["val"], ';');
				unset($blocks[$block]["val"]);
			}
			break;

		case "&reqs_links":
			if($GLOBALS["lnx"] == 1)
				foreach($GLOBALS["links"] as $key => $value)
					if(Check_Grant($value, $key, "READ", FALSE))
					{
						$blocks[$block]["value"][] = $value;
						$blocks[$block]["links_typ"][] = $key;
						$blocks[$block]["key"][] = $GLOBALS["links_val"][$key];
					}
			break;
			
		case "&buttons":
			if(isset($blocks["BUTTONS"]))
				foreach($blocks["BUTTONS"] as $key => $value)
				{
					$blocks[$block]["val"][] = $key;
					$blocks[$block]["attrs"][] = str_replace("[ID]", $GLOBALS["cur_id"]
														, str_replace("[VAL]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"], $value));
				}
			break;

		case "&uni_report":
			if(!isset($GLOBALS["STORED_REPS"][$id]["header"]))
				if(Check_Grant($id, 0, "READ"))
				{
				    include_once "include/compile_report.php";
					Compile_Report($id, TRUE, TRUE);
				}
			$blocks[$block]["val"][] = $GLOBALS["STORED_REPS"][$id]["header"];
			break;

		case "&uni_report_head":
			if(isset($GLOBALS["STORED_REPS"][$id]["head"]))
				foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
					if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # Not a hidden column
						$blocks[$block]["val"][] = $GLOBALS["STORED_REPS"][$id]["head"][$key];
#if($block="mybar")
#{print_r($blocks[$block]);}
			break;

		case "&uni_report_filter":
			if(isset($GLOBALS["STORED_REPS"][$id]["head"]))
			{
				foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
					if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]) # Not a hidden column
					    && isset($GLOBALS["STORED_REPS"][$id]["types"][$key])) # and not the Execute link
					{	
					    $value=str_replace(" ", "_", $value);
					    $blocks[$block]["col"][] = $value;
						$blocks[$block]["fr_val"][] = isset($_REQUEST["FR_$value"]) ? (strlen($_REQUEST["FR_$value"]) ? $_REQUEST["FR_$value"] 
                            : (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]) ? $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key] : "")) : "";
						$blocks[$block]["to_val"][] = isset($_REQUEST["TO_$value"]) ? (strlen($_REQUEST["TO_$value"]) ? $_REQUEST["TO_$value"]
    					    : (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key]) ? $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key] : "")) : "";
					}
			}
#{print_r($GLOBALS);die($id);}
			break;

		case "&uni_report_data":
			if($GLOBALS["STORED_REPS"][$id]["rownum"])
				$blocks[$block]["data"] = array_fill(0, $GLOBALS["STORED_REPS"][$id]["rownum"], "");
#{print_r($GLOBALS);die($id);}
			break;

		case "&uni_report_column":
#print_r($GLOBALS["STORED_REPS"][$id]["names"]);die();
			foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
			{
#				$blocks[$block]["rowspan"][] = "1";
				if(isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]))
    				$blocks[$block]["align"][] = Get_Align($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]]);
    			else
    				$blocks[$block]["align"][] = "LEFT";
				$blocks[$block]["val"][] = array_shift($blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]]);
			}
			break;

		case "&uni_report_totals":
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL]))
				if(strlen(implode($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL])))	# Any totals?
					$blocks[$block]["totals"][] = "";
			break;

		case "&uni_report_column_total":
#print_r($blocks);print_r($GLOBALS); die();
			if(isset($blocks["col_totals"][$id]))
				foreach($blocks["col_totals"][$id] as $key => $value)
					$blocks[$block]["val"][] = $value."&nbsp";
			break;

		case "&login":
			$blocks[$block]["save"][] = isset($_REQUEST["save"]) ? "CHECKED" : "";
			$blocks[$block]["change"][] = isset($_REQUEST["change"]) ? "CHECKED" : "";
#print_r($GLOBALS); die();
			break;

		case "&dir_admin":
            $grant = RepoGrant();
            if($grant == "BARRED")
            	die(t9n("[RU]Недостаточно прав для доступа к этому рабочему месту[EN]Insufficient permissions to access this workplace"));
			$blocks[$block]["folder"][] = isset($_REQUEST["download"]) ? "download" : "templates";
			$blocks[$block]["another"][] = isset($_REQUEST["download"]) ? "templates" : "download";
			if($blocks[$block]["folder"][0] == "download")
				$path = "download/$z";
			else
				$path = "templates/custom/$z";
			if(!file_exists($path))
				mkdir($path);
			$add_path = isset($_REQUEST["add_path"]) ? $_REQUEST["add_path"] : "";
			if(strpos($add_path, "..") !== false)
            	$add_path = "";
			if(isset($_REQUEST["gf"]))
				if((strpos($_REQUEST["gf"], "..") === false) && file_exists($path.$add_path."/".$_REQUEST["gf"]))
				{
					$file = $path.$add_path."/".$_REQUEST["gf"];
					if (ob_get_level())
					  ob_end_clean();
					header('Content-Description: File Transfer');
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename=' . basename($file));
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($file));
					readfile($file);
					exit;
				}
				else
				    die(t9n("[RU]Файл не найден[EN]File not found"));
			if(is_dir($path.$add_path))
				$path .= $add_path;
			else
				$add_path = "";
			$blocks[$block]["path"][] = $path;
			$blocks[$block]["add_path"][] = $add_path;
			$fname = isset($_REQUEST["dir_name"])?$_REQUEST["dir_name"]:"";
			if(isset($_REQUEST["mkdir"]))
			{
			    if($grant != "WRITE")
                	die(t9n("[RU]Недостаточно прав для создания каталогов[EN]Insufficient permissions to create directories"));
				check();
				if(preg_match(DIR_MASK, $fname))
				{
					if(is_dir($path."/".$fname))
						die(t9n("[RU]Такой каталог уже существует![EN]This directory already exists!".BACK_LINK));
					mkdir($path."/".$fname);
					header("Location: /$z/dir_admin/?".$blocks[$block]["folder"][0]."=1&add_path=$add_path");
					myexit();
				}
				else
					die(t9n("[RU]Недопустимое имя каталога.[EN]The directory name is invalid".BACK_LINK));
			}
			if(isset($_REQUEST["touch"]))
			{
			    if($grant != "WRITE")
                	die(t9n("[RU]Недостаточно прав для создания файлов[EN]Insufficient permissions to create files"));
				check();
				if(preg_match(FILE_MASK, $fname))
				{
                    BlackList(substr(strrchr($fname, '.'), 1)); # Check the file extension
                    if(strpos($fname,".") === false)
                        $fname .= ".html";
                    if(is_file($path."/".$fname))
						die(t9n("[RU]Такой файл ($fname) уже существует![EN]File ($fname) already exists!".BACK_LINK));
					touch($path."/".$fname);
					header("Location: /$z/dir_admin/?".$blocks[$block]["folder"][0]."=1&add_path=$add_path");
					myexit();
				}
				else
					die(t9n("[RU]Недопустимое имя файла.[EN]Invalid file name".BACK_LINK));
			}
			$warning = "";
			# File upload
			if(isset($_REQUEST["upload"]))
			    if($grant != "WRITE")
                	die(t9n("[RU]Недостаточно прав для загрузки файлов[EN]Not grants to upload files"));
                else
				{
					check();
					foreach($_FILES as $value)
						if(strlen($value["name"]) > 0)
						{
							BlackList(substr(strrchr($value["name"], '.'), 1)); # Check the file extension
							if(file_exists($path."/".$value["name"]))
								if(isset($_REQUEST["rewrite"]))
									$warning = t9n("[RU] (перезаписан)[EN] (rewritten)");
								else
									die(t9n("[RU]Такой файл (".$value["name"].") уже существует![EN]File (".$value["name"].") already exists!".BACK_LINK));
							if(!move_uploaded_file($value['tmp_name'], $path."/".$value["name"]))
								die (t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
							$warning = t9n("[RU]Файл [EN]File ").$value["name"].t9n("[RU] загружен[EN] uploaded").$warning;
							header("Location: /$z/dir_admin/?".$blocks[$block]["folder"][0]."=1&add_path=$add_path&warning=$warning");
							myexit();
						}
				}
			# Delete files and folders
			if(isset($_POST["delete"]))
			{
			    if($grant != "WRITE")
                	die(t9n("[RU]Недостаточно прав для удаления файлов[EN]Insufficient permissions to delete files"));
				check();
				if(is_array($_POST["del"]))
					foreach($_POST["del"] as $value)
						if(strlen($value))
							RemoveDir($path."/".$value);
#print_r($GLOBALS); die("is_dir($path./.$value) =".is_dir($path."/".$value));
				header("Location: /$z/dir_admin/?".$blocks[$block]["folder"][0]."=1&add_path=$add_path");
				myexit();
			}
			# Make the directories and files list for the current folder
			if($dir = @opendir($path))
			{
				$GLOBALS["dir_list"] = $GLOBALS["file_list"] = $GLOBALS["file_size"] = $GLOBALS["file_time"] = array();
				while(($file = readdir($dir)) !== false)
					if($file != '..' && $file != '.')
					{
						if(is_dir($path."/".$file))
							$GLOBALS["dir_list"][] = $file;
						else
							$GLOBALS["file_list"][] = $file;
					}
				closedir($dir);
				sort($GLOBALS["dir_list"]);
				sort($GLOBALS["file_list"]);
				$blocks[$block]["files"][] = count($GLOBALS["file_list"]);
				$blocks[$block]["folders"][] = count($GLOBALS["dir_list"]);
				foreach($GLOBALS["file_list"] as $value)
				{
					$GLOBALS["file_size"][] = NormalSize(filesize($path."/".$value));
					$GLOBALS["file_time"][] = date ("d.m.Y H:i:s", filemtime($path."/".$value));
				}
			}
			break;
			
		case "&pattern":
			$add_path = "";
			foreach(explode("/", substr($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["add_path"], 1)) as $val)
			{
				$add_path .= "/$val";
				$blocks[$block]["path"][] = $add_path;
				$blocks[$block]["name"][] = $val;
			}
			break;

		case "&file_list":
			$blocks[$block]["size"] = $GLOBALS["file_size"];
			$blocks[$block]["time"] = $GLOBALS["file_time"];
			$blocks[$block]["name"] = $GLOBALS["file_list"];
			break;

		case "&dir_list":
			$blocks[$block]["name"] = $GLOBALS["dir_list"];
#print_r($GLOBALS);
			break;

		default:
			$rep_id = 0;	# Get Report ID to fetch the data
			if(isset($GLOBALS["STORED_REPS"][$block]["_rep_id"]))
				$rep_id = $GLOBALS["STORED_REPS"][$block]["_rep_id"];
			elseif($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='".addslashes($block_name)."' AND t=".REPORT, "Get Report's ID")))
				$rep_id = $row[0]; # Save Report ID
			elseif(is_numeric($block_name) && ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE id='$block_name' AND t=".REPORT, "Check Report's ID"))))
				$rep_id = $block_name; # Save the direct Report ID

			$GLOBALS["STORED_REPS"][$block]["_rep_id"] = $rep_id;	# No report found

			if($rep_id)	# If we got a Report
			{
			    include_once "include/compile_report.php";
				Compile_Report($rep_id, $exe);
				if(!$exe)
					return;
				$bak_id = $id;
				$id = $rep_id;
				if(isset($_REQUEST["obj"]) && ($_REQUEST["obj"] != 0))
					$obj = $_REQUEST["obj"];

				$id = $rep_id;
				Get_block_data("&uni_report");
				Get_block_data("&uni_report_head");
				Get_block_data("&uni_report_data");
#{print_r($GLOBALS); die($id);}
				if(isset($blocks["_data_col"][$id]) && isset($GLOBALS["STORED_REPS"][$id]["head"]))
					foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
						if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # Not hidden field
    					    if($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "HTML")
    						    $blocks[$block][strtolower($value)] = array_shift($blocks["_data_col"][$id]);
    						else
        						$blocks[$block][strtolower($value)] = str_replace("\n", "<BR/>", array_shift($blocks["_data_col"][$id]));

#print_r($GLOBALS);die($block."!");
				$id = $bak_id;
			}
			break;
	}
}
# Check grant to the repository
function RepoGrant()
{
	global $z;
    if(isset($GLOBALS["GRANTS"][$GLOBALS["BT"]["FILE"]])) # The grant is set explicitly
        return $GLOBALS["GRANTS"][$GLOBALS["BT"]["FILE"]];
	elseif(($z == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
        return "WRITE"; # We are the admin
    return "BARRED";
}
?>
