<?php
Check_Grant($id);
$result = Exec_sql("SELECT obj.t, obj.up, obj.ord, max(peers.ord) new_ord
					FROM $z obj LEFT JOIN $z peers ON peers.up=obj.up AND peers.t=obj.t AND peers.ord<obj.ord
					WHERE obj.id=$id", "Get new Order and other Reqs");
#print_r($GLOBALS); die();
if($row = mysqli_fetch_array($result))
{
	$up = $row["up"];
	$id = $row["t"];
	if($row["new_ord"] > 0)
		Exec_sql("UPDATE $z SET ord=(CASE WHEN ord=".$row["ord"]." THEN ".$row["new_ord"]
									." WHEN ord=".$row["new_ord"]." THEN ".$row["ord"]
				." END) WHERE up=$up AND (ord=".$row["ord"]." OR ord=".$row["new_ord"].")", "Change Req order");
}
else
	exit("No arr recs");
$arg = "F_U=$up";
$a = "object";
?>
