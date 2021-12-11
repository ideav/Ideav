<?php
# Make WHERE and JOIN statements for reports
function Construct_WHERE($key, $filter, $cur_typ, $join_req=0, $ignore_tailed=FALSE)
{
    trace("Construct_WHERE for $key, filter: ".print_r($filter, true).", cur_typ: $cur_typ");
	global $z;
	$join = $join_req!=0;
	foreach($filter AS $f => $value)
	{
		$is_date = FALSE; # Later we'll enclose date values in quotes $value => '$value'

		if(substr($value, 0, 1) == "!") # a NOT prefix in the equation
		{
			$NOT = "NOT";
			$NOT_EQ = "!";
			$value = substr($value, 1);
			$NOT_flag = TRUE;
		}
		else
		{
			$NOT = $NOT_EQ = "";
			$NOT_flag = FALSE;
		}
		if(strpos($value, "."))	# Get data from any existing block, identified by it's name
		{
			$block = ".".strtolower(substr($value, 0, strpos($value, "."))); # Get the block name
			$len = strlen($block); # Block name length including the leading dot (".")
			foreach($GLOBALS["blocks"] as $block_id => $val) # Seek blocks
				if(substr($block_id, -$len) == $block)		# with names ending like the given one
					if(isset($val["CUR_VARS"][strtolower(substr($value, strpos($value, ".")+1))]))
					{
						$value = $val["CUR_VARS"][strtolower(substr($value, strpos($value, ".")+1))];
						$GLOBALS["NO_CACHE"] = "";
						break;
					}
		}
		$value = BuiltIn($value); # Check for a built-in phrases
		if($value == "%")
			$search_val = "IS ".($NOT_flag ? "" : "NOT")." NULL";
		else
		{ # If we have a [substitute], don't add '' and slash the existing ''
			$v = preg_match("/\[([^\[\]]+)\]/", $value) ? $value : "'".addslashes($value)."'";
			if(strpos($value, "%") === FALSE)
				$search_val = "$NOT_EQ=$v";
			else
				$search_val = "$NOT LIKE $v";
		}
# Search by ID: @999999 or !@999999
		if(substr($value, 0, 1) == "@")
		{
			$value = (int)str_replace(" ","",substr($value, 1));
			if($key == $cur_typ)
				$GLOBALS["where"] .= " AND vals.id$NOT_EQ=$value ";
			else
			{
				if($GLOBALS["REV_BT"][$key] == "ARRAY")
					$GLOBALS["distinct"] = "DISTINCT"; # Array might return multiple rows, so we have to remove the dupes
				if($NOT_flag)
				{
					if($GLOBALS["REV_BT"][$key] == "REFERENCE")
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND a$key.t=".$GLOBALS["REF_typs"][$key]
												." AND r$key.t=a$key.id AND r$key.val='$join_req'";
						$GLOBALS["where"] .= " AND (a$key.id!=$value OR a$key.id IS NULL)";
					}
					else
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key";
						$GLOBALS["where"] .= " AND (a$key.id!=$value OR a$key.id IS NULL)";
					}
				}
				else 
				{
				    trace(" Check if GLOBALS[REV_BT][$key] ".$GLOBALS["REV_BT"][$key]." == REFERENCE");
					if(isset($GLOBALS["REF_typs"][$key]))
					{
						if($join)
							$GLOBALS["join"] .= " JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND r$key.t=a$key.id AND r$key.val='$join_req' AND r$key.t=$value";
						$GLOBALS["where"] .= " AND a$key.id=$value";
					}
					else
					{
						if($join)
							$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.id=$value";
						$GLOBALS["where"] .= " AND a$key.id=$value";
					}
				}
			}
			break;
		}
