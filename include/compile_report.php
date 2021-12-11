<?php
define("REP_JOIN", 44);
define("REP_HREFS", 95);
define("REP_URL", 97);
define("REP_LIMIT", 134);
define("REP_IFNULL", 113);
define("REP_WHERE", 262);
define("REP_ALIAS", 265);
define("REP_JOIN_ON", 266);

define("REP_COL_FORMAT", 29);
define("REP_COL_ALIAS", 58);
define("REP_COL_FUNC", 63);
define("REP_COL_TOTAL", 65);
define("REP_COL_NAME", 100);
define("REP_COL_FORMULA", 101);
define("REP_COL_FROM", 102);
define("REP_COL_TO", 103);
define("REP_COL_HAV_FR", 105);
define("REP_COL_HAV_TO", 106);
define("REP_COL_HIDE", 107);
define("REP_COL_SORT", 109);
define("REP_COL_SET", 132);

Exec_sql("SET SESSION optimizer_search_depth = 9", "Search depth");

# Apply hint for JOIN of the Ref requisites
function HintNeeded($k, $id)
{	# We might get filter set either in the report definition or online
    if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$k]))
    {
        $str = str_replace(" ", "_", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$k]);
    	if(isset($_REQUEST["FR_$str"]))
    		$c = $_REQUEST["FR_$str"];
    	elseif(isset($_REQUEST["TO_$str"]))
    		$c = $_REQUEST["TO_$str"];
    }
	if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$k]))
		$c = $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$k];
	elseif(isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$k]))
		$c = $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$k];
	if(isset($c))
    	if(strlen($c))
    		if((substr($c,0,1)!="!") && (substr($c,0,1)!="%"))
    		{
    	        trace("Hint NOT needed for $k: $c");
    			return false;
    		}
    trace("HintNeeded for $k");
	return true;
}
function isRef($id, $par, $typ)
{
	if(isset($GLOBALS["STORED_REPS"][$id]["ref_typ"][$typ])) # Does our parent have refs?
		return $GLOBALS["STORED_REPS"][$id]["ref_typ"][$typ];	# seek reference
	return false;
}
function Compile_Report($id, $exe=TRUE, $check=FALSE) # $exe means we must retrieve data at last, not just prep the sql
{
	global $blocks, $obj, $z, $args;
#{print_r($GLOBALS);die($id);}
	if(!isset($GLOBALS["STORED_REPS"][$id]["sql"]))
	{
# Construct the report head
		$GLOBALS["STORED_REPS"][$id]["params"] = $GLOBALS["STORED_REPS"][$id] = Array();
		if($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=$id", "Get Report Header")))
			$GLOBALS["STORED_REPS"][$id]["header"] = $row["val"];
		else
			die_info("Report #$id was not found");
		if($check)
    		Check_Val_granted(REPORT, $row["val"]);
		$tables = $conds = $field_names = $GLOBALS["CONDS"] = $GLOBALS["STORED_REPS"][$id][REP_JOIN] = array();
		$s = "_";   # Delimiter for numeric table names: arr$par$s$rec -> arr777_555
		$tailed = " CHARS MEMO FILE HTML";
		$aggr_funcs = array("AVG", "COUNT", "MAX", "MIN", "SUM"); # Aggregative MySQL functions list
		$distinct = "";
	    $fieldsAll = $displayVal = $fieldsName = $displayName = Array();
	    $joined = Array();
# Prefix for the field name depends on $exe
		if($exe) # p is a Prefix for the field name
		{	$p = "a"; $pi = "i"; $pr = "r"; $pv = "v"; $pu = "u";	}
		else	# This is a subquery, mention it in the table prefix like a$id_$master instead of a$master
		{	$p = "a$id"."_"; $pi = "i$id"."_"; $pr = "r$id"."_"; $pv = "v$id"."_";  $pu = "u$id"."_";	}
# Get the Report Parameters & Columns
		$data_set = Exec_sql("SELECT rep.id up, rep.ord, col_def.up par, col_def.id typ, def_typ.id refr, COALESCE(def_typ.t, def.t, col_def.t) base
						, CASE WHEN cols.t=0 THEN rep.t ELSE COALESCE(col_typ.t, cols.t, rep.t) END col
						, CASE WHEN rep.t=".REP_COLS." THEN cols.id ELSE '' END id
						, CASE WHEN cols.t=0 THEN (CASE WHEN cols.ord=0 THEN CONCAT(rep.val, cols.val) ELSE cols.val END)
							ELSE COALESCE(col_typ.val, cols.val, rep.val) END val
						, CASE WHEN cols.t IS NULL AND col_def.id IS NULL THEN NULL WHEN col_def.val IS NULL THEN rep.ord WHEN req_def.val IS NULL THEN col_def.val
							WHEN def_typ.id=def_typ.t THEN CONCAT(req_def.val, ' -> ', def.val) ELSE req_def.val END name
						, CASE WHEN def_typ.id!=def_typ.t THEN col_def.val END mask, def_typ.val ref_name
						, rep.t jn, COALESCE(cols.val,'') jnon
					FROM $z rep LEFT JOIN $z cols ON cols.up=rep.id 
						LEFT JOIN $z col_typ ON col_typ.id=cols.t AND rep.t=".REP_COLS." AND col_typ.up!=".REP_COLS
					."	LEFT JOIN $z col_def ON col_def.id=rep.val AND (rep.t=".REP_COLS." OR rep.t=".REP_JOIN.")"
					."	LEFT JOIN $z req_def ON col_def.up!=0 AND req_def.id=col_def.up
						LEFT JOIN $z def ON col_def.up!=0 AND def.id=col_def.t
						LEFT JOIN $z def_typ ON def.id!=def.t AND def_typ.id=def.t
					WHERE rep.up=$id ORDER BY rep.ord"
				, "Get the Report Params & Columns");
		while($row = mysqli_fetch_array($data_set)) # Store all report params in an array
			if($row["jn"] == REP_JOIN)	# It's a JOIN
				$GLOBALS["STORED_REPS"][$id][REP_JOIN][$row["par"] > 0 ? $row["par"] : ($row["typ"] > 0 ? $row["typ"] : $row["up"])][$row["col"]]
				        = strlen($row["jnon"]) >= VAL_LIM ? Get_tail($row["id"], $row["jnon"]) : $row["jnon"];
			elseif($row["base"] || $row["id"])	# It's a Column
			{
				if(isset($row["mask"])){
				    $alias = FetchAlias($row["mask"], $row["ref_name"]);
				    if($alias == $row["ref_name"])
    					$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"]." -> $alias";
    				else
    					$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"]." -> $alias (".$row["ref_name"].")";
				}
				else
					$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"];
				$GLOBALS["STORED_REPS"][$id]["types"][$row["ord"]] = isset($row["typ"])?$row["typ"]:"";
				$GLOBALS["STORED_REPS"][$id]["columns"][$row["ord"]] = $row["up"];
				if($row["par"])
					$GLOBALS["STORED_REPS"]["parents"][$row["typ"]] = $row["par"];
				if($row["refr"])
					$GLOBALS["STORED_REPS"][$id]["refs"][$row["ord"]] = $row["refr"];
			    #trace("_ check length of '".$row["val"]."' = ".strlen($row["val"]));
				$GLOBALS["STORED_REPS"][$id][$row["col"]][$row["ord"]] = mb_strlen($row["val"]) >= VAL_LIM ? Get_tail($row["id"], $row["val"]) : trim($row["val"]);
				if(!isset($GLOBALS["REV_BT"][$row["typ"]]) && $row["typ"])
					$GLOBALS["REV_BT"][$row["typ"]] = $GLOBALS["basics"][$row["base"]];
			}
			elseif(isset($GLOBALS["STORED_REPS"][$id]["params"][$row["col"]]))	# It's a Param's tail
				$GLOBALS["STORED_REPS"][$id]["params"][$row["col"]] .= $row["val"];
			else	# It's a Param
				$GLOBALS["STORED_REPS"][$id]["params"][$row["col"]] = $row["val"];
        $GLOBALS["STORED_REPS"][$id]["columns_flip"] = array_flip($GLOBALS["STORED_REPS"][$id]["columns"]);
        # Add dynamically created columns
		if(isset($_REQUEST["SELECT"]) && $exe)
		{
	        $i = count($GLOBALS["STORED_REPS"][$id]["columns"]);
	        $select = explode(",", str_replace("\,","%2c",$_REQUEST["SELECT"]));
			trace("Dynamic select: ".print_r($select, TRUE));
            foreach($select as $k => $v)
            {
                $f = explode(":", str_replace("\:","%3a",$v));
                if(!isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]))
                {
    				$i++;
    				if(strlen($f[0]))
                        $f[0] = str_replace("%2c",",",str_replace("%3a",":",$f[0]));
                    else
                        $f[0] = "''";
    				$GLOBALS["STORED_REPS"][$id]["types"][$i] = "";
    				$GLOBALS["STORED_REPS"][$id]["columns"][$i] = $f[0];
					$GLOBALS["STORED_REPS"][$id]["head"][$i] = $f[0];
    				$GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$i] = $f[0];
    				$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]] = $i;
				    trace("_ check filter for FR_$i");
    				if(isset($_REQUEST["FR_$k"]))
        				$GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$i] = $_REQUEST["FR_$k"];
                }
            }
			trace("Dynamic columns: ".print_r($GLOBALS["STORED_REPS"][$id], TRUE));
        }
        # Check if we have TOTALS specified
		if(isset($_REQUEST["TOTALS"]))
		{
	        $select = explode(",", $_REQUEST["TOTALS"]);
	        $tmp = Array();
			trace("custom totals: ".print_r($select, TRUE));
            foreach($select as $k => $v)
            {
    			trace("_ field: $k => $v");
                $f = explode(":", $v);
                if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]) && in_array($f[1], $aggr_funcs))
                {
        			trace("__ add total: ".$f[0]."=>".$f[1]);
                    $tmp[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]] = $f[1];
                }
            }
            if(count($tmp) > 0)
                $GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL] = $tmp;
		}
		if(!is_array($GLOBALS["STORED_REPS"][$id]["types"]))
			my_die(t9n("[RU]Пустой отчет[EN]Empty report")." ".$GLOBALS["STORED_REPS"][$id]["header"]);
        # Clear the trace
        if($exe)
            mywrite("", "w");

        trace(print_r($GLOBALS["STORED_REPS"][$id], TRUE));
		foreach($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ)
		{
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
				$GLOBALS["STORED_REPS"][$id]["head"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key];
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key]))
			    if($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key]!="")
        			$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key];
			if(!isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]))
    			$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key] = isset($GLOBALS["REV_BT"][$typ])?$GLOBALS["REV_BT"][$typ]:"SHORT";
		}
