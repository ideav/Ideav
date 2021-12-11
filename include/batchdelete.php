<?php
function BatchDelete($id, $root=TRUE)  # Delete Obj and its children recursively
{
	global $z;
	if($id === "")
	{
    	if(isset($GLOBALS["BatchUps"]))
    	{
    		Exec_sql("DELETE FROM $z WHERE up IN(".$GLOBALS["BatchUps"].")", "Flush ups");
            unset($GLOBALS["BatchUps"]);
    	}
    	if(isset($GLOBALS["BatchIDs"]))
    	{
    		Exec_sql("DELETE FROM $z WHERE id IN(".$GLOBALS["BatchIDs"].")", "Flush objs");
            unset($GLOBALS["BatchIDs"]);
    	}
        return;
	}
    trace(" get children for $id");
	$children = exec_sql("SELECT del.id, MIN(child.up) child FROM $z del LEFT JOIN $z child ON child.up=del.id WHERE del.up=$id GROUP BY del.id", "Get children for batch");
	if($child=mysqli_fetch_array($children))
	{
        trace(" $id has ".mysqli_num_rows($children)." children");
		do
		{
		    if($child["child"] > 0)
		    {
        		BatchDelete($child["id"], false);
            	$GLOBALS["BatchUps"] = isset($GLOBALS["BatchUps"]) ? $GLOBALS["BatchUps"].",".$child["id"] : $child["id"];
                // Flush the SQL in case the batch is big enough
            	if(strlen($GLOBALS["BatchUps"]) > 10000)
                    BatchDelete("");
		    }
		} while($child=mysqli_fetch_array($children));
    	$GLOBALS["BatchUps"] = isset($GLOBALS["BatchUps"]) ? $GLOBALS["BatchUps"].",$id" : $id;
    	if(strlen($GLOBALS["BatchUps"]) > 10000)
            BatchDelete("");
	}
    // Add the id to the batch for all its children are already on the list
	if($root) # Delete the object in case it's the initially requested one
	{
    	$GLOBALS["BatchIDs"] = isset($GLOBALS["BatchIDs"]) ? $GLOBALS["BatchIDs"].",$id" : $id;
    	if(strlen($GLOBALS["BatchIDs"]) > 10000)
            BatchDelete("");
    }
}
?>
