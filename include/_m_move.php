<?php
if($id==0)
	die(t9n("[RU]Неверный id: $id[EN]Wrong id: $id"));
Check_Grant($id);
# Get the Object
$result = Exec_sql("SELECT a.up, a.t, up.t ut, a.ord, target.t tt, COALESCE(MAX(reqs.ord)+1,1) new_ord
						FROM $z a, $z up, $z target, $z reqs
						WHERE up.id=a.up AND a.id=$id AND target.id=$up AND reqs.up=$up"
					, "Get Obj to move");
if($row = mysqli_fetch_array($result))
{
    $arg = "moved&";
	if($up != 1)
	{
		Check_Grant($up, $row["t"]);
		$arg .= "&F_U=$up";  # Retain this for Array elements only
		$ord = $row["new_ord"];
	}
	elseif(Grant_1level($row["t"]) != "WRITE")
		die(t9n("[RU]У вас нет прав на создание объектов этого типа.[EN]You don't have permission to create this type of object."));
	else
		$ord = 1;
	if($row["up"]==0)
		exit("Cannot update meta-data");
	if($row["ut"]!=$row["tt"])
		exit("Types mismatch ".$row["t"]."!=".$row["tt"]);
	if($row["up"]!=$up)
	{
#					echo("The same parent $up");
		Exec_sql("UPDATE $z SET ord=$ord, up=$up WHERE id=$id", "Move Obj");
		Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=".$row["up"]." AND t=".$row["t"]." AND ord>".$row["ord"], "Move peers up");
	}
}
else
	exit("No such record");
$a = "object";
?>