#print_r($GLOBALS["STORED_REPS"][$id]);die();
# If we have an UPDATE, reserve a column for its results report
		if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET]))
		{
			if(isset($_REQUEST["confirmed"]))
				$GLOBALS["STORED_REPS"][$id]["head"][] = t9n("[RU]Выполнено[EN]Done");
			else
				$GLOBALS["STORED_REPS"][$id]["head"][] = "<a href=\"#\" onclick=\"byId('report').action+='?confirmed';byId('report').submit();event.stopPropagation();\">".t9n("[RU]Выполнить[EN]Commit changes")."</a>";
		}
# Get the Entities - those having others as requisites
		if(!isset($GLOBALS["STORED_REPS"][$id]["sql"]))
		{
			$sql = "SELECT distinct rep.ord, CASE WHEN col_def.up=0 THEN col_def.id ELSE col_def.up END typ, reqs.id req, req_refs.t refr, arr_vals.up arr
					FROM $z rep LEFT JOIN $z col_def ON col_def.id=rep.val 
						LEFT JOIN $z reqs ON reqs.up=CASE WHEN col_def.up=0 THEN col_def.id ELSE col_def.up END
						LEFT JOIN $z req_refs ON req_refs.id=reqs.t AND length(req_refs.val)=0
						LEFT JOIN $z arr_vals ON arr_vals.up=reqs.t AND arr_vals.ord=1
					WHERE rep.up=$id AND rep.t=".REP_COLS." AND (req_refs.id IS NOT NULL OR arr_vals.id IS NOT NULL)
					ORDER BY rep.ord";
			$data_set = Exec_sql($sql, "Get all Objects involved in Report along with their Refs");
# Report data retrieval - Fill in the arrays of Refs and Arrays
			while($row = mysqli_fetch_array($data_set))
# Save all the links from and to this Req and its Peers and Parent
				if($row["refr"])
				{
					$GLOBALS["STORED_REPS"][$id]["references"][$row["typ"]][$row["refr"]] = $row["req"];
					$GLOBALS["STORED_REPS"][$id]["ref_typ"][$row["req"]] = $row["refr"];
				}
# Save all the Array dependencies of this Req and its Parent
				else
					$GLOBALS["STORED_REPS"][$id]["arrays"][$row["typ"]][$row["arr"]] = $row["req"];
# Replace the report params with the gotten ones from $_REQUEST
			foreach($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ) # Col => Type
			{
    			trace(" Replace the report params with the gotten ones from REQUEST: $key => $typ");
			    # Fill in the array of conditions for Construct_WHERE()
			    if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
			    {
    			    $str = str_replace(" ", "_", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]);
			        if(isset($_REQUEST["FR_$str"]))
			            if(strlen($_REQUEST["FR_$str"]))
            				$GLOBALS["CONDS"][$key]["FR"] = $_REQUEST["FR_$str"];
			        if(isset($_REQUEST["TO_$str"]))
			            if(strlen($_REQUEST["TO_$str"]))
            				$GLOBALS["CONDS"][$key]["TO"] = $_REQUEST["TO_$str"];
			    }
				if(!isset($GLOBALS["CONDS"][$key]["FR"]) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]))
                        $GLOBALS["CONDS"][$key]["FR"] = $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key];
				if(!isset($GLOBALS["CONDS"][$key]["TO"]) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key]))
                        $GLOBALS["CONDS"][$key]["TO"] = $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key];
			}
# In case some function received in the request - implement those
			if(isset($_REQUEST["SELECT"]))
			{
			    $new_funcs = Array();
		        $select = explode(",", str_replace("\,","%2c",$_REQUEST["SELECT"]));
    			trace("Functions: ".print_r($select, TRUE));
                foreach($select as $k => $v)
                {
                    $f = explode(":", str_replace("\:","%3a",$v));
                    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]))
                        $new_funcs[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]] = strtoupper($f[1]);
                }
                if(count($new_funcs) > 0)
					$GLOBALS["STORED_REPS"][$id][REP_COL_FUNC] = $new_funcs;
    			trace("New Functions: ".print_r($new_funcs, TRUE));
            }

			$not_all_joined = TRUE;
			while($not_all_joined)
			{
				$not_all_joined = FALSE;
				$no_progress = TRUE;
				foreach($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ) # Column # => Type
				{
					if(strlen($typ)) # A real field, not a synthetic (calculatable) one
					{
						$par = $par_alias = isset($GLOBALS["STORED_REPS"]["parents"][$typ]) ? $GLOBALS["STORED_REPS"]["parents"][$typ] : $typ;
						$alias = $typ;
						unset($field);
						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
    						if(substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_")
    						{
    							switch($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])
    							{
    								case "abn_ID":
    									$field = "$p$alias.id";  # Requisite ID
    									break;
    								case "abn_UP":
    									$field = "$p$alias.up";  # Parent ID
    									break;
    								case "abn_TYP":
    									$field = "$p$alias.t";   # Requisite Type
    									break;
    								case "abn_ORD":
    									$field = "$p$alias.ord";  # Requisite Order
    									break;
    								case "abn_REQ":
    									$field = "$alias";	# Requisite definition ID
    									break;
    								case "abn_BT":	# Base typ
    									$field = $GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["types"][$key]];
    									break;
    							}
    							if(isset($field))
    							{
    								$GLOBALS["STORED_REPS"][$id]["base_in"][$key] = "NUMBER";
    								unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]); # No more actions for this
    								$GLOBALS["STORED_REPS"][$id]["abn_"][$key] = "";
    							}
    						}
						if(!isset($field))
							$field = "$p$alias.val";
						if(!isset($master))  # Master is the Parent of the first column of the report
						{
							$master = $par_alias;
							$tables[$master] = "$z $p$master";
							if(isset($_REQUEST["i$master"])) # We got an explicit filter for $master table
								$conds[$master] = "$p$master.id=%$master"."_OBJ_ID%";
							else
								$conds[$master] = "$p$master.up!=0 AND length($p$master.val)!=0 AND $p$master.t=$par";
						}
						# Note: Aliased Master would be skipped, its JOIN condition doesn't make sense, it will get to WHERE
						if(!isset($tables[$alias])) # The table is not joined yet
						{
    			    		trace("$alias not joined yet");

							if(!isset($tables[$par_alias])) # The parent is not joined yet
							{
        			    		trace("_ parent $par_alias of $alias not joined yet");
								$on = "AND $p$par_alias.t=$par";
								# First check the FROM set of the report
                        	    if(isset($GLOBALS["STORED_REPS"][$id][REP_JOIN][$par_alias]))
                        	    {
                        	        trace("__ REP_JOIN for $par_alias");
                        	        $rf = $GLOBALS["STORED_REPS"][$id][REP_JOIN][$par_alias];
                        		    $join = "";
                        			if(isset($rf[REP_JOIN_ON])){
                        			    preg_match_all("/:(\d+):/", $rf[REP_JOIN_ON], $matches); # :([\dA-Za-zа-яА-Я _]+):
                        			    foreach($matches[1] as $j)
                        			        if(($j != $par_alias) && !isset($tables[$j]))
                        			        {
                                    	        trace("___ $j required, not joined");
                        			            continue(2);
                        			        }
#                            		    $join = "AND " . preg_replace("/:(\d+):/", "$1", $rf[REP_JOIN_ON]);
                            		    $join = $rf[REP_JOIN_ON];
                        			}
                        		    $par_alias = isset($rf[REP_ALIAS]) ? $rf[REP_ALIAS] : $par_alias;
                        			$tables[$par_alias] = "LEFT JOIN $z $p$par_alias ON $p$par_alias.t=$par_alias AND $join";
                        			
                    			    preg_match_all("/($p$par_alias\.\w+)/", $join, $matches); # Get all the required fields
                    			    if(count($matches[1]))
                        			    foreach($matches[1] as $j)
        									$joined["$p$par_alias"][$j] = $j;
        						    else
        									$joined["$p$par_alias"][$par_alias] = "$p$par_alias.*";
									$joinedFrom["$p$par_alias"] = "FROM $z $p$par_alias";
									$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.t=$par_alias";
									$joinedOn["$p$par_alias"] = ") $p$par_alias ON $join";
                        	        trace("__ ".$tables[$par_alias]);
                        	    }
                        	    else
								foreach($tables as $t => $j) # Look through joined tables
								{
            			    		trace("__ Look through joined tables");
									if(substr($t, strpos($t, "_")) == (isset($suffix) ? $suffix : ""))
										$orig = substr($t, 0, strpos($t, "_"));
									else
										$orig = $t;
									if($t == $master)
									{
    									$ptid = "$p$t.id";
    									$ptup = "$p$t.up";
									}
									else
									{
    									$ptid = "$p$t"."_id";
    									$ptup = "$p$t"."_up";
									}
									# Get first suitable link if any
									if(isset($GLOBALS["STORED_REPS"][$id]["references"][$orig][$par]) # Reference to us
									        &&  ($t."_alias" !== $par_alias)) # and it's not the same Typ reference
									{
                			    		trace("___ first suitable link [$orig]->[$par]");
										if(!isset($joined["$p$t"]) || (strpos(implode(" ", $joined["$p$t"]), $ptid) === false))
										    $joined["$p$t"][$ptid] = "$p$t.id $ptid";
										if(HintNeeded($key, $id))
										{
											$tables[$par_alias] = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias USE INDEX (PRIMARY)) ON $pr$par_alias.up=$p$t.id AND $p$par_alias.id=$pr$par_alias.t $on";
    										$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.up";
    										$joinedFrom["$p$par_alias"] = "FROM $z $pr$par_alias,$z $p$par_alias USE INDEX (PRIMARY)";
    										$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.id=$pr$par_alias.t $on";
    										$joinedOn["$p$par_alias"] = ") $p$par_alias ON $p$par_alias.up=$ptid";
										}
										else
										{
											$tables[$par_alias] = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias) ON $pr$par_alias.val='".$GLOBALS["STORED_REPS"][$id]["references"][$orig][$par]."'"
																	." AND $pr$par_alias.up=$p$t.id AND $p$par_alias.id=$pr$par_alias.t $on";
    										$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.up,$pr$par_alias.val";
    										$joinedFrom["$p$par_alias"] = "FROM $z $pr$par_alias,$z $p$par_alias";
    										$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.id=$pr$par_alias.t $on";
    										$joinedOn["$p$par_alias"] = ") $p$par_alias ON $p$par_alias.val='".$GLOBALS["STORED_REPS"][$id]["references"][$orig][$par]."'"
																	." AND $p$par_alias.up=$ptid";
										}
									}
									elseif(isset($GLOBALS["STORED_REPS"][$id]["references"][$par][$orig])) # We have a Reference
									{
                			    		trace("___ We have a Reference [$par]->[$orig]");
										if(HintNeeded($key, $id))
										{
											$tables[$par_alias] = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias USE INDEX (PRIMARY)) ON $pr$par_alias.up=$p$par_alias.id AND $p$t.id=$pr$par_alias.t AND $pr$par_alias.val='"
											                        .$GLOBALS["STORED_REPS"][$id]["references"][$par][$orig]."' $on";
    										$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.t";
    										$joinedFrom["$p$par_alias"] = "FROM $z $pr$par_alias,$z $p$par_alias /*USE INDEX (PRIMARY)*/";
    										$joinedClause["$p$par_alias"] = " WHERE $pr$par_alias.up=$p$par_alias.id AND $pr$par_alias.val='".$GLOBALS["STORED_REPS"][$id]["references"][$par][$orig]."' $on";
    										$joinedOn["$p$par_alias"] = ") $p$par_alias ON $ptid=$p$par_alias.t";
										}
										else
										{
    										$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.t,$pr$par_alias.val";
											$tables[$par_alias] = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias) ON $pr$par_alias.val='".$GLOBALS["STORED_REPS"][$id]["references"][$par][$orig]."'"
																	." AND $pr$par_alias.up=$p$par_alias.id AND $p$t.id=$pr$par_alias.t $on";
    										$joinedFrom["$p$par_alias"] = "FROM $z $pr$par_alias,$z $p$par_alias";
    										$joinedClause["$p$par_alias"] = " WHERE $pr$par_alias.up=$p$par_alias.id $on";
    										$joinedOn["$p$par_alias"] = " ) $p$par_alias ON $p$par_alias.val='".$GLOBALS["STORED_REPS"][$id]["references"][$par][$orig]."'"
																	." AND $ptid=$p$par_alias.t";
										}
									}
									elseif(isset($GLOBALS["STORED_REPS"][$id]["arrays"][$orig][$par])) # We are an Array
									{
                			    		trace("___ We are an Array [$par]->[$orig]");
										$tables[$par_alias] = "LEFT JOIN $z $p$par_alias ON $p$par_alias.up=$p$t.id $on";
										$joined["$p$par_alias"][$par_alias] = "$p$par_alias.up";
										$joinedFrom["$p$par_alias"] = "FROM $z $p$par_alias";
										$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.t=$par";
										if(strpos(implode(",", $joined["$p$t"]), $ptid) === false)
										    $joined["$p$t"][$ptid] = "$p$t.id $ptid";
										$joinedOn["$p$par_alias"] = " ) $p$par_alias ON $p$par_alias.up=$ptid";
										$GLOBALS["STORED_REPS"][$id]["PARENT"][$par] = $orig;
									}
									elseif(isset($GLOBALS["STORED_REPS"][$id]["arrays"][$par][$orig])) # We got an Array
									{
                			    		trace("___ We got an Array [$par]->[$orig]");
										$tables[$par_alias] = "LEFT JOIN $z $p$par_alias ON $p$t.up=$p$par_alias.id $on";
										$joinedFrom["$p$par_alias"] = "FROM $z $p$par_alias";
										$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.t=$par";
										if(strpos(implode(",", $joined["$p$t"]), $ptup) === false)
										    $joined["$p$t"][$ptup] = "$p$t.up $ptup";
										$joinedOn["$p$par_alias"] = " ) $p$par_alias ON $ptup=$p$par_alias"."_id";
										$GLOBALS["STORED_REPS"][$id]["PARENT"][$orig] = $par;
									}
									else
										continue;
									trace("____ ".$tables[$par_alias]);
									break;
								}
							}
							if(!isset($tables[$par_alias])) # Failed to join the parent, better luck next round
							{
							    trace("__ Failed to join the parent $par_alias, better luck next round");
								$not_all_joined = TRUE;
								continue;
							}
							if($typ != $par)	# We are a requisite
							{
								if(!isset($tables[$alias]))
								    $tables[$alias] = "";
								if($l = isRef($id, $par, $typ))
								{
									if(HintNeeded($key, $id))
									{
										$tables[$alias] .= "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias USE INDEX (PRIMARY)) ON $pr$alias.up=$p$par_alias.id AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l AND $pr$alias.val='$typ' ";
										$joinedJoin["$p$par_alias"][] = "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias USE INDEX (PRIMARY)) ON $pr$alias.up=$p$par_alias.id AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l AND $pr$alias.val='$typ' ";
									}
									else
									{
										$tables[$alias] .= "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias) ON $pr$alias.up=$p$par_alias.id AND $pr$alias.val='$typ' AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l";
										$joinedJoin["$p$par_alias"][] = "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias) ON $pr$alias.up=$p$par_alias.id AND $pr$alias.val='$typ' AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l";
									}
								}
								else
								{
									$tables[$alias] .= "LEFT JOIN $z $p$alias ON $p$alias.up=$p$par_alias.id AND $p$alias.t=$typ";
									$joinedJoin["$p$par_alias"][] = "LEFT JOIN $z $p$alias ON $p$alias.up=$p$par_alias.id AND $p$alias.t=$typ";
								}
							}
						}
						if(isset($fields[$key]))
							continue;
						$no_progress = FALSE;
						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
    						$name = "'".str_replace("'", "\\'", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key])."'";
    					else
    						$name = "$pv$key$s$par";
						$fieldsOrig[$key] = $master == $par_alias ? $field : str_replace(".", "_", $field);
						# Replace [THIS] with the field value in case we got a Formula and [THIS] mentioned in the formula
    					if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]))
    					    if(mb_strpos($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key],"[THIS]")!==FALSE)
    	    				    $field = str_replace("[THIS]", $field, $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]);
						$fields[$key] = $field;
						$names[$key] = $name;
						if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # Not hidden field
						{
    						$displayName[$key] = $name;
							if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
							{
    							if(in_array($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], $aggr_funcs)) # Needs grouping
    							{
    								$field_names[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field) $name";
    								$displayVal[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(" . $fieldsOrig[$key] . ")";
    								$joined["$p$par_alias"][$key] = "$field " . $fieldsOrig[$key];
    								$GLOBALS["STORED_REPS"][$id]["aggrs"][$key] = "";
    								$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)";
    							}
    							elseif(substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_")
    							{
    								$field_names[$key] = "$field $name"; # Post-processing abn_ function like NUM2STR
    								$displayVal[$key] = $field;
    								$GLOBALS["STORED_REPS"][$id]["abn_"][$key] = "";
    							}
    							elseif(strlen($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) # No grouping needed
    							{
    								$field_names[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field) $name";
    								$displayVal[$key] = $master == $par_alias ? $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)" : $fieldsOrig[$key];
    								$joined["$p$par_alias"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)" . " " . $fieldsOrig[$key];
    								unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]);
    							}