# Construct WHERE according to the data type
        trace("_ REV_BT: ".$GLOBALS["REV_BT"][$key]);
        if(isset($GLOBALS["REF_typs"][$key])) # Filter was applied to the Object's Ref Value or ID
        {
    		if($join)
    			$GLOBALS["join"] .= " LEFT JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND r$key.t=a$key.id
    									 AND r$key.val='$join_req' AND a$key.t=".$GLOBALS["REF_typs"][$key];
    		if($NOT_flag) # No match or empty
    			$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
    		else
    			$GLOBALS["where"] .= " AND a$key.val $search_val ";
	    }
        else
		switch($GLOBALS["REV_BT"][$key])
		{
			case "CHARS":
			case "FILE":
			case "MEMO":
				if($value == "%")
				{
					if($join)
						$GLOBALS["join"] .= ($NOT_flag ? " LEFT" : "")." JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
					$GLOBALS["where"] .= " AND a$key.val $search_val ";
				}
				elseif(((mb_strlen($value) <= VAL_LIM) && (strpos($value, "%") === FALSE)) # No tail & index is possible, just like SHORT
							|| $ignore_tailed)	# Or we tread all tailed as SHORT
				{
					if($key == $cur_typ)	# Filter was applied to the Object's Value
						$GLOBALS["where"] .= " AND vals.val $search_val ";
					else
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						if($NOT_flag)
							$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)"; # No match or empty
						else
							$GLOBALS["where"] .= " AND a$key.val $search_val ";
					}
				}
				elseif((substr($value, 0, 1) != "%") # Index is still possible
						&& ((substr($value, 0, 1) != "_") || (strpos($value, "%") === FALSE))) # It's not a LIKE condition
				{
					$GLOBALS["distinct"] = "DISTINCT"; # Tails might return more than one row
					if(strpos($value, "%") === FALSE)
						$short_search_val = "$NOT_EQ='".mb_substr($value, 0, VAL_LIM)."'";
					else
						$short_search_val = "$NOT LIKE '".mb_substr($value, 0, min(mb_strpos($value, '%', 0), mb_strpos($value, '%', 0)))."%'";

					if($key == $cur_typ)		# Filter was applied to the Object's Value
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN $z t$key ON t$key.up=vals.id AND t$key.t=0 "
									. " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
						$GLOBALS["where"] .= " AND vals.val $short_search_val
										AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE vals.val END
												, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
					}
					else
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key
												LEFT JOIN $z t$key ON t$key.up=a$key.id AND t$key.t=0 "
											. " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
						if($NOT_flag) # No match or empty
							$GLOBALS["where"] .= " AND ((a$key.val $short_search_val 
													AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE a$key.val END
														, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val)
												OR a$key.val IS NULL) ";
						elseif(strpos($value, "%") == strlen($value) - 1) # The only % is the ending one
							$GLOBALS["where"] .= " AND a$key.val $short_search_val ";
						else  # There might be something like "1,2,3,%997,998,999" , thus we need to fetch the tail
							$GLOBALS["where"] .= " AND a$key.val $short_search_val "
									. " AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE a$key.val END
												, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
					}
				}
				elseif($key == $cur_typ)	# Filter was applied to the Object's Value
				{
					if($join)
					{
						$GLOBALS["distinct"] = "DISTINCT"; # Tails might return more than one row
						$GLOBALS["join"] .= "LEFT JOIN $z t$key ON t$key.up=vals.id AND t$key.t=0 "
							. " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
					}
					$GLOBALS["where"] .= " AND concat(vals.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
				}
				else
				{
					if($join)
					{
						$GLOBALS["distinct"] = "DISTINCT"; # Tails might return more than one row
						$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key
												LEFT JOIN $z t$key ON t$key.up=a$key.id AND t$key.t=0 "
								. " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
					}
					if($NOT_flag) # No match or empty
						$GLOBALS["where"] .= " AND (CONCAT(a$key.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val 
											OR a$key.val IS NULL)";
					else
						$GLOBALS["where"] .= " AND CONCAT(a$key.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
				}
				break;

			case "ARRAY":	# Filter was applied to the Object's Arr Value
