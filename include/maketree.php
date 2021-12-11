<?php
# Hierarchic tree of blocks (in the $blocks array)
# $cur_block - the parent of the tree (in case of building a sub-tree)
function Make_tree($text, $cur_block)
{
	global $blocks;
# Remove BOM, if exists
	if(substr($text, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf))
        $text = substr($text, 3);
# This makes the delimiters look like: <!-- BEGIN: block_name -->
	$begin = "begin:";		# Block begin mark
	$end = "end:";			# Block end mark
	$file = "file:";			# Block end mark
	$begin_delimiter = "<!-- ";	# Block Mark begin delimiter
	$end_delimiter = " -->";		# Block Mark end delimiter

	$exp = explode($begin_delimiter, $text);
	$patt = "/($begin|$end|$file)[[:blank:]]*(&?[A-ZА-Я0-9_ ]+)[[:blank:]]*$end_delimiter(.*)/uims";
	$blocks[$cur_block]["CONTENT"] = "";

	foreach ($exp as $key => $a)
		if(preg_match($patt, $a, $res))
		{
			$res[1] = strtolower($res[1]); # $res[1] = BEGIN or END
			$res[2] = strtolower($res[2]); # $res[2] = Block name
							# $res[3] = Block content
			if(strcasecmp($res[1], $begin) == 0)
			{
				$blocks[$cur_block.".".$res[2]]["PARENT"] = $cur_block;
				$cur_block = $cur_block.".".$res[2];
				$blocks[$cur_block]["CONTENT"] = $res[3];
			}
			elseif(strcasecmp($res[1], $end) == 0)
			{
				if($blocks[$cur_block]["PARENT"].".".$res[2] != $cur_block) 
					die_info("Invalid blocks nesting (".$blocks[$cur_block]["PARENT"].".".$res[2]." - $cur_block)!");
# If there's a Sub-Block - mark it as an insertion point (with "_block_." prefix)
				$insertion_point = "{_block_.$cur_block}";
				$cur_block = $blocks[$cur_block]["PARENT"];
				$blocks[$cur_block]["CONTENT"] .= $insertion_point.$res[3];
			}
			elseif(strcasecmp($res[1], $file) == 0)
			{
			    if($res[2] == "a")
			        $text = Get_file($GLOBALS["GLOBAL_VARS"]["action"].".html", FALSE);
				elseif(isset($_REQUEST[$res[2]])) # Check if we have the requested template
					$text = Get_file($_REQUEST[$res[2]].".html", FALSE);
				else
					$text = Get_file($res[2].".html", FALSE);
				if(strlen($text) == 0)
					$text = Get_file("info.html");   # Default content is in info.html
				$file_block = "$cur_block." . (isset($_REQUEST[$res[2]])?$_REQUEST[$res[2]]:"");
				$insertion_point = "{_block_.$file_block}";
				$blocks[$file_block]["PARENT"] = $cur_block;
				Make_tree($text, $file_block);
				$blocks[$cur_block]["CONTENT"] .= $insertion_point.$res[3];
#				die($_REQUEST[$res[2]].".html".$text.$cur_block);
			}
		}
		elseif($a)	# Not a block delimiter - leave as is
		{
			if($key != 0)
				$blocks[$cur_block]["CONTENT"] .= $begin_delimiter.$a;	# Restore the starting $begin_delimiter
			else
				$blocks[$cur_block]["CONTENT"] = $a;
		}
}