#   							elseif($GLOBALS["REV_BT"][$bt[$key]] == "PATH")
#	    							$field_names[$key] = "CONCAT($p$alias.id, SUBSTRING($field, -4)) $name";
							}
							if(!isset($field_names[$key]))
							{
								$field_names[$key] = "$field $name";
								
								if($master == $par_alias)
								{
    								$joined["$p$par_alias"][$key] = "";
    								$displayVal[$key] = $field;
								}
    							else
    							{
    								$displayVal[$key] = $fieldsOrig[$key];
    								if(!isset($joined["$p$par_alias"]) || (strpos(implode(" ",$joined["$p$par_alias"]),$fieldsOrig[$key]) === false))
        								$joined["$p$par_alias"][$key] = $field . " " . $fieldsOrig[$key];
    							}
								if(strpos($tailed, $GLOBALS["STORED_REPS"][$id]["base_in"][$key]))
								{ # Get tails of the field, if any
									$GLOBALS["REP_COLS"][$name] = "t$alias"; # Remember the alias that needs the tail
									if(!isset($tails_fetched[$alias])) # Fetch Tails once for all field occurrences
									{
										$tables[$alias] .= " LEFT JOIN $z t$alias ON t$alias.t=0 AND t$alias.ord=0 AND t$alias.up=$p$alias.id";
										$joinedJoin["$p$par_alias"][] = " LEFT JOIN $z t$alias ON t$alias.t=0 AND t$alias.ord=0 AND t$alias.up=$p$alias.id";
										$field_names["t$alias"] = "t$alias.up t$alias";  # First tail
        								$displayVal["t$key"] = $master == $par_alias ? "t$alias.up" : "t$alias"."_up";
        								$displayName["t$key"] = "t$alias";
        								$joined["$p$par_alias"]["t$alias"] = "t$alias.up t$alias"."_up";
										$tails_fetched[$alias] = "";
									}
								}
							}
							if((($par == $typ) && isset($GLOBALS["STORED_REPS"][$id]["params"][REP_HREFS]) # It's a parent & report is Interactive
										&& !isset($GLOBALS["STORED_REPS"][$id]["abn_"][$key]) # it's not a function value
										&& !isset($GLOBALS["STORED_REPS"][$id]["aggrs"][$key])) # & no aggregates applied to this field
								|| ($GLOBALS["STORED_REPS"][$id]["base_in"][$key] == "FILE")	# Or we have a file here
								|| ($GLOBALS["STORED_REPS"][$id]["base_in"][$key] == "PATH"))
							{
								$field_names["$pi$key"] = "$p$alias.id $pi$key";   # Fetch the ID to build a HREF
								$joined["$p$par_alias"]["$pi$key"] = "$p$alias.id $pi$key";
								$displayVal["$pi$key"] = $master == $par_alias ? "$p$alias.id " : "";
								$displayName["$pi$key"] = "$pi$key";
								$fields["$pi$key"] = "$p$alias.id";
								$names["$pi$key"] = "$pi$key";
							}
							if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key])) # We have a value to set
							{	# Fetch the value for UPDATE stmt
							    trace("REP_COL_SET for $key - ".$GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]);
								# Fetch the ID to update the value
								$displayName["$pi$key"] = "$pi$key";
								if(isRef($id, $par, $typ))
								{
									$field_names["$pi$key"] = "$pr$alias.id $pi$key";   # Fetch the ID to update the value
    								if($master == $par_alias)
    								{
        								$joined["$p$par_alias"]["$pi$key"] = "";
        								$displayVal["$pi$key"] = "$pr$alias.id";
    								}
        							else
        							{
        								$joined["$p$par_alias"]["$pi$key"] = "$pr$alias.id $pi$key";
        								$displayVal["$pi$key"] = "";
        							}
								}
								else
								{
									$field_names["$pi$key"] = "$p$alias.id $pi$key";
    								if($master == $par_alias)
    								{
        								$joined["$p$par_alias"]["$pi$key"] = "";
        								$displayVal["$pi$key"] = "$p$alias.id";
    								}
        							else
        							{
        								$joined["$p$par_alias"]["$pi$key"] = "$p$alias.id $pi$key";
        								$displayVal["$pi$key"] = "";
        							}
								}
								$fields["$pi$key"] = "$p$alias.id";
								$names["$pi$key"] = "$pi$key";

								$update_val = BuiltIn($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]);
								if($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key] != $update_val)
									$update_val = "'$update_val'";	# BuiltIn gave something, freeze the result
								$field_names["$pu$key"] = "$update_val $pu$key";
								
								$displayVal["$pu$key"] = $update_val;
								$displayName["$pu$key"] = "$pu$key";

								$fields["$pu$key"] = "$pu$alias.id";
								$names["$pu$key"] = "$pu$key";
								if(!isset($field_names["$pi$par"]) && ($par != $typ))
								{
									$field_names["$pi$par"] = "$p$par.id $pi$par";
    								$displayName["$pi$par"] = "$pi$par";
    								$displayVal["$pi$par"] = "$p$par".($par == $master ? "." : "_")."id";
    								
    								if($master == $par_alias)
        								$joined["$p$par_alias"]["$pi$par"] = "";
        							elseif(strpos(implode(" ", $joined["$p$par_alias"]), "$p$par".($par == $master ? "." : "_")."id") === false)
        								$joined["$p$par_alias"]["$pi$par"] = "$p$par.id $p$par".($par == $master ? "." : "_")."id";
        							
									$fields["$pi$par"] = "$p$par.id";
									$names["$pi$par"] = "$pi$par";
								}
							}
						}
						else
						{
						    $fieldsAll[$key] = $fieldsOrig[$key];
						    $fieldsName[$key] = $name;
						    if(($master !== $par_alias) && (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key])
                        						            || isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key])
                        						            || isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key])))
                        		if(strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
						            $joined["$p$par_alias"][$key] = "$field " . $fieldsOrig[$key];
						}
					}
					else
					{
						$no_progress = FALSE;
						# Save real field names to replace those in the synthetic operations
						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]))
						{
							$field = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key];
							if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
								if($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] == 'abn_URL')
									$field = "'abn_URL($key)'";
						}
						elseif(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
							$field = "'".$GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]." $key - $typ'";
						else
							$field = t9n("[RU]'Пустая или неверная формула в вычисляемой колонке (№$key)'"
							        ."[EN]Empty or incorrect formula in column #$key'");
						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
    						$name = "'".str_replace("'", "\\'", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key])."'";
    					else
    						$name = "$pv$key";
						$fields[$key] = $field;
						$names[$key] = $name;

						if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
						{
							$field_names[$key] = "$field $name";
    						$displayVal[$key] = $field;
    						$displayName[$key] = $name;
						    if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
						    {
    							if(in_array($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], $aggr_funcs)) # Needs grouping
    							{
    								$field_names[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field) $name";
									$fields[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)"; //[AS]12.04.2019
            						$displayVal[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)";
    								$GLOBALS["STORED_REPS"][$id]["aggrs"][$key] = "";
    							}
    							elseif(strlen($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) # No grouping needed
    							{
    									if(substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_")
    										$field_names[$key] = "$field $name";
    									else
    									{
    										$field_names[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field) $name";
    										$displayVal[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)";
        									$fields[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]."($field)"; //[AS]12.04.2019
    										unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]);
    									}
    							}
						    }
						}
						else
						{
						    $fieldsAll[$key] = $field;
						    $fieldsName[$key] = $name;
						}
					}
					if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]))
					{
					    if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]) && ($master !== $par_alias))
	    				    // In case we have an order for a hidden field - fetch the field during the JOIN
	    				    if(strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
        						$joined["$p$par_alias"][$key] = $field . " " . $fieldsOrig[$key];
    					if($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "SIGNED")
    					    $tmp = "CAST(".(isset($GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key])?$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key]
    					                                                                :$fields[$key])." AS SIGNED)";
                        else
    						$tmp = $fields[$key];
						if($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key] < 0)
    					    $sortByArr[-$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]] = ($master == $par_alias ? $tmp : str_replace(".", "_", $tmp)) . " DESC";
						else
    					    $sortByArr[$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]] = $master == $par_alias ? $tmp : str_replace(".", "_", $tmp);
					}