#print_r($GLOBALS);print_r($blocks);die("$"."f = ".$f);
				$GLOBALS["distinct"] = "DISTINCT"; # Array might return multiple rows, so we have to remove the dupes
				if($f == "F")
				{
					if($join)
						$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key";
					if($NOT_flag) # No match or empty
						$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
					else
						$GLOBALS["where"] .= " AND a$key.val $search_val ";
				}
				else # Check if we got only one range border, and then transform it into an exact match
				if((!isset($filter["TO"])) || (!isset($filter["FR"])))
				{
					if($join)
						$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
					if($value != "%")
					{
						if($NOT_flag) # No match or empty
							$GLOBALS["where"] .= " AND (a$key.val$NOT_EQ='$value' OR a$key.val IS NULL)";
						else
							$GLOBALS["where"] .= " AND a$key.val='$value'";
					}
					else
						$GLOBALS["where"] .= " AND a$key.val $search_val ";
				}
				elseif(isset($filter["TO"]) && isset($filter["FR"]))
				{	# Apply range filter
					if($value == 0)  # Add '...' in case $value isn't numeric
						$value = "'$value'";
					else
						$value = (float)$value;
					
					if($f == "FR")  # Range FROM
					{
						if($join)
							$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						$GLOBALS["where"] .= " AND a$key.val>=$value ";
					}
					elseif($f == "TO")   # Range TO
						$GLOBALS["where"] .= " AND a$key.val<=$value ";
				}
				break;

			case "DATE":
				$is_date = TRUE;
			case "DATETIME":
				if($value != "%")
					$value = Format_Val($GLOBALS["BT"][$GLOBALS["REV_BT"][$key]], $value);
			case "NUMBER":
			case "SIGNED":
			    # We might get a statement here, thus check if we got no letters and stuff
			    if((double)str_replace(" ", "", $value) != 0)
    			    $value = str_replace(" ", "", $value);
				# Check if we got only one range border, and then transform it into an exact match
				if((!isset($filter["TO"])) || (!isset($filter["FR"])))
				{
					if($key == $cur_typ)
					{
						if(strpos($value, "%") === FALSE)
							$GLOBALS["where"] .= " AND vals.val$NOT_EQ='$value' ";
						else
							$GLOBALS["where"] .= " AND vals.val $NOT LIKE '$value' ";
					}
					else
					{
						if($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						if($value == "%")
							$GLOBALS["where"] .= " AND a$key.val $search_val ";
						elseif(strpos($value, "%") === FALSE)
						{
							if($NOT_flag) # No match or empty
								$GLOBALS["where"] .= " AND (a$key.val!='$value' OR a$key.val IS NULL) ";
							else
								$GLOBALS["where"] .= " AND a$key.val='$value' ";
						}
						elseif($NOT_flag) # No match or empty
							$GLOBALS["where"] .= " AND (a$key.val NOT LIKE '$value' OR a$key.val IS NULL) ";
						else
							$GLOBALS["where"] .= " AND a$key.val LIKE '$value' ";
					}
				}
				else # Apply range filter
				{
					if($is_date)
						$value = "'$value'"; # Add quotes to the date to use the index
					elseif((strpos($value, "[") === FALSE) && (strpos($value, "_") === FALSE))	# It's not a Variable
						$value = (float)str_replace(" ", "", $value); # Remove spaces from numbers

					if($f == "FR")  # Range FROM
					{
						if($key == $cur_typ)
							$GLOBALS["where"] .= " AND vals.val>=$value ";
						else
						{
							if($join)
								$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
							$GLOBALS["where"] .= " AND a$key.val>=$value ";
						}
					}
					elseif($f == "TO")     # Range TO
					{
						if($key == $cur_typ)
							$GLOBALS["where"] .= " AND vals.val<=$value ";
						else
							$GLOBALS["where"] .= " AND a$key.val<=$value ";
					}
				}
				break;
#			case "BOOLEAN":
#			case "SHORT":	# Char or Text values without tails
			default:	# Cast as SHORT in case no type detected
				if($key == $cur_typ)		# Filter was applied to the Object's Value
					$GLOBALS["where"] .= " AND vals.val $search_val ";
				else
				{
					if($join)
						$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
					if($NOT_flag)
						$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
					else
						$GLOBALS["where"] .= " AND a$key.val $search_val ";
				}
				break;
		}
	}
}
?>