# Fills the insertion points in Blocks and compiles all that stuff using recursive calls
function Parse_block($block)
{
	global $blocks;
	$i = count($blocks[$block], 1);
	Get_block_data($block);
# If there are insertion points, but we haven't got any data, - return
#	if(preg_match("/\{([A-Za-z0-9\_\*\(\)\'\,\+\-\/]+?)}/", $blocks[$block]["CONTENT"]) && ($i == count($blocks[$block], 1)))
	if(preg_match("/\{([A-ZА-Я0-9_ \-]+?)}/ui", $blocks[$block]["CONTENT"]) && ($i == count($blocks[$block], 1)))
		return "";
# Get insertion points. Attention: Sub-block pattern has a dot (.) in it!
#	preg_match_all("/\{([A-Za-z0-9\.&_\*\(\)\'\,\+\-\/]+?)}/", $blocks[$block]["CONTENT"], $temp);
	preg_match_all("/\{([A-ZА-Я0-9\.&_ \-]+?)}/ui", $blocks[$block]["CONTENT"], $temp);
	$points = $sub = array();
	foreach(array_unique($temp[1]) as $key => $value)	# remove duplicated insertion points, if any
		if(substr($value, 0, 7) != "_block_")	# Toss Sub-Blocks to the end to not str_ireplace them in the parent body
			$points[] = $value;
		else
			$sub[] = $value;
	$points = array_merge($points, $sub);
	$content = "";
	unset($end);
	while(!isset($end))
	{
		$end = 1;
		$cur_content = $blocks[$block]["CONTENT"];
# Get current Items from dataset for this block
		foreach($blocks[$block] as $key => $value)
			if(($key != "CUR_VARS") && is_array($value))
				$blocks[$block]["CUR_VARS"][$key] = array_shift($blocks[$block][$key]);
# Fill insertion points, calling Sub-Blocks parser, if any
		foreach($points as $key => $point)
		{
			unset($item);
			$point = strtolower($point);
			$sub = explode(".", $point);
			if($sub[0] == "_block_")	# If it's a Sub-Block (marked by prefix "_block_.")
			{
				trace("Got sub-block: $point");
				unset($sub[0]);
				$sub_block = implode(".", $sub);
				trace("Parse sub-block: $sub_block");
				$item = Parse_block($sub_block);	# parse Sub-Block
				$cur_content = str_ireplace("{".$point."}", $item, $cur_content);	# Insert Sub-Block
			}
			else
			{
				if(isset($blocks[$block]["CUR_VARS"][$point]))
					$item = $blocks[$block]["CUR_VARS"][$point];
				elseif($sub[0] == "_parent_")	# It's a Parent's Variable (marked by prefix "_parent_.")
				{
					$parent = $blocks[$block]["PARENT"];
					while(!isset($item))	# Seek the parent's var up to the main block
						if(isset($blocks[$parent]["CUR_VARS"][$sub[1]]))
							$item = $blocks[$parent]["CUR_VARS"][$sub[1]];	# Got it
						elseif(isset($blocks[$parent]["PARENT"]))
							$parent = $blocks[$parent]["PARENT"];	# Go upper
						else
							break;
				}
				elseif($sub[0] == "_global_")	# It's a Global Variable (marked by prefix "_global_.")
					$item = isset($GLOBALS["GLOBAL_VARS"][$sub[1]]) ? $GLOBALS["GLOBAL_VARS"][$sub[1]] : "";
				elseif($sub[0] == "_request_")	# It's a _REQUEST var (marked by prefix "_request_.")
				{
					foreach($_GET as $k => $v)
						if(strtolower($k) == $sub[1])
						{
							$item = $v;
							break;
						}
					foreach($_POST as $k => $v)
						if(strtolower($k) == $sub[1])
						{
							$item = $v;
							break;
						}
				}

				if(isset($item))	# Insert item, masking "{"
					$cur_content = str_ireplace("{".$point."}", str_replace("{", "&#123;", $item), $cur_content);
				else
					break;	# Break the parsing upon first missing value (to not process the sub-blocks)
				if(isApi() && ($sub[0] != "_global_"))
					$GLOBALS["GLOBAL_VARS"]["api"][$block][$point][] = str_replace("{", "&#123;", $item);
				if(isset($blocks[$block][$point]))
				    if(count($blocks[$block][$point]))	# Check, is there any more data
                        unset($end);
			}
		}
# Accept only fully filled portions of Block content
#		if(!preg_match("/\{([A-Za-z0-9\.&_\*\(\)\'\,\+\-\/]+?)}/", $cur_content) || isset($_REQUEST["debug"]))
		if(!preg_match("/\{([A-ZА-Я0-9\.&_ \-]+?)}/ui", $cur_content) || isset($_REQUEST["debug"]))
			$content .= $cur_content;
	}
	if(($block == "&main") || ($block == ""))  # Replace "{" only returning the complete result (not a sub-block)
		return str_replace("&#123;", "{", $content);
	else
		return $content;
}
?>