# Create WHERE condition according to the data types
					if(isset($filters[$key]))# || isset($field_names[$key])) # The filter is already set
						continue;
					if(isset($GLOBALS["CONDS"][$key]))	# If we got some filter conditions
					{
						$GLOBALS["REV_BT"][$alias] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key]; # Get Base Type for our column
						$GLOBALS["where"] = "";
						Construct_WHERE($alias, $GLOBALS["CONDS"][$key], 1, FALSE);
						$filters[$key] = str_replace("a$alias.val", $field, $GLOBALS["where"]);
						if(($master == $par_alias) || !isset($fieldsOrig[$key]))
    						$masterFilters[$key] =  $filters[$key];
    					else
    					{
	    				    if(strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
        						$joined["$p$par_alias"]["f$key"] = $field . " ".$fieldsOrig[$key];
    						$masterFilters[$key] = str_replace($field, $fieldsOrig[$key], $filters[$key]);
    					}
						if(strpos($filters[$key], "tp$alias.val"))	# Check if Tail fields like tp777.val exist
						{
							$filters[$key] = str_replace("t$alias.val", "t$field", $filters[$key]);
							$filters[$key] = str_replace("tp$alias.val", "tp$field", $filters[$key]);
							$masterFilters[$key] = str_replace("t$alias.val", "t$field", $masterFilters[$key]);
							$masterFilters[$key] = str_replace("tp$alias.val", "tp$field", $masterFilters[$key]);
							$field = $typ;
							$distinct = "DISTINCT";	# Tails might return more than one row
							if(!isset($field_names[$field]))
							{
								$field_names[$field] = "a$field.id";	# Add ID to make equal Vals differ
								$joined["a$par_id"][$field] = "a$field.id";
							}
							if(!isset($tables["t$field"]))	# The Field might be used more than once
								$tables["t$field"] = " LEFT JOIN $z ta$field ON ta$field.up=a$field.id AND ta$field.t=0
									LEFT JOIN $z tpa$field ON tpa$field.up=ta$field.up AND tpa$field.t=0 AND tpa$field.ord=ta$field.ord+1";
						}	# No way to create values for tailed fields, selected on condition
						elseif(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]) && ($par != $master))
						{	# We have a value to set, so return an empty line even in case the condition fails
						    //trace("Move the condition from WHERE to JOIN $typ tables[ $key ] .= filters[ $key ]");
						    //trace("_".$tables[$typ]." .= ".$filters[$key]);
							$tables[$typ] .= $filters[$key];	# Move the condition from WHERE to JOIN
							$joinedOn["$p$par_alias"] .= $masterFilters[$key];
							unset($filters[$key]);
							unset($masterFilters[$key]);
							if(isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ]))
							{
								$par_id = $GLOBALS["STORED_REPS"][$id]["PARENT"][$typ];
								if(!strpos($field_names[$key], "a$par_id.id i$par_id")) # Fetch parent ID to create a req
								{
									$field_names[$key] .= ", a$par_id.id i$par_id";
    								$displayVal["i$par_id"] = "i$par_id";
                        		    $joined["a$par_id"]["a$par_id.id"] = "a$par_id.id i$par_id";
								}
							}
						}
					}
					elseif(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]) && ($par != $master))
						# We have a value to set, so fetch the parent's ID just in case there's no Value
						if(isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ]))
						{
							$par_id = $GLOBALS["STORED_REPS"][$id]["PARENT"][$typ];
							if(!strpos($field_names[$key], "a$par_id.id i$par_id")) # Fetch parent ID to create a req
							{
								$field_names[$key] .= ", a$par_id.id i$par_id";
								$displayVal["i$par_id"] = "i$par_id";
                    		    $joined["a$par_id"]["a$par_id.id"] = "a$par_id.id i$par_id";
							}
						}
				}
				if($not_all_joined && $no_progress)
				{
#{print_r($tables);print_r($conds);print_r($field_names);print_r($filters);print_r($GLOBALS["STORED_REPS"]);print_r($GLOBALS);die("Не могу связать колонки отчета");}
#break;
				    die_info($GLOBALS["STORED_REPS"][$id]["header"].": ".t9n("[RU]Невозможно связать колонки отчета.[EN]It is impossible to link the columns of the report."));
				}
				# We might get the Object explicitly set for Calculatables
				if(isset($_REQUEST["i$typ"]) && ($typ != $master))
					$conds[$typ] = "AND $p$typ.id=%$typ"."_OBJ_ID%";
			}
		    $fieldsAll = $fieldsAll + $displayVal;
		    $fieldsName = $fieldsName + $displayName;

# Check if we have a field set specified in request
			trace("Globals: ".print_r($GLOBALS["STORED_REPS"][$id], TRUE));
			trace("field_names: ".print_r($field_names, TRUE));
			trace("names: ".print_r($names, TRUE));
			trace("fields: ".print_r($fields, TRUE));
			if(isset($_REQUEST["SELECT"]))
			{
			    $new_field_names = $new_head = $new_fields = Array();
		        $select = explode(",",str_replace("\,","%2c",$_REQUEST["SELECT"]));
    			trace("select: ".print_r($select, TRUE));
		    	trace("fields: ".print_r($fields, TRUE));
                foreach($select as $k => $v)
                {
                    $v = array_shift(explode(":", str_replace("\:","%3a",$v)));
                    $v = str_replace("%2c",",",str_replace("%3a",":",$v));
                    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]))
                    {
        		    	trace("_ found in columns_flip: $k => $v");
                        $new_field_names[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]] = $field_names[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]];
                        $new_head[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]] = $GLOBALS["STORED_REPS"][$id]["head"][$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]];
                    }
                }
                if(count($new_field_names)>0)
                {
    			    $field_names = $new_field_names;
    			    $GLOBALS["STORED_REPS"][$id]["head"] = $new_head;
                }
    			trace("columns: ".print_r($GLOBALS["STORED_REPS"][$id]["columns_flip"], TRUE));
		    	trace("new field_names: ".print_r($field_names, TRUE));
			}
# Format the output
			foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
				if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key]))	# Set the column result Type
					$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key];
				else
					$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key];

# Construct the GROUP BY clause, if applicable
			if(isset($GLOBALS["STORED_REPS"][$id]["aggrs"])) # We have at least one aggregation function
				foreach($fields as $key => $value)
				    if(isset($field_names[$key])) # Only those values selected
    					if((!isset($GLOBALS["STORED_REPS"][$id]["aggrs"][$key])) # The field isn't aggregated
							&& (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))  # and not hidden
							&& !(($GLOBALS["STORED_REPS"][$id]["types"][$key]=="") # and not a calculatable with aggregation
							    && (preg_match("/\b(sum|avg|count|min|max)\b\(/i", $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key])))
							&& !((substr($key,0,1) == "u") # and not an Update statement with aggregates enclosed
							    && preg_match("/\b(sum|avg|count|min|max)\b\(/i", $GLOBALS["STORED_REPS"][$id][REP_COL_SET][substr($key,1)])))
    					{
    					    #trace("($key) group by ".$fields[$key]." or ".$names[$key]." which is ".$GLOBALS["STORED_REPS"][$id][REP_COL_SET][substr($key,1)]);
    						if(isset($group))
    						{
    							$group .= ", ";
    							$groupBy[$master] .= ", ";
    						}
    						else
    							$group = $groupBy[$master] = "GROUP BY ";
    						$group .= substr($names[$key],0,1) == "'" ? $fields[$key] : $names[$key]; # Fix a mysql bug https://bugs.mysql.com/bug.php?id=14019
    						
    						//mywrite("fields $key => $value names:".$names[$key]." fields: ".$fields[$key]." display: ".$fieldsAll[$key]." ".$fieldsName[$key]);
    						$groupBy[$master] .= substr($displayName[$key],0,1) == "'" ? $fieldsAll[$key] : $fieldsName[$key]; # Fix a mysql bug https://bugs.mysql.com/bug.php?id=14019
    					}
