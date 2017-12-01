<?php

/*
 *
 * User -- 'root'
 * Password -- 1234
CREATE SCHEMA `shield_database` ;

CREATE TABLE `shield_database`.`shield` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `time_ddos` INT NOT NULL,
  `number` INT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `block` TINYINT(1) NULL,
  `timeblock` INT NULL,
  PRIMARY KEY (`id`));
*/

mysqli_report(MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli('localhost:3306', 'root', '1234', 'shield_database');
} catch (Exception $e) {
    echo 'ERROR:'.$e->getMessage();
}

$sql = "SELECT * FROM `shield` WHERE `ip`='{$_SERVER['REMOTE_ADDR']}' LIMIT 1";

if (!$result = $mysqli->query($sql)) {
    echo "Извините, возникла проблема в работе сайта.";
    exit;
}

$ip_data = $result->fetch_assoc();
$id = $ip_data['id'];
$time_ddos = $ip_data['time_ddos']; //время подключения
$nubmer_ddos = $ip_data['number']; //количество попыток подключения
$block_status = $ip_data['block']; //статус блокировки
$unblocking_time = $ip_data['timeblock']; //время когда можно разблокировать
$cur_time_sec = gettimeofday("sec"); //текущее время в сек

if(empty($id)){
//новый IP
    $mysqli->query("INSERT `shield` SET `ip`='{$_SERVER['REMOTE_ADDR']}', `block` = 0, `number` = '1', `time_ddos`='$cur_time_sec'");
} else {
    if($block_status > 0){
        if($unblocking_time < $cur_time_sec){
//штрафной период окончился, снимаем блокировку
            $mysqli->query("DELETE FROM `shield` WHERE `ip`='{$_SERVER['REMOTE_ADDR']}'");
            $mysqli->query("INSERT `shield` SET `ip`='{$_SERVER['REMOTE_ADDR']}', `number`='1', `block`='0', `time_ddos`='$cur_time_sec'");
        } else {
//если бан существует возвращаем ошибку
            $msg = ' until ' .date('d/m/Y H:i:s', $unblocking_time);
            //header($_SERVER['SERVER_PROTOCOL'] . $msg, true, 403);
            header("HTTP/1.1 423 Locked".$msg);
            exit;
        }
    } else {
//если ip не заблокировано проверяем время меджу первым и текущим визитами
        if(($cur_time_sec - $time_ddos) < 60){
            $nubmer_ddos = $nubmer_ddos + 1;
            if($nubmer_ddos > 5){
//если число подключений бльше 5, блокируем ip на 600 сек
                $unblock_time = $cur_time_sec + 600;
                $mysqli->query("UPDATE `shield` SET `number`='1', `block`='1', `timeblock`='$unblock_time' WHERE `id`='$id'");
                $msg = ' until ' .date('d/m/Y H:i:s', $unblock_time);
                header("HTTP/1.1 423 Locked".$msg);
                exit;
            } else {
//фиксируем время еще одного подключения
                $mysqli->query("INSERT `shield` SET `ip`='{$_SERVER['REMOTE_ADDR']}', `number`='$nubmer_ddos', `block`='0', `time_ddos`='$cur_time_sec';");
                $mysqli->query("UPDATE `shield` SET `number`='$nubmer_ddos' WHERE `ip`='{$_SERVER['REMOTE_ADDR']}'");
            }
        } else {
            $mysqli->query("INSERT `shield` SET `ip`='{$_SERVER['REMOTE_ADDR']}', `number`='$nubmer_ddos', `block`='0', `time_ddos`='$cur_time_sec'");
            $mysqli->query("DELETE FROM `shield` WHERE `id`='$id'");
        }
    }
}


echo "Hello USER";

$result->free();
$mysqli->close();
