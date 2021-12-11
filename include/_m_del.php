<?php
if($id==0)
	my_die(t9n("[RU]Неверный id: $id[EN]Wrong id: $id"));
Check_Grant($id);
include_once "batchdelete.php";
$refs = exec_sql("SELECT count(r.id), obj.up, obj.ord, obj.t, par.up pup FROM $z obj "
                    ."LEFT JOIN $z r ON r.t=obj.id JOIN $z par ON par.id=obj.up WHERE obj.id=$id"
		, "Get Refs to the Object");
if($row = mysqli_fetch_array($refs))
{
	if($row["pup"] == 0)
		my_die(t9n("[RU]Нельзя удалить метаданные (реквизит $id типа [EN]You can't delete metadata (the $id type".$row["up"].")!"));
	if($row[0] > 0)
		my_die(t9n("[RU]Нельзя удалить объект, на который существует ссылки (всего: [EN]You can't delete an object that has links to it (total:").$row[0].")!");
	if($row["up"] > 1) # We'll drop the Array element, so we need to adjust the order of its peers
	{
		$arg = "F_U=".$row["up"];
		Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=".$row["up"]." AND t=".$row["t"]." AND ord>".$row["ord"], "Move peers");
	}
	//Delete($id);
	BatchDelete($id);
	$obj=$id;
	$id = $row["t"];
    $a = "object";
}
else
	die(t9n("[RU]Объект не найден[EN]Object not found"));
BatchDelete(""); // Flush batch
$a = "object";
?>