# Construct the HAVING clause, if applicable
			$GLOBALS["CONDS"] = array();
			foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
			{
				# Fill in the array of conditions for Construct_WHERE()
				if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key]))
					$GLOBALS["CONDS"][$key]["FR"] = $GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key];
				if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key]))
					$GLOBALS["CONDS"][$key]["TO"] = $GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key]; 
				if(isset($GLOBALS["CONDS"][$key]))  # If we got some having conditions
				{
					$typ = $GLOBALS["STORED_REPS"][$id]["types"][$key];
					$GLOBALS["REV_BT"][$GLOBALS["STORED_REPS"][$id]["types"][$key]] = $GLOBALS["STORED_REPS"][$id]["base_out"][$key];
					$GLOBALS["where"] = "";
					Construct_WHERE($typ, $GLOBALS["CONDS"][$key], 1, FALSE, TRUE);
					if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
						$names[$key] = $fields[$key];
					if(isset($having))
					{
#						$having .= str_replace("a$typ.val", $names[$key], $GLOBALS["where"]);
						$having .= str_replace("a$typ.val", substr($displayName[$key],0,1) == "'" ? $fieldsAll[$key] : $fieldsName[$key], $GLOBALS["where"]);
					}
					else	# Cut first AND added by Construct_WHERE()
					{
#						$having = " HAVING ".substr(str_replace("a$typ.val", $names[$key], $GLOBALS["where"]), 4);
						$having = " HAVING ".substr(str_replace("a$typ.val", substr($displayName[$key],0,1) == "'" ? $fieldsAll[$key] : $fieldsName[$key], $GLOBALS["where"]), 4);
					}
				}
			}
#print_r($GLOBALS);print($having."\n");die($having1);
# Construct the ORDER BY clause, if applicable
			if(isset($_REQUEST["ORDER"]))
			{
		        $select = explode(",", $_REQUEST["ORDER"]);
    			trace("Order get: ".print_r($select, TRUE));
                foreach($select as $k => $v)
                {
                    if(substr($v,0,1)=="-")
                    {
                        $v = substr($v,1);
                        $desc = " DESC";
                    }
                    else
                        $desc = "";
                    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]))
                    {
                        $col = $GLOBALS["STORED_REPS"][$id]["columns_flip"][$v];
                        $key = $fields[$col];
                        trace("_ field $v ($col) found: $key type: ".$GLOBALS["STORED_REPS"][$id]["base_out"][$col]);
						if($GLOBALS["STORED_REPS"][$id]["base_out"][$col] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$col] == "SIGNED")
						    $key = "CAST(". $key." AS SIGNED)";
    					if(isset($order))
    						$order .= ", $key $desc";
    					else
    						$order = "ORDER BY $key $desc";
                    }
                }
    			trace("order set: $order");
            }
            elseif(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT]))
			{
				foreach($GLOBALS["STORED_REPS"][$id][REP_COL_SORT] as $key => $value)
				{
					unset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]);
					if(strlen($value))
					{	# In case the field is hidden, use its real expression, not alias
/*						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]) || !isset($field_names[$key]))
							$key = $fields[$key];
						else
*/
    					if($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "SIGNED")
    					    $key = "CAST(".(isset($GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key])?$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key]
    					                                                                :$fields[$key])." AS SIGNED)";
                        else
    						$key = $fields[$key];
            			trace("order: $key");
						if($value < 0)
							$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key." DESC"] = -$value;
						else
							$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key] = $value;
					}
				}
				array_multisort($GLOBALS["STORED_REPS"][$id][REP_COL_SORT]);
				foreach($GLOBALS["STORED_REPS"][$id][REP_COL_SORT] as $key => $value)
				{
					if(isset($order))
						$order .= ", $key";
					else
						$order = " ORDER BY $key";
				}
				ksort($sortByArr);
			}
# Save field names
			ksort($names);
			$GLOBALS["STORED_REPS"][$id]["names"] = $names;
# Gather all SQL parts into one string
			$filter = isset($filters) ? implode(" ", $filters) : "";
			$masterFilter = isset($masterFilters) ? implode(" ", $masterFilters) : "";
			
			$field = implode(",",$field_names);
			$cond = implode(" ", $conds);
			$sql = implode(" ", $tables);
# Check if we got some WHERE parameters
			if($exe && (isset($_REQUEST["WHERE"]) || isset($GLOBALS["STORED_REPS"][$id]["params"][REP_WHERE])))
			    if(strlen(trim($where = isset($_REQUEST["WHERE"]) ? $_REQUEST["WHERE"] : $GLOBALS["STORED_REPS"][$id]["params"][REP_WHERE])))
    			    $filter .= strtoupper(substr($where, 0, 3)) == "AND" ? " $where" : " AND $where";
# Fill in the field set with built-in and request values, if any
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $field, $builtins);
			foreach($builtins[0] as $builtin)
				if(BuiltIn($builtin) != $builtin)
					$field = str_replace($builtin, BuiltIn($builtin), $field);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $field, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($_REQUEST[$_req]))
					$field = str_replace($builtins[0][$k], $_REQUEST[$_req], $field);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $field, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$field = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $field);
            
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $filter, $builtins);
			foreach($builtins[0] as $builtin)
				if(BuiltIn($builtin) != $builtin)
					$filter = str_replace($builtin, BuiltIn($builtin), $filter);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $filter, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($_REQUEST[$_req]))
					$filter = str_replace($builtins[0][$k], $_REQUEST[$_req], $filter);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $filter, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$filter = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $filter);

			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", implode(" ", $fieldsAll), $builtins);
			foreach($builtins[0] as $builtin)
				if(BuiltIn($builtin) != $builtin)
					$fieldsAll = str_replace($builtin, BuiltIn($builtin), $fieldsAll);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $fieldsAll), $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($_REQUEST[$_req]))
					$fieldsAll = str_replace($builtins[0][$k], $_REQUEST[$_req], $fieldsAll);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $fieldsAll), $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$fieldsAll = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $fieldsAll);
            
            $tmp = "";
            foreach($joined as $k => $v)
                $tmp .= implode(" ", $v);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $tmp, $builtins);
			foreach($builtins[0] as $builtin)
				if(BuiltIn($builtin) != $builtin)
				    foreach($joined as $k => $v)
    					$joined[$k] = str_replace($builtin, BuiltIn($builtin), $joined[$k]);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $tmp, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($_REQUEST[$_req]))
				    foreach($joined as $kk => $v)
    					$joined[$kk] = str_replace($builtins[0][$k], $_REQUEST[$_req], $joined[$kk]);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $tmp, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
				    foreach($joined as $kk => $v)
    					$joined[$kk] = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $joined[$kk]);

			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $masterFilter, $builtins);
			foreach($builtins[0] as $builtin)
				if(BuiltIn($builtin) != $builtin)
					$masterFilter = str_replace($builtin, BuiltIn($builtin), $masterFilter);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $masterFilter, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($_REQUEST[$_req]))
					$masterFilter = str_replace($builtins[0][$k], $_REQUEST[$_req], $masterFilter);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $masterFilter, $builtins);
			foreach($builtins[1] as $k => $_req)
				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$masterFilter = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $masterFilter);
			
			if(isset($order))
			{
    			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $order, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($_REQUEST[$_req]))
    					$order = str_replace($builtins[0][$k], $_REQUEST[$_req], $order);
    			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $order, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
    					$order = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $order);
    			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $sortByArr, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($_REQUEST[$_req]))
    					$sortByArr = str_replace($builtins[0][$k], $_REQUEST[$_req], $sortByArr);
    			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $sortByArr, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
    					$sortByArr = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $sortByArr);
			}
			if(isset($group))
			{
    			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $group, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($_REQUEST[$_req]))
    					$group = str_replace($builtins[0][$k], $_REQUEST[$_req], $group);
    			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $group, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
    					$group = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $group);
    			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $groupBy, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($_REQUEST[$_req]))
    					$groupBy = str_replace($builtins[0][$k], $_REQUEST[$_req], $groupBy);
    			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $groupBy, $builtins);
    			foreach($builtins[1] as $k => $_req)
    				if(isset($GLOBALS["GLOBAL_VARS"][$_req]))
    					$groupBy = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $groupBy);
			}
#			print_r($builtins);die($field);
# Check if we got an SQL-injection in the Formula or Set field of a report
			if(preg_match("/(\b(from|select|table)\b)/i", $field.$filter, $match))
				die_info(t9n("[RU]Недопустимое значение вычисляемого поля: нельзя использовать служебные слова SQL. Найдено: ".
				        "[EN]No SQL clause allowed in calculatable fields. Found: ").$match[0]);
