<?php
	session_start();
	date_default_timezone_set('Asia/Kolkata');
	$conn = mysqli_connect('localhost','root','','sabbole') or die('Databse is not connected !');
	error_reporting(0);
	if(!isset($_SESSION['isUserLoggedIn']) || $_SESSION["isUserLoggedIn"] !== true){
		$_SESSION['isUserLoggedIn'] = true;
		$_SESSION['id'] = 1;
	}

	$cookie_password = '';
    $checked_remember_password = '';
    if(isset($_COOKIE['irctc_password'])){
        $cookie_password = $_COOKIE['irctc_password'];
        $checked_remember_password = "checked='checked'";	
    }
	if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['username']) && isset($_POST['password']) && isset($_SESSION['isUserLoggedIn'])){
		$username = mysqli_real_escape_string($conn, $_POST['username']);
		$username = str_replace(' ','',$username);
		$password = mysqli_real_escape_string($conn, $_POST['password']);
		$query    = mysqli_query($conn,"SELECT * FROM irctc WHERE username='$username'");
		if(!mysqli_num_rows($query)){
			if(!empty($username) && !empty($password)){
				$time = time();
				$insert = mysqli_query($conn,"INSERT INTO irctc(user_id,username,password,created) VALUE('".$_SESSION['id']."','$username','$password','$time')");
				if($insert){
					if(isset($_POST['remember_password'])){
						setcookie('irctc_password', $password, time() + (60*60*24));
					}else{
						setcookie('irctc_password', $password, time() - 60);
					}
					data_backup('localhost','root','','sabbole','*');
				}else{
					echo 'ERROR: Data not inserted into database !';
				}
			}else{
				echo 'ERROR: Username or Password is Empty !';
			}
		}else{
			echo 'ERROR: Username already exists !';
		}
		exit();
	}
	function data_backup($localhost,$user,$password,$dbname,$tables='*'){
		$db = mysqli_connect($localhost,$user,$password, $dbname);
		if(mysqli_connect_errno()){
			echo "Failed to connect to MySQL: " . mysqli_connect_error();
			exit(); 
		}
		mysqli_query($db, "SET NAMES 'utf8'");
		if($tables == '*'){
			$tables = array();
			$result = mysqli_query($db, 'SHOW TABLES');
			while($row = mysqli_fetch_row($result)){
				$tables[] = $row[0];
			}
		}else{
			$tables = is_array($tables) ? $tables : explode(',',$tables);
		}
		$return = '';
		foreach($tables AS $table){
			$result = mysqli_query($db, 'SELECT * FROM '.$table);
			$num_fields = mysqli_num_fields($result);
			$num_rows = mysqli_num_rows($result);
			$return.= 'DROP TABLE IF EXISTS '.$table.';';
			$row2 = mysqli_fetch_row(mysqli_query($db, 'SHOW CREATE TABLE '.$table));
			$return.= "\n\n".$row2[1].";\n\n";
			$counter = 1;
			for($i = 0; $i < $num_fields; $i++){
				while($row = mysqli_fetch_row($result)){   
					if($counter == 1){
						$field_query = mysqli_query($db,"SHOW COLUMNS FROM $table");
						// $field_db = mysqli_num_fields($field_query);
						$return .= 'INSERT INTO `'.$table.'` (';
						$field_name = '';
						while($field_row = mysqli_fetch_assoc($field_query)){
							$field_name .= '`'.$field_row['Field'].'`, ';
						}
						$return .= rtrim($field_name,", ").") VALUES\n(";
					}else{
						$return .= "(";
					}
					for($j=0; $j<$num_fields; $j++){
						$row[$j] = addslashes($row[$j]);
						$row[$j] = str_replace("\n","\\n",$row[$j]);
						if(isset($row[$j])){
							$return .= "'".$row[$j]."'" ;
						}else{
							$return .= "''";
						}
						if($j<($num_fields-1)){
							$return .= ', ';
						}
					}
					if($num_rows == $counter){
						$return .= ");\n";
					}else{
						$return .= "),\n";
					}
					++$counter;
				}
			}
			$return.="\n\n\n";
		}
		//save file
		$directory = 'Backup/Database';
		if(!file_exists($directory) || !is_dir($directory)){
			$dir = @mkdir($directory, 0777, true);
			if(!$dir){
				echo 'ERROR: Directory is not created...';
			}
		}
		$file_name = "$directory/$dbname.sql";
		$handle = fopen($file_name,"w+");
		fwrite($handle,$return);
		if(fclose($handle)){
			// echo "SUCCESS: Data Backup Successfully Done !";
            session_start();
            session_destroy();
		}
		exit(); 
	}

	if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['quantity']) && isset($_SESSION['isUserLoggedIn'])){
		$quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
		$query = mysqli_query($conn,"SELECT * FROM irctc WHERE status='' AND replace_id='' LIMIT $quantity");
		if(mysqli_num_rows($query) >= $quantity){
			foreach($query AS $row){
				$time = time();
				$update = mysqli_query($conn,"UPDATE irctc SET cust_id='".$_SESSION['id']."', status='waiting', sold_out='$time' WHERE id='".$row['id']."'");
			}
			if($update){
				purchage($conn,$quantity);
			}else{
				echo 'Purchaging in Process Failed ! Please Try Again.';
			}
		}else{
			echo 'ERROR: User ID Not Available !';
		}
		exit();
	}
	function purchage($conn,$quantity){
		$time = time()-(60*200);
		$query = mysqli_query($conn,"SELECT * FROM irctc WHERE cust_id='".$_SESSION['id']."' AND status='waiting' AND sold_out>='$time' LIMIT $quantity");
		if(mysqli_num_rows($query) >= $quantity){
			foreach($query AS $row){
				$time = time();
				$update = mysqli_query($conn,"UPDATE irctc SET status='confirm', sold_out='$time' WHERE id='".$row['id']."'");
			}
			if(!$update){
				echo 'IRCTC ID Purchaging Failed ! Please Try Again.';
			}
		}else{
			echo 'ERROR: User ID Not Available !';
		}
	}
	
	if(isset($_POST['replacement']) && isset($_SESSION['isUserLoggedIn'])){
		$id = mysqli_real_escape_string($conn, $_POST['id']);
		$query = mysqli_query($conn,"SELECT * FROM irctc WHERE id='$id' AND cust_id='".$_SESSION['id']."' AND replace_id=''");
		$db = mysqli_fetch_assoc($query);
		if(mysqli_num_rows($query)){
			$time = time()-(60*60*24*2);
			$replace_query = mysqli_query($conn,"SELECT * FROM irctc WHERE id='".replacement($conn,$db['id'])."' AND sold_out>='$time'");
			if(mysqli_num_rows($replace_query)){
				$update = mysqli_query($conn,"UPDATE irctc SET replace_id='replacement' WHERE id='".$db['id']."'");
				if($update){
					echo '';
				}else{
					echo 'ERROR: IRCTC ID Not Replacement ! Please Try Again.';
				}
			}else{
				echo 'ERROR: Replacement Date And Time is Over.';
			}
		}else{
			echo 'ERROR: This User ID And Password Replacement Not Available !';
		}
		exit();
	}
	function replacement($conn,$id){
		$query = mysqli_query($conn,"SELECT * FROM irctc WHERE cust_id='".$_SESSION['id']."' AND id='$id'");
		$db = mysqli_fetch_assoc($query);
		if($db['status']=='confirm'){
			return $db['id'];
		}else{
			return replacement($conn,$db['status']);
		}
	}

	if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['irctc_replacement_id']) && isset($_POST['irctc_id_replacement']) && isset($_SESSION['isUserLoggedIn'])){
		$id = mysqli_real_escape_string($conn, $_POST['irctc_replacement_id']);
		$replacement = mysqli_real_escape_string($conn, $_POST['irctc_id_replacement']);
		$time = time()-(60*60*24*1);
		$query = mysqli_query($conn,"SELECT * FROM irctc WHERE id='$id' AND user_id='".$_SESSION['id']."' AND replace_id='replacement' AND sold_out>='$time'");
		$row = mysqli_fetch_assoc($query);
		if(mysqli_num_rows($query)){
			$select_query = mysqli_query($conn,"SELECT * FROM irctc WHERE status='' AND replace_id=''");
			$db = mysqli_fetch_assoc($select_query);
			if(mysqli_num_rows($select_query)){
				$time = time();
				if($replacement=='accept'){
					$db_update = mysqli_query($conn,"UPDATE irctc SET replace_id='$replacement' WHERE id='".$row['id']."'");
					$replace_update = mysqli_query($conn,"UPDATE irctc SET cust_id='".$row['cust_id']."', status='".$row['id']."', sold_out='$time' WHERE id='".$db['id']."'");
					if($replace_update AND $db_update){
						echo '';
					}else{
						echo 'ERROR: IRCTC ID Not Replace ! Please Try Again.';
					}
				}else{
					$db_update = mysqli_query($conn,"UPDATE irctc SET replace_id='$replacement' WHERE id='".$row['id']."'");
					if($db_update){
						echo '';
					}else{
						echo 'ERROR: IRCTC ID Not Replace ! Please Try Again.';
					}
				}
			}else{
				echo 'ERROR: Extra User ID And Password Not Available !';
			}
		}else{
			echo 'ERROR: User ID And Password Replacement Not Available !';
		}
		exit();
	}
	if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['irctc_id']) && isset($_POST['irctc_user_id']) && isset($_POST['irctc_password']) && isset($_POST['irctc_email']) && isset($_SESSION['isUserLoggedIn'])){
		$id = mysqli_real_escape_string($conn, $_POST['irctc_id']);
		$username = mysqli_real_escape_string($conn, $_POST['irctc_user_id']);
		$password = mysqli_real_escape_string($conn, $_POST['irctc_password']);
		$email = mysqli_real_escape_string($conn, $_POST['irctc_email']);
		$update = mysqli_query($conn,"UPDATE irctc SET cust_id='', username='$username', password='$password', email='$email', status='', replace_id='', sold_out='' WHERE user_id='".$_SESSION['id']."' AND id='$id'");
		if(!$update){
			echo 'ERROR: IRCTC ID Not Update. Please Try Again !';
		}
		exit();
	}
	if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['irctc_status_id']) && isset($_POST['irctc_id_status']) && isset($_SESSION['isUserLoggedIn'])){
		$id     = mysqli_real_escape_string($conn, $_POST['irctc_status_id']);
		$status = mysqli_real_escape_string($conn, $_POST['irctc_id_status']);
		$update = mysqli_query($conn,"UPDATE irctc SET replace_id='$status' WHERE user_id='".$_SESSION['id']."' AND id='$id'");
		if(!$update){
			echo 'ERROR: IRCTC ID Not Update. Please Try Again !';
		}
		exit();
	}
	if(isset($_POST['irctc_id_delete']) && isset($_SESSION['isUserLoggedIn'])){
		$id = mysqli_real_escape_string($conn, $_POST['id']);
		if($_SESSION['id']==1){
			$delete = mysqli_query($conn,"DELETE FROM irctc WHERE user_id='".$_SESSION['id']."' AND id='$id'");
			if(!$delete){
				echo 'ERROR: IRCTC ID Not Deleted. Please Try Again !';
			}
		}else{
			$update = mysqli_query($conn,"UPDATE irctc SET replace_id='delete' WHERE cust_id='".$_SESSION['id']."' AND id='$id'");
			if(!$update){
				echo 'ERROR: IRCTC ID Not Deleted. Please Try Again !';
			}
		}
		exit();
	}
	if(isset($_POST['fetch_all_id'])){
		$query = mysqli_query($conn,"SELECT * FROM irctc ORDER BY id DESC");
		if(mysqli_num_rows($query)){
			$i = 1;
			foreach($query AS $row){
				if($row['user_id']==$_SESSION['id'] && $row['cust_id']==0 && empty($row['status']) && empty($row['replace_id'])){
					echo '<tr>
							<th style="padding:0 5px;">'.$i.'.</th>
							<td style="padding:0 5px;">'.$row['username'].'</td>
							<td style="padding:0 5px;">'.$row['password'].'</td>
							<td style="padding:0 5px;">'.$row['email'].'</td>
							<th style="padding:0 5px;"><i data-id="'.$row['id'].'" class="material-icons status_update" title="Login Result">manage_history</i> <i class="material-icons id_edit" data-id="'.$row['id'].'" data-username="'.$row['username'].'" data-password="'.$row['password'].'" title="Update">edit</i> <i class="material-icons id_delete" data-id="'.$row['id'].'" title="Delete">delete</i></th>
						</tr>';
					$i++;
				}else{
					if($row['user_id']!=$_SESSION['id'] && $row['cust_id']==$_SESSION['id'] && !empty($row['status']) && $row['status'] != 'waiting' && empty($row['replace_id'])){
						$time = time()-(60*60*24*1);
						$status = '';
						if($row['status']!='confirm'){
							$status = '<i class="material-icons" title="">published_with_changes</i>';
						}
						$replcement = '';
						if($row['sold_out']>$time){
							$replcement = '<i data-id="'.$row['id'].'" class="material-icons replacement" title="Replace">autorenew</i>';
						}
						echo '<tr>
								<th style="padding:0 5px;">'.$i.'.</th>
								<td style="padding:0 5px;">'.$row['username'].'</td>
								<td style="padding:0 5px;">'.$row['password'].'</td>
								<td style="padding:0 5px;">'.$row['email'].'</td>
								<th style="padding:0 5px;">'.$status.' '.$replcement.' <i class="material-icons id_delete" data-id="'.$row['id'].'" title="Disable">delete</i></th>
							</tr>';
						$i++;
					}
				}
			}
		}else{
			echo '<tr>
					<td colspan="5" style="padding:0 5px;">IRCTC ID Not Available.</td>
				</tr>';
		}
		exit();
	}
	if(isset($_POST['sale_id'])){
		$query = mysqli_query($conn,"SELECT * FROM irctc ORDER BY sold_out DESC");
		if(mysqli_num_rows($query)){
			$i = 1;
			foreach($query AS $row){
				if($row['user_id']==$_SESSION['id'] && (!empty($row['status']) || $row['status']=='waiting') && (empty($row['replace_id']) || $row['replace_id']=='accept' || $row['replace_id']=='replacement')){
					$status='';
					if($row['status']=='confirm'){
						$status = '<span class="material-icons confirm" title="Sale">verified</span>';
					}else if($row['status']=='waiting'){
						$status = '<span class="material-icons" title="Processing">schedule</span>';
					}else{
						$status = '<span class="material-icons" title="Replaced">published_with_changes</span>';
					}
					$edit='';
					$delete='';
					$replcement = '';
					if($row['replace_id']=='replacement'){
						$replcement = '<i data-id="'.$row['id'].'" class="material-icons replace" title="Replacement">sync_problem</i>';
						$edit = '<i class="material-icons id_edit" data-id="'.$row['id'].'" data-username="'.$row['username'].'" data-password="'.$row['password'].'" title="Edit">edit</i>';
						$delete = '<i class="material-icons id_delete" data-id="'.$row['id'].'" title="Delete">delete</i>';
					}else if($row['replace_id']=='accept'){
						$replcement = '<span class="material-icons" title="Replaced">published_with_changes</span>';
					}
					echo '<tr>
							<th style="padding:0 5px;">'.$i.'.</th>
							<td style="padding:0 5px;">'.$row['username'].'</td>
							<th style="padding:0 5px;">'.$status.' '.$replcement.' '.$edit.' '.$delete.'</th>
						</tr>';
					$i++;
				}else{
					if($row['user_id']!=$_SESSION['id'] && $row['cust_id']==$_SESSION['id'] && !empty($row['status']) && ($row['replace_id']=='accept' || $row['replace_id']=='replacement')){
						$time = time()-(60*60*24*1);
						$replcement = '';
						if($row['replace_id']=='accept'){
							$replcement = '<span class="material-icons" title="Replaced">verified</span>';
						}else if($row['replace_id']=='replacement'){
							$replcement = '<span class="material-icons" title="Pending">schedule</span>';
						}
						echo '<tr>
								<th style="padding:0 5px;">'.$i.'.</th>
								<td style="padding:0 5px;">'.$row['username'].'</td>
								<th style="padding:0 5px;">'.$replcement.'</th>
							</tr>';
						$i++;
					}
				}
			}
		}else{
			echo '<tr>
					<td colspan="3" style="padding:10px 5px;">IRCTC ID Not Available.</td>
				</tr>';
		}
		exit();
	}
	if(isset($_POST['unused_id'])){
		$query = mysqli_query($conn,"SELECT * FROM irctc ORDER BY replace_id");
		if(mysqli_num_rows($query)){
			$i = 1;
			foreach($query AS $row){
				if($row['user_id']==$_SESSION['id'] && !empty($row['replace_id']) && $row['replace_id'] != 'replacement' && $row['replace_id'] != 'accept'){
					$status='';
					$edit='';
					$delete='';
					if(empty($row['status'])){
						$edit = '<i class="material-icons id_edit" data-id="'.$row['id'].'" data-username="'.$row['username'].'" data-password="'.$row['password'].'" title="Edit">edit</i>';
						$delete = '<i class="material-icons id_delete" data-id="'.$row['id'].'" title="Delete">delete</i>';
					}else if($row['status']=='confirm'){
						$status = '<span class="material-icons confirm" title="Sale">verified</span>';
					}else{
						$status = '<span class="material-icons" title="Replaced">published_with_changes</span>';
					}
					$replcement = '';
					if($row['replace_id']=='reject'){
						$replcement = '<span class="material-icons" title="Reject">cancel</span>';
					}else if($row['replace_id']=='disable'){
						$replcement = '<span class="material-icons" title="Disable">block</span>';
					}else if($row['replace_id']=='invalid'){
						$replcement = '<span class="material-icons" title="Invalid">dangerous</span>';
					}else if($row['replace_id']=='recheck'){
						$replcement = '<span class="material-icons" title="Check Again">warning</span>';
					}else if($row['replace_id']=='incorrect'){
						$replcement = '<span class="material-icons" title="Login Error">error</span>';
					}else if($row['replace_id']=='delete'){
						$replcement = '<span class="material-icons" title="Deleted">delete_forever</span>';
					}
					echo '<tr>
							<th style="padding:0 5px;">'.$i.'.</th>
							<td style="padding:0 5px;">'.$row['username'].'</td>
							<th style="padding:0 5px;">'.$status.' '.$replcement.' '.$edit.' '.$delete.'</th>
						</tr>';
					$i++;
				}else{
					if($row['user_id']!=$_SESSION['id'] && $row['cust_id']==$_SESSION['id'] && !empty($row['status']) && ($row['replace_id']=='delete' || $row['replace_id']=='reject')){
						$time = time()-(60*60*24*1);
						$replcement = '';
						if($row['replace_id']=='delete'){
							$replcement = '<span class="material-icons" title="Deleted">delete_forever</span>';
						}else if($row['replace_id']=='reject'){
							$replcement = '<span class="material-icons" title="Reject">cancel</span>';
						}
						echo '<tr>
								<th style="padding:0 5px;">'.$i.'.</th>
								<td style="padding:0 5px;">'.$row['username'].'</td>
								<td style="padding:0 5px;">'.$row['password'].'</td>
								<th style="padding:0 5px;">'.$replcement.'</th>
							</tr>';
						$i++;
					}
				}
			}
		}else{
			echo '<tr>
					<td colspan="3" style="padding:10px 5px;">IRCTC ID Not Available.</td>
				</tr>';
		}
		exit();
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title>IRCTC ID - SABBOLE</title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel='stylesheet' href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css'>
	<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<script language="JavaScript" type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
	<script language="JavaScript" type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script language="JavaScript" type="text/javascript" src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script type="text/javascript" src="https://www.jqueryscript.net/demo/Easy-Elements-Fullscreen-Plugin-with-jQuery/release/jquery.fullscreen-0.4.1.min.js"></script>
	<script type="text/javascript" src="https://cdn.jsdelivr.net/jsbarcode/3.6.0/JsBarcode.all.min.js"></script>
	<script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
	<style>
		*{box-sizing:border-box;margin:0px;padding:0px;word-spacing:0px;letter-spacing:0px;word-wrap:break-word;}
		:root{
			--main-bg: #cccccc;
			--matching-bg: #fff;
			--main-color: #000000;
			--matching-color: #555555;
			--main-border:#aaaaaa;
			--matching-border:#fff;
			--selection-bg:#000;
			--selection-color:#fff;
			--pink-bg:#ffe6f3;
			--pink-color:deeppink;
			--blue-bg:#ccf2ff;
			--blue-color:deepskyblue;
			--loader-bg:rgb(162, 236, 255);
			--loader-border:rgb(0, 144, 180);
			--gear-color:#081b29;
			--body-bg:linear-gradient(90deg, transparent 85px, tomato 86px, transparent 0px, transparent 89px, tomato 90px, transparent 0px),
						radial-gradient(rgba(255, 255, 0, 0.25), rgba(255, 99, 71, 0.25), rgba(144, 238, 144, 0.25) 75%),
						linear-gradient(180deg, #f2f2f2 85px, tomato 86px, transparent 0px, #f2f2f2 89px, tomato 90px, #f2f2f2 0px, #f2f2f2 118px, transparent 118px, transparent 0px),
						linear-gradient(0deg, #f2f2f2 30px, transparent 30px, transparent 0px),
						repeating-linear-gradient(0deg, transparent 0px, transparent 28px, teal 29px), #f2f2f2;
			--body-scrollbar:linear-gradient(180deg, #f2f2f2 85px, tomato 86px, transparent 0px, #f2f2f2 89px, tomato 90px, #f2f2f2 0px, #f2f2f2 118px, transparent 118px, transparent 0px),
								linear-gradient(0deg, #f2f2f2 30px, transparent 30px, transparent 0px),
								repeating-linear-gradient(0deg, transparent 0px, transparent 28px, teal 29px), #f2f2f2;
			--body-scrollbar-thumb:rgba(0,0,0,0.5);
			--header-bg:linear-gradient(to bottom, rgb(0, 191, 255) 0%, rgb(0, 200, 255) 10%, rgba(14,80,97,1) 10%, rgba(0,204,255,1) 60%, rgba(204,245,255,1) 100%);
			--footer-bg:linear-gradient(to top, rgb(0, 191, 255) 0%, rgb(0, 200, 255) 10%, rgba(14,80,97,1) 10%, rgba(0,204,255,1) 60%, rgba(204,245,255,1) 100%);
			--menubar-bg:linear-gradient(to bottom, rgba(204,245,255,1) 0%, rgba(0,204,255,1) 40%, rgba(14,80,97,1) 100%);
			--menu-bg:linear-gradient(to top, rgba(204,245,255,1) 0%, rgba(0,204,255,1) 40%, rgba(14,80,97,1) 100%);
			--submenu-bg:#9fe9ff;
			--list-color:#000000;
			--info-color:#444444;
			--list-bg:#bfefff;
			--list-hover-color:blue;
			--info-hover-color:#333333;

			--popup-bg:linear-gradient(to right, #999, #bbb, #ddd 7%, #fff 10%, #fff, #fff 90%, #ddd 93%, #bbb, #999);
			--popup-header-bg:linear-gradient(to right, rgb(0, 63, 114), rgb(0, 97, 175), rgb(0, 121, 219) 7%, rgb(0, 140, 255) 10%, rgb(0, 140, 255), rgb(0, 140, 255) 90%, rgb(0, 121, 219) 93%, rgb(0, 97, 175), rgb(0, 63, 114));
			--popup-close:linear-gradient(45deg, transparent 45%, #fff 45%, #fff 55%, transparent 0%), linear-gradient(135deg, transparent 45%, #fff 45%, #fff 55%, transparent 0%);
			--popup-close-hover:linear-gradient(45deg, transparent 40%, red 40%, red 60%, transparent 0%), linear-gradient(135deg, transparent 40%, red 40%, red 60%, transparent 0%);
			--popup-close-border:#fff;
			--popup-close-border-hover:red;
			--close-btn:tomato;
			--popup-submit-bg:rgb(0, 179, 0);
			--popup-submit-hover-bg:green;
			--popup-submit-active-bg:rgb(0, 97, 0);
			--popup-shadow:1px 1px green, 2px 2px green, 3px 3px green, 4px 4px green, 5px 5px green;
			--popup-shadow-hover:1px 1px rgb(0, 97, 0), 2px 2px rgb(0, 97, 0), 3px 3px rgb(0, 97, 0), 4px 4px rgb(0, 97, 0), 5px 5px rgb(0, 97, 0);
			--login-popup-bg:linear-gradient(to bottom, deepskyblue, rgb(156, 230, 255));
			--login-popup-box-shadow:5px 6px 0px -2px #007697, -5px 5px 0px -2px #007697, 0px -1px 0px 3px #86e3ff, 0px 10px 0px 0px #007697, 0px -10px 0px 0px rgb(55, 205, 255);
			--popup-msg-shadow:0px 0px 0px 4px #007697, inset 0px 0px 15px 0px #000, 2px 7px 1px 1px #86e3ff, -2px 7px 1px 1px #86e3ff;
			--popup-btn-border:#005168;
			--popup-btn-shadow:0px 0px 0px 4px #007697, 2px 6px 1px 1px #b6eeff, -2px 6px 1px 1px #b6eeff;
			--popup-cnf-hover:0px 0px 0px 4px #007697, 2px 6px 1px 1px #b6eeff, -2px 6px 1px 1px #b6eeff, inset 2px 2px 10px 3px #4e6217;
			--popup-cancel-hover:0px 0px 0px 4px #007697, 2px 6px 1px 1px #b6eeff, -2px 6px 1px 1px #b6eeff, inset 2px 2px 10px 3px #691e1e;

			--accordion-header-bg:#b7ddff;
			--accordion-header-hover-bg:#54afff;
			--accordion-header-active-bg:#0088ff;    
			--accordion-header-border:#96bddf;
			--accordion-header-border-hover:#4d94d3;
			--accordion-header-border-active:#2c74b3;
			--accordion-contents-bg:#d6ebff;
			--accordion-contents-border:#00427c;
			--input-focus-bg:#3399FF;
			--input-color:tomato;
			--input-focus-color:#fff;
			--success-bg:green;
			--success-msg:#fff;
			--success-color:green;
			--error-bg:red;
			--error-msg:#fff;
			--error-color:red;
			
			--table-th-bg:rgb(0, 200, 255);
			--tr-first-bg:#ffccdd;
			--tr-second-bg:#ddffcc;
			--tr-third-bg:#ccddff;
			--tr-fourth-bg:#ccfcff;
			--tr-hover-bg:rgb(117, 225, 255);
			
			--live-table-border-color:rgb(0, 147, 206);

			--count-down-bg:linear-gradient(to bottom, rgba(255,132,0,1) 0%, rgba(255,102,0,1) 10%, rgba(125,58,0,1) 10%, rgba(255,202,161,1) 65%, rgba(97,45,0,1) 100%);
		}
		.Dark{
			--main-bg: #333333;
			--matching-bg: #555555;
			--main-color: #fff;
			--matching-color: #cccccc;
			--main-border:#999999;
			--matching-border:#444444;
			--selection-bg:#ccc;
			--selection-color:#000;
			--pink-bg:#ccc;
			--pink-color:#000;
			--blue-bg:#ccc;
			--blue-color:#000;
			--loader-bg:#666;
			--loader-border:#333;
			--gear-color:#222;
			--body-bg:linear-gradient(90deg, transparent 85px, #fff 86px, transparent 0px, transparent 89px, #fff 90px, transparent 0px),
					linear-gradient(180deg, #000 85px, #fff 86px, transparent 0px, #000 89px, #fff 90px, #000 0px, #000 118px, #000 118px, transparent 0px),
					linear-gradient(0deg, #000 30px, transparent 30px, transparent 0px),
					repeating-linear-gradient(0deg, transparent 0px, transparent 28px, #fff 29px),
					radial-gradient(#333, #222, #111, #000 75%);
			--body-scrollbar:linear-gradient(180deg, #333 85px, #fff 86px, transparent 0px, #333 89px, #fff 90px, #333 0px, #333 118px, #333 118px, transparent 0px),
								linear-gradient(0deg, #333 30px, transparent 30px, transparent 0px),
								repeating-linear-gradient(0deg, transparent 0px, transparent 28px, #fff 29px), #333;
			--body-scrollbar-thumb:rgba(255,255,255,0.5);
			--header-bg:linear-gradient(to bottom, #cccccc 0%, #cccccc 10%, #333333 10%, #808080 60%, #cccccc 100%);
			--footer-bg:linear-gradient(to top, #cccccc 0%, #cccccc 10%, #333333 10%, #808080 60%, #cccccc 100%);
			--menubar-bg:linear-gradient(to bottom, #cccccc 0%, #808080 40%, #333333 100%);
			--menu-bg:linear-gradient(to top, #cccccc 0%, #808080 40%, #333333 100%);
			--submenu-bg:#cccccc;
			--list-color:#000000;
			--info-color:#555555;
			--list-bg:#666666;
			--list-hover-color:#fff;
			--info-hover-color:#ddd;

			--popup-bg:linear-gradient(to right, #222, #333, #444 7%, #555 10%, #555, #555 90%, #444 93%, #333, #222);
			--popup-header-bg:linear-gradient(to right, #333333, #555555, #777777 7%, #999999 10%, #999999, #999999 90%, #777777 93%, #555555, #333333);
			--popup-close:linear-gradient(45deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 0%), linear-gradient(135deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 0%);
			--popup-close-hover:linear-gradient(45deg, transparent 40%, #fff 40%, #fff 60%, transparent 0%), linear-gradient(135deg, transparent 40%, #fff 40%, #fff 60%, transparent 0%);
			--popup-close-border:#ddd;
			--popup-close-border-hover:#fff;
			--close-btn:#000;
			--popup-submit-bg:#888;
			--popup-submit-hover-bg:#777;
			--popup-submit-active-bg:#333;
			--popup-shadow:1px 1px #555, 2px 2px #555, 3px 3px #555, 4px 4px #555, 5px 5px #555;
			--popup-shadow-hover:1px 1px #333, 2px 2px #333, 3px 3px #333, 4px 4px #333, 5px 5px #333;
			--login-popup-bg:linear-gradient(to bottom, #444, #888);
			--login-popup-box-shadow:5px 6px 0px -2px #555, -5px 5px 0px -2px #555, 0px -1px 0px 3px #999, 0px 10px 0px 0px #555, 0px -10px 0px 0px #777;
			--popup-msg-shadow:0px 0px 0px 4px #333, inset 0px 0px 15px 0px #000000, 2px 7px 1px 1px #999, -2px 7px 1px 1px #999;
			--popup-btn-border:#222;
			--popup-btn-shadow:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999;
			--popup-cnf-hover:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999, inset 2px 2px 10px 3px #4e6217;
			--popup-cancel-hover:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999, inset 2px 2px 10px 3px #691e1e;
			--accordion-header-bg:#999;
			--accordion-header-hover-bg:#777;
			--accordion-header-active-bg:#666;
			--accordion-header-border:#777;
			--accordion-header-border-hover:#666;
			--accordion-header-border-active:#555;
			--accordion-contents-bg:#777;
			--accordion-contents-border:#222;
			--input-focus-bg:#000;
			--input-color:#777;
			--input-focus-color:#fff;
			--success-bg:#ccc;
			--success-msg:#555;
			--success-color:#fff;
			--error-bg:#ccc;
			--error-msg:#555;
			--error-color:#fff;

			--table-th-bg:#333;
			--tr-first-bg:#777;
			--tr-second-bg:#888;
			--tr-third-bg:#555;
			--tr-fourth-bg:#666;
			--tr-hover-bg:#444;

			--live-table-border-color:#444;
			--count-down-bg:linear-gradient(to bottom, #ccc 0%, #ccc 10%, #333 10%, #808080 35%, #ccc 65%, #808080 85%, #333 100%);
		}
		@media(prefers-color-scheme:dark){
			.Auto, .Device {
				--main-bg: #333333;
				--matching-bg: #555555;
				--main-color: #fff;
				--matching-color: #cccccc;
				--main-border:#999999;
				--matching-border:#444444;
				--selection-bg:#ccc;
				--selection-color:#000;
				--pink-bg:#aaa;
				--pink-color:#000;
				--blue-bg:#aaa;
				--blue-color:#000;
				--loader-bg:#666;
				--loader-border:#333;
				--gear-color:#222;
				--body-bg:linear-gradient(90deg, transparent 85px, #fff 86px, transparent 0px, transparent 89px, #fff 90px, transparent 0px),
						linear-gradient(180deg, #000 85px, #fff 86px, transparent 0px, #000 89px, #fff 90px, #000 0px, #000 118px, #000 118px, transparent 0px),
						linear-gradient(0deg, #000 30px, transparent 30px, transparent 0px),
						repeating-linear-gradient(0deg, transparent 0px, transparent 28px, #fff 29px),
						radial-gradient(#333, #222, #111, #000 75%);
				--body-scrollbar:linear-gradient(180deg, #333 85px, #fff 86px, transparent 0px, #333 89px, #fff 90px, #333 0px, #333 118px, #333 118px, transparent 0px),
									linear-gradient(0deg, #333 30px, transparent 30px, transparent 0px),
									repeating-linear-gradient(0deg, transparent 0px, transparent 28px, #fff 29px), #333;
				--body-scrollbar-thumb:rgba(255,255,255,0.5);
				--header-bg:linear-gradient(to bottom, #cccccc 0%, #cccccc 10%, #333333 10%, #808080 60%, #cccccc 100%);
				--menubar-bg:linear-gradient(to bottom, #cccccc 0%, #808080 40%, #333333 100%);
				--menu-bg:linear-gradient(to top, #cccccc 0%, #808080 40%, #333333 100%);
				--submenu-bg:#cccccc;
				--list-color:#000000;
				--info-color:#555555;
				--list-bg:#666666;
				--list-hover-color:#fff;
				--info-hover-color:#ddd;
			
				--popup-bg:linear-gradient(to right, #222, #333, #444 7%, #555 10%, #555, #555 90%, #444 93%, #333, #222);
				--popup-header-bg:linear-gradient(to right, #333333, #555555, #777777 7%, #999999 10%, #999999, #999999 90%, #777777 93%, #555555, #333333);
				--popup-close:linear-gradient(45deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 0%), linear-gradient(135deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 0%);
				--popup-close-hover:linear-gradient(45deg, transparent 40%, #fff 40%, #fff 60%, transparent 0%), linear-gradient(135deg, transparent 40%, #fff 40%, #fff 60%, transparent 0%);
				--popup-close-border:#ddd;
				--popup-close-border-hover:#fff;
				--close-btn:#000;
				--popup-submit-bg:#888;
				--popup-submit-hover-bg:#555;
				--popup-submit-active-bg:#333;
				--popup-shadow:1px 1px #555, 2px 2px #555, 3px 3px #555, 4px 4px #555, 5px 5px #555;
				--popup-shadow-hover:1px 1px #333, 2px 2px #333, 3px 3px #333, 4px 4px #333, 5px 5px #333;
				--login-popup-bg:linear-gradient(to bottom, #444, #888);
				--login-popup-box-shadow:5px 6px 0px -2px #555, -5px 5px 0px -2px #555, 0px -1px 0px 3px #999, 0px 10px 0px 0px #555, 0px -10px 0px 0px #777;
				--popup-msg-shadow:0px 0px 0px 4px #333, inset 0px 0px 15px 0px #000000, 2px 7px 1px 1px #999, -2px 7px 1px 1px #999;
				--popup-btn-border:#222;
				--popup-btn-shadow:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999;
				--popup-cnf-hover:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999, inset 2px 2px 10px 3px #4e6217;
				--popup-cancel-hover:0px 0px 0px 4px #333, 2px 6px 1px 1px #999, -2px 6px 1px 1px #999, inset 2px 2px 10px 3px #691e1e;
				--accordion-header-bg:#999;
				--accordion-header-hover-bg:#777;
				--accordion-header-active-bg:#666;
				--accordion-header-border:#777;
				--accordion-header-border-hover:#666;
				--accordion-header-border-active:#555;
				--accordion-contents-bg:#777;
				--accordion-contents-border:#222;
				--input-focus-bg:#000;
				--input-color:#777;
				--input-focus-color:#fff;
				--success-bg:#ccc;
				--success-msg:#555;
				--success-color:#fff;
				--error-bg:#ccc;
				--error-msg:#555;
				--error-color:#fff;
			
				--table-th-bg:#333;
				--tr-first-bg:#777;
				--tr-second-bg:#888;
				--tr-third-bg:#555;
				--tr-fourth-bg:#666;
				--tr-hover-bg:#444;
			
				--live-table-border-color:#444;
				--count-down-bg:linear-gradient(to bottom, #ccc 0%, #ccc 10%, #333 10%, #808080 35%, #ccc 65%, #808080 85%, #333 100%);
			}
		}
		html, body{/*-webkit-user-select:none;-webkit-touch-callout:none;-moz-user-select:none;-moz-touch-callout:none;-o-user-select:none;-o-touch-callout:none;-ms-user-select:none;-ms-touch-callout:none;-khtml-user-select:none;-khtml-touch-callout:none;*/display:flex;flex-direction:column;align-items:center;width:100%;color:var(--main-color);background:url('../my_images/background-texture1.jpg'), url('../my_images/building1.jpg'), var(--body-bg);
			background-size:100% 100%;background-position:0px 0px;background-repeat:repeat;background-attachment:fixed;margin:0px;/*font-size:calc(11px + (14 - 10) * (100vw - 320px) / (1080 - 320));*/font-family:Arial, Helvetica, sans-serif;line-height:1;/*overflow-x:hidden;*/}
		.row .animatedParent > .animated{background-color:var(--matching-bg);}
		/* Scrollbar */
		body::-webkit-scrollbar{width:10px;display:block;}
		body::-webkit-scrollbar-track{background:var(--body-scrollbar);box-shadow:inset 0 0 10px rgba(0,0,0,0.5);border-radius:0px;}
		body::-webkit-scrollbar-thumb{background:var(--body-scrollbar-thumb);border-radius:25px 0px 0px 25px;}
		body::-webkit-scrollbar-thumb:hover{box-shadow:inset 0 0 15px rgba(0,0,0,1);}
		/* For Print */
		@media print{
			.noPrint{display:none !important;}
			.forPrint{display:block !important;}
			@page{size:A4 portrait !important;margin: 0mm !important;}
			/* *{box-sizing:border-box!important;margin:0px!important;padding:0px!important;word-spacing:0px!important;letter-spacing:0px!important;word-wrap:break-word!important;} */
			html, body{width:210mm !important;height:297mm !important;padding:1.5mm 3mm !important;font-size:1rem !important;font-family:Arial, Helvetica, sans-serif !important;background-image:none  !important;background:none !important;border:none !important;overflow:visible !important;}
			/* A5 (148mm x 210mm)
			A4 (210mm x 297mm) - the default size
			A3 (297mm x 420mm)
			B3 (353mm x 500mm)
			B4 (250mm x 353mm)
			JIS-B4 (257mm x 364mm)
			letter (8.5in x 11in)
			legal (8.5in x 14in)
			ledger (11in x 17in) */
		}
		::-moz-selection{background:var(--selection-bg);color:var(--selection-color);}
		::selection{background:var(--selection-bg);color:var(--selection-color);}
		:disabled{cursor:not-allowed;opacity:0.5;}
		table i{cursor:pointer;}
		table span{cursor:default;}
		.container{width:100%;display:flex;flex-direction:column;align-items:start;justify-content:center;gap:25px;padding:0 25px;}
		.container .container-field, .container .container-box, .container form{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}
		.container .container-field{gap:25px;}
		.container .container-box{gap:0px;padding:25px;background-color:var(--matching-bg);}
		div.heading-line{position: relative;width:100%;margin-bottom:20px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0px;padding:10px 15px;font-weight: bold;overflow: hidden;}
		.heading-line.pink{background:var(--pink-bg);}
		.heading-line.blue{background:var(--blue-bg);}
		div.heading-line:before{content: "";position: absolute;top: -17px;bottom: -17px;left: -17px;right: -17px;}
		.heading-line.pink:before{border: 20px dashed var(--pink-color);}
		.heading-line.blue:before{border: 20px dashed var(--blue-color);}
		.heading-line div:first-child{font-size:1.75rem;}
		.heading-line div:last-child{font-size:1.25rem;}
		.heading-line.pink div:last-child{color: var(--blue-color);}
		.heading-line.pink div:first-child{color:var(--pink-color);}
		.heading-line.blue div:last-child{color: var(--pink-color);}
		.heading-line.blue div:first-child{color:var(--blue-color);}
		@media only screen and (min-width:1281px){
			.container{flex-direction:row-reverse;}
			.container .head-container{width:66.66%;}
			.container .side-container{width:33.33%;}
		}
		.input-container{width:100%;display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));grid-auto-flow:dense;grid-gap:15px;align-items:start;}
		.input-box{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;}
		.input-box .input-field{position: relative;width:100%;display: flex;flex-flow: column;}
		.input-box .input-field input{padding:2px 20px 2px 30px;}
		.input-box .input-field textarea{height:100%;min-height:100px;padding:2px 20px 2px 30px;}
		.input-box .input-field input, .input-field textarea{width: 100%;background: transparent;border: none;outline: none;resize: none;overflow: hidden;-webkit-appearance: none;color:var(--main-color);font-size:1.1rem;box-shadow: inset 0 -1px 0 #888888;transition: .5s;}
		.input-field input:focus, .input-field input:hover, .input-field textarea:focus, .input-field textarea:hover{box-shadow: inset 0 -2px 0 #888888;}
		.input-field input:not(:placeholder-shown), .input-field textarea:not(:placeholder-shown){box-shadow: inset 0 -2px 0 var(--matching-color);}
		.input-field label{position: absolute;top: 0;left: 0;color: #888888;pointer-events: none;transition: 0.5s;}
		.input-field input, .input-field textarea, label{touch-action: manipulation;transform-origin: left bottom;transition: all 0.2s;}
		.input-field input:placeholder-shown ~ label{left:31px;top: calc(50% + 1px);transform: translateY(-50%) scale(1.1);white-space: nowrap;overflow: hidden;text-overflow: ellipsis;cursor: text;}
		.input-field textarea:placeholder-shown ~ label{left:31px;top: 8px;transform: /*translate(30px, 1.95rem)*/ scale(1.1);white-space: nowrap;overflow: hidden;text-overflow: ellipsis;cursor: text;}
		.input-field input:focus ~ label, .input-field input:not(:placeholder-shown) ~ label, .input-field textarea:focus ~ label, .input-field textarea:not(:placeholder-shown) ~ label{color:var(--matching-color);left:-1px;top: -8px;transform: translate3d(0, -50%, 0) scale(0.75);}
		.input-field i{position: absolute;left: 0;top: 50%;transform:translate(0%, -50%);font-size:1.5rem;color: #888888;transition: 0.5s;}
		.input-field.textarea i{top: 16px;}
		.input-field input:not(:placeholder-shown) ~ i, .input-field input:focus ~ i, .input-field textarea:not(:placeholder-shown) ~ i, .input-field textarea:focus ~ i{color:var(--matching-color);}
		.input-field input:focus ~ label, .input-field input:hover ~ label, .input-field input:focus ~ i, .input-field input:hover ~ i, .input-field textarea:focus ~ label, .input-field textarea:hover ~ label, .input-field textarea:focus ~ i, .input-field textarea:hover ~ i{color:var(--main-color);}
		.input-field small{position: absolute;right: 0;top: calc(50% - 2px);transform:translateY(-50%);font-size:1rem;cursor: pointer;}
		.input-field.textarea small{top: 12px;}
		.input-field input:invalid ~ small:after, .input-field textarea:invalid ~ small:after{content: '✖';color: tomato;}
		.input-field input:valid ~ small:after, .input-field textarea:valid ~ small:after{content: '✓';color: var(--main-color);}
		.input-field input ~ span, .input-field textarea ~ span{position: absolute; bottom: 0; left: 0; width: 0; height: 2px; background-color: var(--main-color); transition: 0.4s;}
		.input-field input:focus ~ span, .input-field textarea:focus ~ span{width: 100%; transition: 0.4s;}
		.input-field strong{position: absolute;right: 0;bottom: calc(100% - 1px);transition: all 0.2s;font-size:0.75rem;display: none;}
		.input-field input:focus ~ strong{display: block;}
		.input-field code{padding:2px 7px;}
		.input-field a{padding:2px 7px;}
		.input-field button{bottom: 10px;padding:5px 15px;}
		.input-field a, .input-field button{font-size:1.1rem;}
		.input-field code, .input-field a{top: calc(50% - 1px);transform:translateY(-50%);font-size:1rem;}
		.input-field code, .input-field a, .input-field button{position: absolute;right: 0;background:transparent;color: #0ef;border:1px solid #0ef;border-radius:3px;opacity:0.25;-webkit-appearance: none;appearance:none;cursor:pointer;transition: .5s;}
		.input-field input:not(:placeholder-shown) ~ code, .input-field input:not(:placeholder-shown) ~ a, .input-field textarea:not(:placeholder-shown) ~ button{background:#0ef;color: #fff;border-color:#fff;opacity:1;}
		/* .input-field input:focus ~ code, .input-field input:not(:placeholder-shown) ~ code, .input-field textarea:focus ~ button, .input-field textarea:not(:placeholder-shown) ~ button{background:#0ef;color: #fff;border-color:#fff;} */
		.input-field dl, .input-field dt{position: absolute;top: calc(50% - 1px);transform:translateY(-50%);font-size:1.5rem;cursor:pointer;}
		.input-field dl{right: 40px;color:green;}
		.input-field dt{right: 15px;color:var(--close-btn);border-radius:50%;}
		.input-field input::-webkit-input-placeholder, .input-field textarea::-webkit-input-placeholder{opacity: 0;transition: inherit;}
		.input-field input:focus::-webkit-input-placeholder, .input-field textarea:focus::-webkit-input-placeholder{opacity: 0.5;color:var(--matching-color);}
		.input-field em{position:absolute;display:inline-block;right:60px;top:calc(50% - 3px);transform: translateY(-50%);z-index:9;}
		.input-box .error-msg, .input-box .exists-err{width:100%;font-size: 0.75rem;font-weight:bold;color:tomato;text-align:left;float:left;}
		/* For Submit Button */
		.button-box{width:100%;display:flex;flex-direction:row;align-items:center;justify-content:center;gap:10px;}
		.button-box input[type=submit]{background-image:linear-gradient(#081b29, deepskyblue, #081b29, deepskyblue);border:2px solid deepskyblue;}
		.button-box input[type=button]{background-image:linear-gradient(#081b29, #00ff00, #081b29, #00ff00);border:2px solid #00ff00;}
		.button-box button{background-image:linear-gradient(#081b29, #0ef, #081b29, #0ef);border:2px solid #0ef;display:flex;flex-direction:row;align-items:center;justify-content:center;gap:5px;}
		.button-box button i{font-size:1.25rem;}
		.button-box input[type=reset]{background-image:linear-gradient(#081b29, deeppink, #081b29, deeppink);border:2px solid deeppink;}
		.button-box input[type=submit], .button-box input[type=button], .button-box input[type=reset], .button-box button{min-width:150px;padding:10px 25px;background-size:100% 300%;background-position:0 50%;background-repeat:no-repeat;color:#fff;border-radius: 25px;font-size:1rem;font-weight:bold;white-space:nowrap;text-shadow:0px -1px 0 #000;box-shadow:5px 5px 5px 5px rgba(0,0,0,0.5);-webkit-transition:all 150ms ease;-moz-transition:all 150ms ease;-o-transition:all 150ms ease;transition:all 150ms ease;transition: background 0.5s;cursor:pointer;outline: none;}
		.button-box input[type=submit]:hover, .button-box input[type=submit]:focus, .button-box input[type=button]:hover, .button-box input[type=button]:focus, .button-box input[type=reset]:hover, .button-box input[type=reset]:focus, .button-box button:hover, .button-box button:focus{background-position:0 0;-webkit-animation:hightLight 1.2s linear infinite;-moz-animation:hightLight 1.2s linear infinite;-o-animation:hightLight 1.2s linear infinite;animation:hightLight 1.2s linear infinite;}
		@-webkit-keyframes hightLight{0%{color:#ddd;text-shadow:0 -1px 0 #000;}50%{color:#fff;text-shadow:0 -1px 0 #444, 0 0 5px #ffd, 0 0 8px #fff;}100%{color:#ddd;text-shadow:0 -1px 0 #000;}}
		@-moz-keyframes hightLight{0%{color:#ddd;text-shadow:0 -1px 0 #000;}50%{color:#fff;text-shadow:0 -1px 0 #444, 0 0 5px #ffd, 0 0 8px #fff;}100%{color:#ddd;text-shadow:0 -1px 0 #000;}}
		@-o-keyframes hightLight{0%{color:#ddd;text-shadow:0 -1px 0 #000;}50%{color:#fff;text-shadow:0 -1px 0 #444, 0 0 5px #ffd, 0 0 8px #fff;}100%{color:#ddd;text-shadow:0 -1px 0 #000;}}
		@keyframes hightLight{0%{color:#ddd;text-shadow:0 -1px 0 #000;}50%{color:#fff;text-shadow:0 -1px 0 #444, 0 0 5px #ffd, 0 0 8px #fff;}100%{color:#ddd;text-shadow:0 -1px 0 #000;}}
		.button-box input[type=submit]:active, .button-box input[type=button]:active, .button-box input[type=reset]:active, .button-box button:active{-webkit-transform:translateY(5px) translateX(5px);-moz-transform:translateY(5px) translateX(5px);-o-transform:translateY(5px) translateX(5px);transform:translateY(5px) translateX(5px);-webkit-animation:none;-moz-animation:none;-o-animation:none;animation:none;color:#fff;text-shadow:0 -1px 0 #444, 0 0 5px #ffd, 0 0 8px #fff;box-shadow:0 1px 0 #666, 0 2px 0 #444, 0 2px 2px rgba(0,0,0,0.9);}
		.button-box input[type=submit]:disabled, .button-box input[type=button]:disabled, .button-box input[type=reset]:disabled, .button-box button:disabled{cursor:not-allowed;opacity:0.5;}
		.password_validation{position:absolute;width:100%;left:50%;bottom:100%;transform:translateX(-50%);background:#007780;box-shadow: 0 -1px 10px #0ef;padding:10px 15px;border-radius:5px;float:left;display:none;}
		.password_validation h3{width:100%;font-size:0.9rem;color:#fff;white-space: nowrap;}
		.password_validation p{width:100%;padding:1px 10px;font-size:0.8rem;white-space: nowrap;}
		.password_validation .valid{color:#0ef;}
		.password_validation .valid:before{content:"✔";}
		.password_validation .invalid{color:tomato;}
		.password_validation .invalid:before{content:"✖";}
		.password_validation .valid:before, .password_validation .invalid:before{position:relative;left:-10px;}
		.form-box .remember{width:100%;display:flex;flex-direction:row;align-items:center;justify-content: start;}
		.form-box .accept{width:100%;display:flex;flex-direction:row;align-items:start;justify-content: start;font-size: 0.95rem;}
		.form-box .remember label, .form-box .accept label{color:#666;}
		.form-box .remember input[type="checkbox"]:checked + label, .form-box .accept input[type="checkbox"]:checked + label{color:var(--main-color);}
		
		form{width:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}
		form.popup-container{position:fixed;height:100%;top:0;left:0;background:rgba(0, 0, 0, 0.5);background-image:radial-gradient(#000 2px, transparent 2px);background-size: calc(10 * 2px) calc(10 * 2px);transition: modelShadow 250ms 700ms ease;overflow:hidden;z-index:100;}
		form .popup-box{position:relative;width:500px;min-width:250px;max-width:100%;background:var(--popup-bg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:25px;padding-bottom:25px;border-radius:25px;-webkit-animation-name: popupBounceIn;animation-name: popupBounceIn;-webkit-animation-duration: 0.5s;animation-duration: 0.5s;-webkit-transition-delay:0.1s;transition-delay:0.1s;-webkit-animation-fill-mode: both;animation-fill-mode: both;transition:all 1s ease;overflow:hidden;}
		form .popup-header{width:100%;padding:17px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--popup-header-bg);color:var(--main-color);white-space:nowrap;font-size:1.5rem;font-weight:bold;}
		form .form_field{width:calc(100% - 100px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;}
		form .error_msg{width:calc(100% - 100px);padding:7px 10px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:1rem;font-weight:bold;border-radius:5px;display:none;}
		form .popup-footer{width:calc(100% - 100px);display:flex;flex-direction:row;align-items:center;justify-content:center;gap:5px;white-space:nowrap;font-size:1rem;font-weight:bold;}
		form .popupClose{position:absolute;width:24px;height:24px;top:16px;right:16px;background:var(--popup-close);border:2px solid var(--popup-close-border);border-radius:50%;transition:all 0.1s ease;cursor: pointer;z-index: 2;}
		form .popupClose:hover{width:28px;height:28px;top:14px;right:14px;background:var(--popup-close-hover);border:4px solid var(--popup-close-border-hover);}
		@-webkit-keyframes popupBounceIn{0%{transform: scale(0.1);opacity: 0;}40%{transform: scale(1.15);opacity: 1;}70%{transform: scale(0.9);opacity: 0.75;}100%{transform: scale(1);}}
		@-moz-keyframes popupBounceIn{0%{transform: scale(0.1);opacity: 0;}40%{transform: scale(1.15);opacity: 1;}70%{transform: scale(0.9);opacity: 0.75;}100%{transform: scale(1);}}
		@-o-keyframes popupBounceIn{0%{transform: scale(0.1);opacity: 0;}40%{transform: scale(1.15);opacity: 1;}70%{transform: scale(0.9);opacity: 0.75;}100%{transform: scale(1);}}
		@keyframes popupBounceIn{0%{transform: scale(0.1);opacity: 0;}40%{transform: scale(1.15);opacity: 1;}70%{transform: scale(0.9);opacity: 0.75;}100%{transform: scale(1);}}
			
		/* For Radio Toolbar */
		.radio-toolbar{width:100%;padding:4px 4px 1px;border:0px solid silver;text-align:center;float:left;}
		.radio-toolbar input[type="radio"]{width:0px;opacity:0;position:fixed;}
		.radio-toolbar label{display:inline-block;background:linear-gradient(to bottom, #fff 25%, #aaa);padding:10px 20px 9px;margin:0px;font-family:sans-serif, Arial;font-size:1rem;color:tomato;border:1px solid tomato;border-radius:5px;}
		.radio-toolbar label:hover{background:linear-gradient(to bottom, #aaa, #fff 75%);cursor:pointer;}
		.radio-toolbar input[type="radio"]:focus + label{border: 1px dashed green;}
		.radio-toolbar input[type="radio"]:checked + label{background:linear-gradient(to bottom, #aaa, #fff 75%);border-color:green;color:green;}
	</style>
</head>
<body>
	<div class="heading-line pink">
		<div>IRCTC</div>
		<div>INDIAN RAILWAY</div>
	</div>
	<div class="container noPrint">
		<div class="container-field head-container">
			<div class="container-box">
				<div class="heading-line blue">
					<div>ADD YOUR DATA</div>
				</div>
				<form id="add_irctc_id" class="form-box" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" style="gap:15px;">
					<div class="input-container">
						<div class="input-box">
							<div class="input-field">
								<input type="text" name="username" id="username" placeholder="Username" maxlength="50" value="">
								<label for="username">Username *</label>
								<i class="bx bxs-user"></i>
								<strong></strong>
								<span></span>
							</div>
							<!-- <div class="errMsg"></div> -->
							<div class="error-msg">
							</div>
						</div>
						<div class="input-box">
							<div class="input-field">
								<input type="password" name="password" id="password" data-keyup="password" placeholder="**********" maxlength="50" value="<?php echo $cookie_password; ?>">
								<label for="password">Password *</label>
								<i class="bx bxs-lock-alt"></i>
								<strong></strong>
								<span></span>
							</div>
							<div class="error-msg">
							</div>
						</div>
					</div>
					<div class="remember">
						<input type="checkbox" name="remember_password" id="remember_password" <?php echo $checked_remember_password;?>>&nbsp;&nbsp;<label for="remember_password"><b>Remember Me Only Password !</b></label>
					</div>
					<div class="button-box">
						<button>Submit</button>
					</div>
				</form>
			</div>
			<div class="container-box">
				<div class="heading-line pink">
					<div>ACTIVE</div>
				</div>
				<table border="1" style="width:100%;border-collapse:collapse;white-space:nowrap;">
					<thead style="background:#ddd;">
						<tr>
							<th style="width:10px;padding:12px 10px;">S.No.</th>
							<th style="padding:12px 10px;">Username</th>
							<th style="padding:12px 10px;">Password</th>
							<th style="padding:12px 10px;">Email</th>
							<th style="width:90px;padding:12px 10px;">Action</th>
						</tr>
					</thead>
					<tbody id="all_id">
					</tbody>
				</table>
			</div>
		</div>
		<div class="container-field side-container">
			<div class="container-box">
				<div class="heading-line pink">
					<div>SALE</div>
				</div>
				<form id="irctc_id_purchage" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post" style="gap:15px;">
					<div class="input-container">
						<div class="input-box">
							<div class="input-field">
								<input type="text" name="quantity" id="quantity" placeholder="Quantity" maxlength="3" value="" pattern="[\d]+">
								<label>Quantity *</label>
								<i class="bx bxs-user"></i>
								<strong></strong>
								<small></small>
								<span></span>
							</div>
							<!-- <div class="errMsg"></div> -->
							<div class="error-msg">
							</div>
						</div>
					</div>
					<div class="button-box">
						<button>Sale Now</button>
					</div>
				</form>
			</div>
			<div class="container-box">
				<div class="heading-line blue">
					<div>SALED</div>
				</div>
				<table border="1" style="width:100%;border-collapse:collapse;white-space:nowrap;">
					<thead style="background:#ddd;">
						<tr>
							<th style="width:10px;padding:12px 10px;">S.No.</th>
							<th style="padding:12px 10px;">Username</th>
							<th style="width:20px;padding:12px 10px;">Status</th>
						</tr>
					</thead>
					<tbody id="sale">
					</tbody>
				</table>
			</div>
			<div class="container-box">
				<div class="heading-line pink">
					<div>UNUSABLED</div>
				</div>
				<table border="1" style="width:100%;border-collapse:collapse;white-space:nowrap;">
					<thead style="background:#ddd;">
						<tr>
							<th style="width:10px;padding:12px 10px;">S.No.</th>
							<th style="padding:12px 10px;">Username</th>
							<th style="width:90px;padding:12px 10px;">Status</th>
						</tr>
					</thead>
					<tbody id="unused">
					</tbody>
				</table>
			</div>
		</div>
	</div>
	
	<form name="irctc_update_form" id="irctc_update_form" class="popup-container" action="javascript:void(0)" method="POST" style="display:none;">
		<div class="popup-box">
			<input type="reset" name="reset" value="" class="popupClose" onclick="document.body.style.overflowY='auto';$('#irctc_update_form').fadeOut();">
			<div class="popup-header">IRCTC User ID & Password Update !</div>
			<div id="err_msg" class="error_msg"></div>
			<div class="form_field">
				<div class="input-container">
					<div class="input-box">
						<div class="input-field">
							<input type="text" name="irctc_user_id" id="irctc_user_id" class="" data-keypress="" placeholder="User ID" maxlength="20">
							<label for="irctc_user_id">User ID *</label>
							<i class="bx bxs-user"></i>
							<strong></strong>
							<small></small>
							<span></span>
						</div>
						<div class="error-msg">
						</div>
					</div>
					<div class="input-box">
						<div class="input-field">
							<input type="password" name="irctc_password" id="irctc_password" data-keypress="password" placeholder="Password" maxlength="25">
							<label for="irctc_password">Password *</label>
							<i class="bx bxs-lock-alt"></i>
							<strong></strong>
							<small></small>
							<span></span>
						</div>
						<div class="error-msg">
						</div>
					</div>
				</div>
					<div class="input-box">
						<div class="input-field">
							<input type="email" name="irctc_email" id="irctc_email" data-keypress="email" placeholder="Email" maxlength="25">
							<label for="irctc_email">Email *</label>
							<i class="bx bxs-lock-alt"></i>
							<strong></strong>
							<small></small>
							<span></span>
						</div>
						<div class="error-msg">
						</div>
					</div>
				</div>
				<input type="hidden" name="irctc_id" id="irctc_id" readonly>
			</div>
			<div class="button-box">
				<button>Update</button>
			</div>
		</div>
	</form>
	<form name="irctc_replacement_form" id="irctc_replacement_form" class="popup-container" action="javascript:void(0)" method="POST" style="display:none;">
		<div class="popup-box">
			<input type="reset" name="reset" value="" class="popupClose" onclick="document.body.style.overflowY='auto';$('#irctc_replacement_form').fadeOut();">
			<div class="popup-header">IRCTC ID Replacement !</div>
			<div id="err_msg" class="error_msg"></div>
			<div class="form_field">
				<div class="radio-toolbar">
						<input type="radio" name="irctc_id_replacement" id="replacement_accept" value="accept"><label for="replacement_accept">Accept</label>
						<input type="radio" name="irctc_id_replacement" id="replacement_reject" value="reject"><label for="replacement_reject">Reject</label>
				</div>
				<input type="hidden" name="irctc_replacement_id" id="irctc_replacement_id" readonly>
			</div>
			<div class="button-box">
				<button>Submit</button>
			</div>
		</div>
	</form>
	<form name="status_update_form" id="status_update_form" class="popup-container" action="javascript:void(0)" method="POST" style="display:none;">
		<div class="popup-box">
			<input type="reset" name="reset" value="" class="popupClose" onclick="document.body.style.overflowY='auto';$('#status_update_form').fadeOut();">
			<div class="popup-header">IRCTC ID Status Update !</div>
			<div id="err_msg" class="error_msg"></div>
			<div class="form_field">
				<div class="radio-toolbar">
						<input type="radio" name="irctc_id_status" id="replacement_recheck" value="recheck"><label for="replacement_recheck">Re Check</label>
						<input type="radio" name="irctc_id_status" id="replacement_incorrect" value="incorrect"><label for="replacement_incorrect">Incorrect</label>
				</div>
				<div class="radio-toolbar">
						<input type="radio" name="irctc_id_status" id="replacement_invalid" value="invalid"><label for="replacement_invalid">Invalid</label>
						<input type="radio" name="irctc_id_status" id="replacement_disable" value="disable"><label for="replacement_disable">Disable</label>
				</div>
				<input type="hidden" name="irctc_status_id" id="irctc_status_id" readonly>
			</div>
			<div class="button-box">
				<button>Submit</button>
			</div>
		</div>
	</form>
	<script>
		$(document).ready(function(){
			function IRCTCIdList(){
				$.ajax({
					url:"index.php",
					method:"POST",
					data:{fetch_all_id:true},
					cache:false,
					success:function(response){
						$('#all_id').html(response);
					}
				});
			}IRCTCIdList();
			function saleIdList(){
				$.ajax({
					url:"index.php",
					method:"POST",
					data:{sale_id:true},
					cache:false,
					success:function(response){
						$('#sale').html(response);
					}
				});
			}saleIdList();
			function unusedList(){
				$.ajax({
					url:"index.php",
					method:"POST",
					data:{unused_id:true},
					cache:false,
					success:function(response){
						$('#unused').html(response);
					}
				});
			}unusedList();
			$('#add_irctc_id').submit(function(e){
				e.preventDefault();
				var remember  = $('input[type=checkbox][name=remember_password]:checked').length;
				$.ajax({
					type: "POST",
					url: "index.php",
					data: new FormData(this),
					processData: false,
					contentType: false,
					cache: false,
					async: false,
					beforeSend: function (){
						$('#add_irctc_id button').html('<i class="fa fa-spinner fa-spin"></i> Please Wait...').attr('disabled', true);
					},
					error:function(){
					},
					success: function (response){
						if(response==''){
							if(remember != 0){
								$('#username').val('').focus();
							}else{
								$('#username, #password').val('');
								$('#username').focus();
							}
							IRCTCIdList();
							// $('#add_irctc_id')[0].reset();
						}else{
							alert(response);
						}
						$('#add_irctc_id button').html('Submit').attr('disabled', false);
					},
					complete: function (){
						// $('#add_irctc_id button').html('Submit').attr('disabled', false);
					}
				});
			});
			$('#irctc_id_purchage').submit(function(e){
				e.preventDefault();
				$.ajax({
					type: "POST",
					url: "index.php",
					data: new FormData(this),
					processData: false,
					contentType: false,
					cache: false,
					async: false,
					beforeSend: function (){
						$('#irctc_id_purchage button').html('<i class="fa fa-spinner fa-spin"></i> Please Wait...').attr('disabled', true);
					},
					error:function(){
					},
					success: function (response){
						if(response==''){
							IRCTCIdList();
							saleIdList();
							$('#irctc_id_purchage')[0].reset();
						}else{
							alert(response);
						}
						$('#irctc_id_purchage button').html('Purchage Now').attr('disabled', false);
					},
					complete: function (){
						// $('#irctc_id_purchage button').html('Purchage Now').attr('disabled', false);
					}
				});
			});
			$(document).on('click','#all_id .replacement', function(e){
				var id = $(this).data('id');
				$.ajax({
					url:"index.php",
					method:"POST",
					data:{replacement:true,id:id},
					cache:false,
					success:function(response){
						if(response==''){
							alert('SUCCESS: Replacement request successfully send !');
							IRCTCIdList();
							saleIdList();
							unusedList();
						}else{
							alert(response);
						}
					}
				});
			});
			$(document).on('click','.replace', function(e){
				$('#irctc_replacement_id').val($(this).data('id'));
				$('#irctc_replacement_form').show();
			});
			$('#irctc_replacement_form').submit(function(e){
				e.preventDefault();
				var id = $('replacement_id').val();
				var replacement = $('irctc_id_replacement').val();
				$.ajax({
					type: "POST",
					url: "index.php",
					data: new FormData(this),
					processData: false,
					contentType: false,
					cache: false,
					async: false,
					beforeSend: function (){
						$('#irctc_replacement_form button').html('<i class="fa fa-spinner fa-spin"></i> Please Wait...').attr('disabled', true);
					},
					error:function(){
					},
					success:function(response){
						if(response==''){
							$('#irctc_replacement_form').hide();
							saleIdList();
							unusedList();
						}else{
							alert(response);
						}
						$('#irctc_replacement_form button').html('Submit').attr('disabled', false);
					},
					complete: function (){
						// $('#irctc_replacement_form button').html('Submit').attr('disabled', false);
					}
				});
			});
			$(document).on('click','.id_edit', function(e){
				$('#irctc_id').val($(this).data('id'));
				$('#irctc_user_id').val($(this).data('username'));
				$('#irctc_password').val($(this).data('password'));
				$('#irctc_update_form').show();
			});
			$('#irctc_update_form').submit(function(e){
				e.preventDefault();
				var username = $('#irctc_user_id').val();
				var password = $('#irctc_password').val();
				var id       = $('#irctc_id').val();
				if(username==''){
					$('#irctc_user_id').focus();
					alert('Please enter User ID.');
					return false;
				}else if(password==''){
					$('#irctc_password').focus();
					alert('Please enter Password.');
					return false;
				}else if(id==''){
					alert('Data not fetched.');
					return false;
				}else{
					$.ajax({
						type: "POST",
						url: "index.php",
						data: new FormData(this),
						processData: false,
						contentType: false,
						cache: false,
						async: false,
						beforeSend: function (){
							$('#irctc_update_form button').html('<i class="fa fa-spinner fa-spin"></i> Please Wait...').attr('disabled', true);
						},
						error:function(){
						},
						success:function(response){
							if(response==''){
								$('#irctc_update_form').hide();
								IRCTCIdList();
								saleIdList();
								unusedList();
							}else{
								alert(response);
							}
							$('#irctc_update_form button').html('Update').attr('disabled', false);
						},
						complete: function (){
							// $('#irctc_update_form button').html('Update').attr('disabled', false);
						}
					});
				}
			});
			$(document).on('click','.status_update', function(e){
				$('#irctc_status_id').val($(this).data('id'));
				$('#status_update_form').show();
			});
			$('#status_update_form').submit(function(e){
				// e.preventDefault();
				var id       = $('#irctc_status_id').val();
				var status = $('#irctc_id_status').val();
				/*if(status==''){
					$('#irctc_user_id').focus();
					alert('Please enter User ID.');
					return false;
				}else */if(id==''){
					alert('Data not fetched.');
					return false;
				}else{
					$.ajax({
						type: "POST",
						url: "index.php",
						data: new FormData(this),
						processData: false,
						contentType: false,
						cache: false,
						async: false,
						beforeSend: function (){
							$('#status_update_form button').html('<i class="fa fa-spinner fa-spin"></i> Please Wait...').attr('disabled', true);
						},
						error:function(){
						},
						success:function(response){
							if(response==''){
								$('#status_update_form').hide();
								IRCTCIdList();
								unusedList();
							}else{
								alert(response);
							}
							$('#status_update_form button').html('Update').attr('disabled', false);
						},
						complete: function (){
							// $('#status_update_form button').html('Update').attr('disabled', false);
						}
					});
				}
			});
			$(document).on('click','.id_delete', function(e){
				var id = $(this).data('id');
                if(window.confirm('Are you sure ? You want to delete this IRCTC ID !') == false){
					return false;
                }else{
					$.ajax({
						url:"index.php",
						method:"POST",
						data:{irctc_id_delete:true,id:id},
						cache:false,
						success:function(response){
							if(response==''){
								IRCTCIdList();
								saleIdList();
								unusedList();
							}else{
								alert(response);
							}
						}
					});
				}
			});
		});
	</script>
</body>
</html>