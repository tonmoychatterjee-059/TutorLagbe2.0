<?php
require_once __DIR__ . '/includes/admin_auth_check.php'; require_once __DIR__ . '/../config/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request.'); }
$action=(string)($_POST['action']??''); $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); $redirect=(string)($_POST['redirect']??'dashboard.php');
if(!$id || !preg_match('/^[a-z_]+\.php$/',$redirect)){http_response_code(400);exit('Invalid request.');}
try{$pdo=db();
 if($action==='approve_tutor'){$stmt=$pdo->prepare("UPDATE users SET is_verified=1,is_suspended=0,rejection_reason=NULL WHERE id=:id AND role='tutor'");$msg='Tutor approved successfully.';}
 elseif($action==='reject_tutor'){ $reason=trim((string)($_POST['reason']??''));if(mb_strlen($reason)<3||mb_strlen($reason)>1000)throw new InvalidArgumentException('Provide a rejection reason between 3 and 1000 characters.');$stmt=$pdo->prepare("UPDATE users SET is_verified=0,rejection_reason=:reason WHERE id=:id AND role='tutor'");$stmt->bindValue(':reason',$reason);$msg='Tutor application rejected and reason recorded.';}
 elseif($action==='toggle_tutor'){$stmt=$pdo->prepare("UPDATE users SET is_suspended=IF(is_suspended=1,0,1) WHERE id=:id AND role='tutor'");$msg='Tutor status updated.';}
 elseif($action==='feature_tutor'){$stmt=$pdo->prepare("INSERT INTO tutors (user_id, is_featured) VALUES (:id, 1) ON DUPLICATE KEY UPDATE is_featured=1");$msg='Tutor added to featured educators.';}
 elseif($action==='unfeature_tutor'){$stmt=$pdo->prepare("UPDATE tutors SET is_featured=0 WHERE user_id=:id");$msg='Tutor removed from featured educators.';}
 elseif($action==='toggle_student'){$stmt=$pdo->prepare("UPDATE users SET is_suspended=IF(is_suspended=1,0,1) WHERE id=:id AND role='student'");$msg='Student status updated.';}
 else throw new InvalidArgumentException('Unknown action.');
 $stmt->bindValue(':id',$id,PDO::PARAM_INT);$stmt->execute();if(!$stmt->rowCount())throw new RuntimeException('The requested account was not found.');
 // Add admin_logs here later: admin_id, action, target_user_id, created_at.
 if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){header('Content-Type: application/json');echo json_encode(['ok'=>true,'message'=>$msg]);exit;}flash('admin_success',$msg);
}catch(Throwable $e){$message=$e instanceof InvalidArgumentException?$e->getMessage():'The action could not be completed.';if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>$message]);exit;}flash('admin_error',$message);}header('Location: '.base_url('admin/'.$redirect));exit;