# Check if we got a fields set specified in the SELECT parameter
            trace("Fields: ".print_r($fields,true));
			if(isset($_REQUEST["SELECT"]))
			{
                trace("Check if we got field IDs to replace with field values");
            	foreach($fields as $k => $v)
            	{
                	preg_match_all("/:(\d+):/", $v, $cols);
                	if(count($cols[1]))
                	{
            	        trace("IDs to replace: ".print_r($cols, TRUE));
                    	foreach($cols[1] as $f)
                    	    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]))
                        	{
                    	        trace("replace $f with ".$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]]);
                    	        $field = str_replace(":$f:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]],$field);
                    	        $filter = str_replace(":$f:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]],$filter);
        						if(isset($group))
                        	        $group = str_replace(":$f:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]],$group);
        						if(isset($order))
                        	        $order = str_replace(":$f:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]],$order);
        						if(isset($having))
                        	        $having = str_replace(":$f:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]],$having);
                        	}
#                	    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]))
#            	        $sql = str_replace(":$v:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]],$sql);
                	}
            	}
			}
            trace("New fields: ".print_r($fields,true));
            trace("New field_names: ".print_r($field_names,true));
# Check if we got some subqueries - Reports - try to find them
            trace("Check if we got some subqueries: ".print_r($GLOBALS["STORED_REPS"][$id],true));
			$reps = array_unique(explode("[", $sql.$field.$filter.(isset($having) ? $having : " ").(isset($order) ? $order : " ")));
			array_shift($reps);
			if(count($reps))
				foreach ($reps as $value)
				{
                    trace("_ subquery: $value");
                    $tmp = explode("]", $value);
					$sub_query = array_shift($tmp);
					$bak_id = $id;
#					$block_bak = $block;
					Get_block_data($sub_query, FALSE); # Just get the SQL, no execution
					$id = $bak_id;
#					$block = $block_bak;
					if(isset($GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"]))
					{
#print_r($GLOBALS);print($having."\n");die($having1);
						$field = str_replace('['.$sub_query.']'
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $field);
						$filter = str_replace('\'['.$sub_query.']\''	# The report might be used in WHERE too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $filter);
						$filter = str_replace('['.$sub_query.']'	# The report might be used in WHERE too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $filter);
						$masterFilter = str_replace('\'['.$sub_query.']\''	# The report might be used in WHERE too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $masterFilter);
						$masterFilter = str_replace('['.$sub_query.']'	# The report might be used in WHERE too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $masterFilter);
						$sql = str_replace('['.$sub_query.']'	# The report might be used in JOIN too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $sql);
						if(isset($order))
						{
    						$order = str_replace('['.$sub_query.']'	# and in ORDER
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $order);
    						$sortByArr = str_replace('['.$sub_query.']'	# and in ORDER
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $sortByArr);
						}
						if(isset($group))
						{
    						$group = str_replace('['.$sub_query.']'	# and in GROUP
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $group);
    						$groupBy = str_replace('['.$sub_query.']'
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $groupBy);
						}
						if(isset($having))
						{
    						$having = str_replace('\'['.$sub_query.']\''	# and in HAVING
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $having);
    						$having = str_replace('['.$sub_query.']'	# and in HAVING
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $having);
						}
						$fieldsAll = str_replace('['.$sub_query.']'
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $fieldsAll);
						$fieldsAll = str_replace('\'['.$sub_query.']\''	# The report might be used in WHERE too
									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
									, $fieldsAll);
            			foreach($joined as $k => $v)
            			{
    						$joined[$k] = str_replace('['.$sub_query.']'
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $joined[$k]);
    						$joined[$k] = str_replace('\'['.$sub_query.']\''	# The report might be used in WHERE too
    									, "(".$GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"].")"
    									, $joined[$k]);
            			}
					}
#print_r($GLOBALS);die(":".$rep);
				}
				
# Replace aliases with the real field names for REP_COL_NAME
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA]))
				foreach($GLOBALS["STORED_REPS"][$id][REP_COL_NAME] as $key => $value)
					if(strlen($value) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]))
					{
					    $key = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key];
					    trace("field formula $value to be replaced with $key in $field ");
						$field = str_replace('\'['.$value.']\'', $key, $field); # First replace [FIELD]'s
						$field = str_replace('['.$value.']', $key, $field); # replace numeric [FIELD]'s
						$filter = str_replace('\'['.$value.']\'', $key, $filter); # First replace [FIELD]'s
						$filter = str_replace('['.$value.']', $key, $filter); # replace numeric [FIELD]'s
						$filter = str_replace($value, $key, $filter);
						$masterFilter = str_replace('\'['.$value.']\'', $key, $masterFilter); # First replace [FIELD]'s
						$masterFilter = str_replace('['.$value.']', $key, $masterFilter); # replace numeric [FIELD]'s
						$masterFilter = str_replace($value, $key, $masterFilter);
						if(isset($group))
    						$group = str_replace('['.$value.']', $key, $group);
						if(isset($groupBy))
    						$groupBy = str_replace('['.$value.']', $key, $groupBy);
						if(isset($order))
    						$order = str_replace('['.$value.']', $key, $order); # replace numeric [FIELD]'s
						if(isset($sortByArr))
    						$sortByArr = str_replace('['.$value.']', $key, $sortByArr); # replace numeric [FIELD]'s
						if(isset($having))
    						$having = str_replace('['.$value.']', $key, $having);
						$sql = str_replace('\'['.$value.']\'', $key, $sql); # replace numeric [FIELD]'s
						$sql = str_replace('['.$value.']', $key, $sql); # replace numeric [FIELD]'s
					}

# Replace aliases with the real field names
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA]))
				foreach($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA] as $key => $value)
					if(strlen($value) && ($GLOBALS["STORED_REPS"][$id]["types"][$key] != ""))
					{
					    trace("formula $key => $value - to be replaced with ".$fields[$key]." in $field ");
						$field = str_replace('\'['.$value.']\'', $fields[$key], $field); # First replace [FIELD]'s
						$field = str_replace('['.$value.']', $fields[$key], $field); # replace numeric [FIELD]'s
						$field = str_replace($value, $fields[$key], $field);
						$filter = str_replace('\'['.$value.']\'', $fields[$key], $filter); # First replace [FIELD]'s
						$filter = str_replace('['.$value.']', $fields[$key], $filter); # replace numeric [FIELD]'s
						$filter = str_replace($value, $fields[$key], $filter);
						$masterFilter = str_replace('\'['.$value.']\'', $fieldsOrig[$key], $masterFilter); # First replace [FIELD]'s
						$masterFilter = str_replace('['.$value.']', $fieldsOrig[$key], $masterFilter); # replace numeric [FIELD]'s
						$masterFilter = str_replace($value, $fieldsOrig[$key], $masterFilter);
						if(isset($group))
						{
    						$group = str_replace('\'['.$value.']\'', $fields[$key], $group); # First replace [FIELD]'s
    						$group = str_replace('['.$value.']', $fields[$key], $group); # replace numeric [FIELD]'s
    						$group = str_replace($value, $fields[$key], $group);
    						$groupBy = str_replace('\'['.$value.']\'', $fieldsOrig[$key], $groupBy); # First replace [FIELD]'s
    						$groupBy = str_replace('['.$value.']', $fieldsOrig[$key], $groupBy); # replace numeric [FIELD]'s
    						$groupBy = str_replace($value, $fieldsOrig[$key], $groupBy);
						}
						if(isset($order))
						{
    						$order = str_replace('\'['.$value.']\'', $fields[$key], $order); # First replace [FIELD]'s
    						$order = str_replace('['.$value.']', $fields[$key], $order); # replace numeric [FIELD]'s
    						$order = str_replace($value, $fields[$key], $order);
    						$sortByArr = str_replace('\'['.$value.']\'', $fields[$key], $sortByArr); # First replace [FIELD]'s
    						$sortByArr = str_replace('['.$value.']', $fields[$key], $sortByArr); # replace numeric [FIELD]'s
    						$sortByArr = str_replace($value, $fields[$key], $sortByArr);
						}
						if(isset($having))
    						$having = str_replace('\'['.$value.']\'', $fields[$key], $having);
						$sql = str_replace('\'['.$value.']\'', $fields[$key], $sql); # replace numeric [FIELD]'s
						$sql = str_replace('['.$value.']', $fields[$key], $sql); # replace numeric [FIELD]'s
						
						$fieldsAll = str_replace('\'['.$value.']\'', $fieldsOrig[$key], $fieldsAll); # First replace [FIELD]'s
						$fieldsAll = str_replace('['.$value.']', $fieldsOrig[$key], $fieldsAll); # replace numeric [FIELD]'s
						$fieldsAll = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $fieldsAll);
						$joinedClause = str_replace('\'['.$value.']\'', $fieldsOrig[$key], $joinedClause); # First replace [FIELD]'s
						$joinedClause = str_replace('['.$value.']', $fieldsOrig[$key], $joinedClause); # replace numeric [FIELD]'s
						$joinedClause = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $joinedClause);
						$joinedOn = str_replace('\'['.$value.']\'', $fieldsOrig[$key], $joinedOn); # First replace [FIELD]'s
						$joinedOn = str_replace('['.$value.']', $fieldsOrig[$key], $joinedOn); # replace numeric [FIELD]'s
						$joinedOn = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $joinedOn);
					}

# Apply LIMIT value, if set online in the report
            if(isset($_REQUEST["RECORD_COUNT"]))
				$limit = "";
			elseif(isset($GLOBALS["STORED_REPS"][$id]["params"][REP_LIMIT]))	#  or in the report definition
			{
				$limit = "LIMIT ";
				$limits = explode(",", $GLOBALS["STORED_REPS"][$id]["params"][REP_LIMIT]);
				if(isset($_REQUEST["LIMIT"])){	# Do not exceed the predefined limit
					$req_limits = explode(",", $_REQUEST["LIMIT"]);
					if(isset($limits[1])){ # Given: offset, limit1
						if(isset($req_limits[1])) # Requested: offset, limit
							$limit .= (int)$req_limits[0] # Requested offset
									.",". min((int)$req_limits[1],(int)$limits[1]); # Min of Given and Requested limit
						else	# Requested: limit
							$limit .= min((int)$req_limits[0],(int)$limits[1]); # Requested offset, given limit
					}
					else{	# Given: limit
						if(isset($req_limits[1])) # Requested: offset, limit
							$limit .= (int)$req_limits[0] # Requested offset
									.",". min((int)$req_limits[1],(int)$limits[0]); # Min of Given and Requested limit
						else	# Requested: limit
							$limit .= min((int)$req_limits[0],(int)$limits[0]); # Requested offset, given limit
					}
				}
				else
					$limit = "LIMIT ".(int)$limits[0].(isset($limits[1]) ? ",".(int)$limits[1] : "");
			}
			elseif(isset($_REQUEST["LIMIT"]))
			{
				$limits = explode(",", $_REQUEST["LIMIT"]);
				if((int)$limits[0] > 0)
    				$limit = "LIMIT ".(int)$limits[0].(isset($limits[1]) ? ",".(int)$limits[1] : "");
    			else
    				$limit = "";
			}
			else
				$limit = "";
# Compile the SELECT
/*			if(strlen($sql))
				$sql = "SELECT $distinct $field FROM $sql WHERE $cond $filter "
				        . (isset($group) ? $group : " ") . (isset($having) ? $having : " ")
				        . (isset($order) ? $order : " ") . " $limit";
			else	# There are only calculatible fields in the report
				$sql = "SELECT $field " . (strlen($filter) ? " FROM dual WHERE ".substr($filter,4) : "") . $having;
*/            
            #mywrite("\r\n".$GLOBALS["STORED_REPS"][$id]["header"]);
		    if(strlen($sql))
		    {
#		        if($exe)
#               	mywrite("$sql");
    		    $sql = "";
    		    foreach($displayVal as $k => $v)
        		    $sql .= "," . $fieldsAll[$k] ." ". $displayName[$k];
        		$sql = "\r\nSELECT " . substr($sql, 1);
    		    $sql .= "\r\nFROM ".$tables[$master];
    		    if(isset($joinedJoin["$p$master"]))
        			foreach($joinedJoin["$p$master"] as $j)
    	    		    $sql .= "\r\n   $j";
    			foreach($joined as $k => $v)
    			{
    			    
    			    if($k != "$p$master")
            		    $sql .= "\r\n  LEFT JOIN (SELECT ".implode(",", $joined[$k]);
        		    if(isset($joinedFrom[$k]))
            		    $sql .= "\r\n  ".$joinedFrom[$k];
        		    if(($k != "$p$master") && isset($joinedJoin[$k]))
            			foreach($joinedJoin[$k] as $j)
        	    		    $sql .= "\r\n   $j";
    	    		    
        		    if(isset($joinedClause[$k]))
        		    {
            		    $sql .= "\r\n   ".$joinedClause[$k];
            		    if(preg_match("/\b(sum|avg|count|min|max)\b\(/i", implode(",",$joined[$k])))
                            foreach($joined[$k] as $gr)
                                if(!preg_match("/\b(sum|avg|count|min|max)\b\(/i", $gr))
                                {
            						$tmp = explode(" ", $gr);
            						$gr = array_pop($tmp);
                                    if(isset($groupBy[$k]))
                                        $groupBy[$k] .= ",$gr";
                                    else
                                        $groupBy[$k] .= "GROUP BY $gr";
                                }
                        if(isset($groupBy[$k]))
                		    $sql .= "\r\n   ".$groupBy[$k];
        		    }
        		    if(isset($joinedOn[$k]))
            		    $sql .= "\r\n   ".$joinedOn[$k];
    			}
    		    $sql .= "\r\nWHERE $cond $masterFilter "
    				        . (isset($groupBy[$master]) ? "\r\n".$groupBy[$master] : " ") . (isset($having) ? $having : " ")
    				        . (isset($sortByArr) ? "\r\nORDER BY ".implode(",", $sortByArr) : " ") . " $limit";
                
		    }
			else	# There are only calculatible fields in the report
				$sql = "SELECT $field " . (strlen($filter) ? " FROM dual WHERE ".substr($filter,4) : "") . $having;

	        if($exe)
                mywrite($sql);

			if(isset($_REQUEST["SELECT"]))
			{
            	preg_match_all("/:(\d+):/", $sql, $cols); # Check if we got field IDs to replace with field values
            	foreach($cols[1] as $k => $v)
            	    if(isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]))
            	        $sql = str_replace(":$v:",$fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]],$sql);
            	trace("IDs to replace: ".print_r($cols, TRUE));
			}
# Save the Report, it might be required again this session
			$GLOBALS["STORED_REPS"][$id]["sql"] = $sql;
		}
	}
# Return in case we construct a sub-query
	if(!$exe)
	{
		if(isset($GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL]))	# Apply IF_NULL value, if set
			$GLOBALS["STORED_REPS"][$id]["sql"] = "COALESCE((".$GLOBALS["STORED_REPS"][$id]["sql"]."),"
															.$GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL].")";
		return;
	}
# The query is already prepared - just replace the IDs in it, if any
	foreach($_REQUEST as $key => $value)
		if((substr($key, 0, 1) == "i") && ((int)$value != 0))
			$GLOBALS["STORED_REPS"][$id]["sql"] = str_replace("%".substr($key, 1)."_OBJ_ID%", (int)$value, $GLOBALS["STORED_REPS"][$id]["sql"]);

   	$sql = $GLOBALS["STORED_REPS"][$id]["sql"];
	if(isset($GLOBALS["NO_CACHE"]))	# There were insertion points from the current parent block, do not cache the SQL
		unset($GLOBALS["STORED_REPS"][$id]["sql"]);
	elseif($sql == $GLOBALS["STORED_REPS"][$id]["sql"]) # Check if we got changes in the SQL since the last build (Obj ID or something)
	{
		if(isset($GLOBALS["STORED_REPS"][$id]["last_res"]))
		{
			$blocks["_data_col"][$id] = $GLOBALS["STORED_REPS"][$id]["last_res"];
			if(isset($GLOBALS["STORED_REPS"][$id]["last_totals"]))
    			$blocks["col_totals"][$id] = $GLOBALS["STORED_REPS"][$id]["last_totals"];
			return;
		}
		elseif(isset($GLOBALS["STORED_REPS"][$id]["last_res_empty"]))
			return;
	}
	if(isset($GLOBALS["TRACE"]))
	    mywrite($sql);
	if(isset($_REQUEST["RECORD_COUNT"]))
	{
	    trace("RECORD_COUNT set");
    	$data_set = Exec_sql("SELECT COUNT(1) FROM ($sql) temp", "Request report data");
    	if($row = mysqli_fetch_array($data_set))
    	    if(isset($_REQUEST["JSON"]) || isApi() || isset($args["json"]))
            	die('{"count":"'.$row[0].'"}');
    	    else
    	        die($row[0]);
	}
	$data_set = Exec_sql($sql, "Request report data");
	$rownum = 1;
	$GLOBALS["STORED_REPS"][$id]["last_res_empty"] = 1;
	$GLOBALS["STORED_REPS"][$id]["rownum"] = mysqli_num_rows($data_set);
	foreach($GLOBALS["STORED_REPS"][$id]["names"] as $key => $value)
	    if(substr($value,0,1) == "'")
	    {
	        //trace("mbstrlen ".$value." ".strlen($value)." ".mb_strlen($value)." (".str_replace("\\'", "'", substr($value, 1, strlen($value)-2)).") (".str_replace("\\'", "'", substr($value, 1, mb_strlen($value)-2)).")");
	        $names[$key] = $GLOBALS["STORED_REPS"][$id]["names"][$key] = str_replace("\\'", "'", substr($value, 1, strlen($value)-2));
	    }
	if((mysqli_num_rows($data_set) == 0) && isset($GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL])) # IF_NULL set
	{
		$GLOBALS["STORED_REPS"][$id]["rownum"] = 1;	# Fill in one line of the report with the given IF_NULL values
		foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)	# Set IF_NULL value in case of an empty report
			if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # Not hidden column
				$blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][] = $GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL];
	}
	elseif(mysqli_num_rows($data_set) == 0) # The rep is empty
	{
		foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value) # Create empty report cols
			if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # Not hidden column
				$blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]] = Array();
	}
	else
	while($row = mysqli_fetch_array($data_set))
	{
		foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
		{
			if(!isset($names[$key]))
				$names[$key] = $GLOBALS["STORED_REPS"][$id]["names"][$key] = "update";
			$value = $names[$key];
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) # A hidden column
			{
				unset($GLOBALS["STORED_REPS"][$id]["head"][$key]); # Discard this record and fetch the next
				if(!isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]))
					continue; # Nothing more to do here
			}
			$typ = isset($GLOBALS["STORED_REPS"][$id]["types"][$key]) ? $GLOBALS["STORED_REPS"][$id]["types"][$key] : NULL;
			$base_str = isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]) ? $GLOBALS["STORED_REPS"][$id]["base_out"][$key] : NULL;
			$base = isset($GLOBALS["BT"][$base_str]) ? $GLOBALS["BT"][$base_str] : NULL;
			$val = isset($row[$value]) ? $row[$value] : "";
			if(isset($row["t$typ"]))  # Get the tail, if we have one
				if($row["t$typ"])
					$val = Get_tail($row["t$typ"], $val);
#if($key==6)
#{	print_r($GLOBALS);die(":");}
			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
				switch($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]) # Internal functions are named abn_XXXX
				{
					case "abn_DATE2STR":
						include_once "include/funcs.php";
						$val = abn_DATE2STR($val);
						$base = $GLOBALS["BT"]["SHORT"]; # Replace the base type with STRING
						break;
					case "abn_NUM2STR":
						include_once "include/funcs.php";
						$val = abn_NUM2STR($val);
						$base = $GLOBALS["BT"]["SHORT"];
						break;
					case "abn_RUB2STR":
						include_once "include/funcs.php";
						$val = abn_RUB2STR($val);
						$base = $GLOBALS["BT"]["SHORT"];
						break;
					case "abn_Translit":
						include_once "include/funcs.php";
						$val = abn_Translit($val);
						$base = $GLOBALS["BT"]["SHORT"];
						break;
					case "abn_ROWNUM":
						$val = $rownum++;
						break;
					case "abn_URL":
						$val = $GLOBALS["STORED_REPS"][$id]["params"][REP_URL];
						if(strlen($val) && !isset($file_failed))
						{
                            $host=strtolower(parse_url($val, 1));
                            if((strtolower($_SERVER["HTTP_HOST"]) === $host) || ($host === "localhost")
                                    || ((false !== filter_var($host, FILTER_VALIDATE_IP)) && (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))))
                                $val = $file_failed = "You cannot access your own server!";
							elseif(substr(parse_url($val, 0), 0, 4) == 'http')
							{
								if(is_array($GLOBALS["STORED_REPS"][$id][REP_COL_NAME]))
									foreach($GLOBALS["STORED_REPS"][$id][REP_COL_NAME] as $i => $a)
										if(strpos($val, "[$a]"))
											$val = str_replace("[$a]", rawurlencode($cur_line[$i]), $val);
#print_r($GLOBALS);die($_SERVER["HTTP_HOST"].":".parse_url($val, 1));
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_HEADER, 0);
								curl_setopt($ch, CURLOPT_HTTPHEADER, array("User-Agent: Integral"));
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
								curl_setopt($ch, CURLOPT_URL, $val);
								$val = curl_exec($ch);
								if(curl_errno($ch)){
									$val = curl_errno($ch).": $val";
									$file_failed = true;
								}
								curl_close($ch);
							}
							else
								$val = $file_failed = "URL must use https or http";
						}
						break;
				}
#			trace("Format $val base:$base - base_str:$base_str id:".$row["i$key"]);
			if(isset($GLOBALS["STORED_REPS"][$id]["params"][REP_HREFS]) && isset($names["i$key"])
					&& !isset($GLOBALS["STORED_REPS"]["parents"][$typ]))	# Make HREFS in case of interactive report
			{
				if(($base_str == "PATH") || ($row["i$key"] < 2))
					$val = "<span>".Format_Val_View($base, $val, $row["i$key"])."</span>";
				else
					$val = "<a target=\"$key\" href=\"/$z/edit_obj/".$row["i$key"]."\">".Format_Val_View($base, $val, $row["i$key"])."</a>";
			}
			elseif($base_str == "PATH")
				$val = strlen($val) ? Format_Val_View($base, $val, $row["i$key"]) : "";
			elseif($base_str == "HTML")
				$val = strlen($val) ? str_ireplace("{_global_.z}", $z, $val) : "";
			elseif($base_str == "FILE")
				$val = strlen($val) ? Format_Val_View($base, $val, $row["i$key"]) : "";
			else
				$val = strlen($val) ? htmlspecialchars(Format_Val_View($base, $val)) : "";
			$blocks["_data_col"][$id][$value][] = $val;

            if(isset($GLOBALS["STORED_REPS"][$id]["params"][REP_URL]))
    			if(strlen($GLOBALS["STORED_REPS"][$id]["params"][REP_URL]))
    				$cur_line[$key] = $val;
#print_r($GLOBALS["STORED_REPS"][$id]["head"]);print_r($blocks["_data_col"][$id]);print_r($GLOBALS);die();
# Count totals for the Column
            if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL]))
            {
                if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key]))
    			{
    				switch(strtoupper($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key]))
    				{
    					case "COUNT":
    						if(isset($blocks["col_totals"][$id][$value]))
    							$blocks["col_totals"][$id][$value] = $blocks["col_totals"][$id][$value] + 1;
    						else
    							$blocks["col_totals"][$id][$value] = 1;
    						break;
    					case "AVG":
    					case "SUM":
    						if(isset($blocks["col_totals"][$id][$value]))
    							$blocks["col_totals"][$id][$value] = (float)$blocks["col_totals"][$id][$value] + (float)$row[$value];
    						break;
    					case "MIN":
    						if(isset($blocks["col_totals"][$id][$value]))
    							if($blocks["col_totals"][$id][$value] > $row[$value])
    								$blocks["col_totals"][$id][$value] = $row[$value];
    						break;
    					case "MAX":
    						if(isset($blocks["col_totals"][$id][$value]))
    							if($blocks["col_totals"][$id][$value] < $row[$value])
    								$blocks["col_totals"][$id][$value] = $row[$value];
    						break;
    					default:	# Wrong TOTAL function name
    						$blocks["col_totals"][$id][$value] = "".$GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key];
    						break;
    				}
        			if(!isset($blocks["col_totals"][$id][$value])) // The first value for AVG, MIN, etc
        				$blocks["col_totals"][$id][$value] = $row[$value];
    			}
    			if(!isset($blocks["col_totals"][$id][$value]))
    				$blocks["col_totals"][$id][$value] = "";
            }
		}
# Prepare the UPDATE list
		if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET]))
		{
			while(!isset($ready))
			{
				$ready = 0;	# Assume, we got all the ID links for new records
				$progress = FALSE;	# Some more records created this round
				$rec = count($blocks["_data_col"][$id]["update"])-1;	# Save the line number of the report to show the result there
				foreach($GLOBALS["STORED_REPS"][$id][REP_COL_SET] as $key => $val)
				{
				    trace("REP_COL_SET $key => $val");
					$typ = $GLOBALS["STORED_REPS"][$id]["types"][$key];
					$parent = isset($GLOBALS["STORED_REPS"]["parents"][$typ]) ? $GLOBALS["STORED_REPS"]["parents"][$typ] : 0;
					if($new = ($row["i$key"] == 0))	# Is it a new rec?
					{
						$o = 1;
						if($parent == 0)	# We are parent
						{
        				    trace("_ We are parent");
							if(isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ]))	# and somebody's array Req
							{	# Get our parent's ID
								$u = $row["i".$GLOBALS["STORED_REPS"][$id]["PARENT"][$typ]];
								$o = 0; # We'll need the Order value
							}
							else	# we are no one's Req
								$u = 1;
						}
						else	# We are a Req
							$u = $row["i$parent"];
						
						if($u == 0)	# Our parent doesn't exist yet
						{
        				    trace("_ Our parent doesn't exist yet");
                            foreach($blocks["_update"] as $upd => $col) // Find the column where our parent is being created
                                if(isset($col["t"]))
                                    if(isset($col["t"][$rec]))
                                        if(($col["t"][$rec] == $parent) && isset($col["new_id"][$rec]))
                                        {
                        				    trace("__ Our parent will be");
            								$u = $upd."_".$col["new_id"][$rec];
                        				    trace("__ Our parent will be $u");
            								$new_id_needed[$u] = "";	# Mark the parent ID required to save for it's Up for our Req
            								break;
                                        }
							if($u == 0) // If no parent to be created, though we need a parent ID for this Req
							{
            				    trace("__ no parent to be created, though we need a parent ID for this Req");
								unset($ready);
								continue;	# skip it and process the next
							}
						}
						$i = $u;
						if(!isset($blocks["_update"][$key]["id"][$i]))
							$blocks["_update"][$key]["new_id"][$rec] = $i;	# Form the Link to new ID, in case we'll need it
					}
					else
						$i = $row["i$key"];

					if(isset($blocks["progress"][$key]["id"][$i]))	# The UPDATE params were already prepared for this record
						continue;

                    trace("_ The value to set is row[u".$key."]=".$row["u$key"]);
					$progress = TRUE;
					$blocks["progress"][$key]["id"][$i] = true;
					$blocks["_update"][$key]["id"][$i] = $rec;
					if($row["u$key"] == "")
					{
						if($new) # Do we have a Value to delete?
							unset($blocks["_update"][$key]["id"][$i]);
						else
						{
							$blocks["_update"][$key]["delete"][$rec] = "";
							$blocks["_data_col"][$id]["update"][$rec] .= "<s>".$GLOBALS["STORED_REPS"][$id]["head"][$key]."</s> (удалить)<br>";
						}
						continue;
					}
					if($new)	# No need to save Up and Ord for UPDATE statement
					{
						$blocks["_update"][$key]["up"][$rec] = $u;
						$blocks["_update"][$key]["ord"][$rec] = $o;
					}
					if(isRef($id, $par, $typ))
					{
						$blocks["_update"][$key]["t"][$rec] = $row["u$key"];
    				    trace("Our type is $typ");
						if($new)
							$blocks["_update"][$key]["val"][$rec] = $typ;
						# We'll show the pre-Update advice: what's added/changed during this UPDATE
						$blocks["_data_col"][$id]["update"][$rec] .= $GLOBALS["STORED_REPS"][$id]["head"][$key].($new ? ": #" : " => #").$row["u$key"]."<br>";
					}
					else
					{
						$blocks["_update"][$key]["val"][$rec] = Format_Val($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_in"][$key]], $row["u$key"]);
						trace("__ formatted value is ".$blocks["_update"][$key]["val"][$rec]);
						trace("__ base type: ".$GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_in"][$key]]);
						/*
						if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA])) # We might have formulas to replace the value
							if($i = array_search($val, $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA])) # Replace the value, if we found the formula
								$blocks["_update"][$key]["val"][$rec] = Format_Val($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_in"][$key]], $cur_line[$i]);
						*/
						trace("__ formula applied, value is ".$blocks["_update"][$key]["val"][$rec]);
						$blocks["_data_col"][$id]["update"][$rec] .= $GLOBALS["STORED_REPS"][$id]["head"][$key].": ";
						if($new)
							$blocks["_update"][$key]["t"][$rec] = $GLOBALS["STORED_REPS"][$id]["types"][$key];
						else
						{
							# Indicate the old Value in the pre-Update advice
							$blocks["_data_col"][$id]["update"][$rec] .= $blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][$rec]." => ";
							if(strlen($blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][$rec]) > VAL_LIM)
								$blocks["_update"][$key]["tail"][$rec] = ""; # We have tails of the old value
						}
						$blocks["_data_col"][$id]["update"][$rec] .= Format_Val_View($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]]
																					, $blocks["_update"][$key]["val"][$rec])."<br>";
#						if($blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][$rec] == $blocks["_update"][$key]["val"][$rec])
#                           unset($blocks["_update"][$key]["id"][$rec]); # If the values are equal, discard the change
					}
				}
				if(!isset($ready) && !$progress)	# We still need new IDs links, but no links created this round - no hope
					$ready = 0;	//	die_info("Не могу найти родительский ID");
			}
			unset($ready);
		}
	}
#print_r($blocks["_data_col"]);print_r($blocks["_update"]);print_r($GLOBALS);die();
# Execute UPDATEs
	if(isset($blocks["_update"]) && isset($_REQUEST["confirmed"]))	# We have a confirmation to UPDATE
	{
		check();
		foreach($blocks["_update"] as $key => $value)
			foreach($value["id"] as $i => $n)
				if(isset($value["ord"][$n]))	# A new record
				{
				    trace("A new record under ".$value["up"][$n]);
					if(isset($new_id_needed[$value["up"][$n]])) # Fetch the new ID of our parent
						$value["up"][$n] = $new_id_needed[$value["up"][$n]];
					if(isset($new_id_needed[$key."_".$value["new_id"][$n]]))	# Save the new ID in case some Reqs need to know it
						$new_id_needed[$key."_".$value["new_id"][$n]] = Insert($value["up"][$n]
												, $value["ord"][$n] == 0 ? Calc_Order($value["up"][$n], $value["t"][$n]) : $value["ord"][$n]
												, $value["t"][$n], $value["val"][$n], "INSERT new rec, get ID");
					else
						Insert($value["up"][$n], $value["ord"][$n] == 0 ? Calc_Order($value["up"][$n], $value["t"][$n]) : $value["ord"][$n]
									, $value["t"][$n], $value["val"][$n], "INSERT new rec");
				}
				elseif(isset($value["delete"][$n]))
					Delete($i);
				elseif(!isset($value["val"][$n]))
					Exec_sql("UPDATE $z SET t=".$value["t"][$n]." WHERE id=$i", "UPDATE Ref");
				else
					Update_Val($i, Format_Val($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]], $value["val"][$n]), !isset($value["tail"][$n]));
		unset($blocks["_update"]);
	}
# Format the Totals values according to their type
#print_r($blocks["col_totals"][$id]);print_r($GLOBALS);die();
#print_r($blocks["_data_col"][$id]);print_r($GLOBALS);die();
	if(isset($blocks["col_totals"][$id]))
		foreach($blocks["col_totals"][$id] as $key => $value)
			{
				$k = array_search($key, $GLOBALS["STORED_REPS"][$id]["names"]);
				if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$k]))
    				if(strtoupper($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$k]) == "AVG")
    					$value = $value / $GLOBALS["STORED_REPS"][$id]["rownum"];
    			if(isset($GLOBALS["STORED_REPS"][$id]["base_out"][$k]))
    				$blocks["col_totals"][$id][$key] = Format_Val_View($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$k]], $value);
    			else
    			    $blocks["col_totals"][$id][$key] = $value;
    			if(isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$k]))
    				if($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$k] == "COUNT")
    					$blocks["col_totals"][$id][$key] = Format_Val_View($GLOBALS["BT"]["NUMBER"], $value);
			}
	if((isApi() || isset($args["json"])) && ($GLOBALS["GLOBAL_VARS"]["action"] == "report"))
	{
	    $i = 0;
		foreach($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
    	    if(isset($field_names[$key])) # Only those values selected
    		{
    			$GLOBALS["STORED_REPS"][$id]["last_res"][$value] = count($blocks["_data_col"][$id]) ? array_shift($blocks["_data_col"][$id]) : "";
    		    $json["columns"][$i]["id"] = $GLOBALS["STORED_REPS"][$id]["columns"][$key];
    		    $json["columns"][$i]["format"] = $GLOBALS["STORED_REPS"][$id]["base_out"][$key];
    		    $json["columns"][$i]["name"] = $value;
    		    $i++;
    		}
	    $i = 0;
    	foreach($GLOBALS["STORED_REPS"][$id]["last_res"] as $rs)
		    $json["data"][$i++] = $rs;
	    $i = 0;
    	if(isset($blocks["col_totals"][$id]))
    	    foreach($blocks["col_totals"][$id] as $v)
    		    $json["columns"][$i++]["totals"] = $v;
		api_dump(json_encode($json), $GLOBALS["STORED_REPS"][$id]["header"].".json");
	}
# Remember the last result
	$GLOBALS["STORED_REPS"][$id]["last_res"] = $blocks["_data_col"][$id];
	if(isset($blocks["col_totals"][$id]))
    	$GLOBALS["STORED_REPS"][$id]["last_totals"] = $blocks["col_totals"][$id];
}
?>
