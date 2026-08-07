<?php
declare(strict_types=1);
if(!isset($_SESSION['user_id']))go('/');
$id=(int)$_SESSION['user_id'];
$q=$pdo->prepare('SELECT u.username,c.full_name,c.email,c.phone_number FROM user u LEFT JOIN candidate c ON c.id=u.id WHERE u.id=?');$q->execute([$id]);$profile=$q->fetch(PDO::FETCH_ASSOC)?:[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!validCsrf()){flash('error','Your form expired.');go('/profile');}
    if(($_POST['action']??'')==='cancel'){
        $applicationId=(int)($_POST['application_id']??0);
        $q=$pdo->prepare("SELECT p.id FROM candidacy c JOIN payment p ON p.id=c.payment_id WHERE c.id=? AND c.candidate_id=? AND c.status='Pending' AND p.status='Pending'");
        $q->execute([$applicationId,$id]); $paymentId=$q->fetchColumn();
        if ($paymentId === false) { flash('error','Only an unpaid pending application can be cancelled.'); go('/profile'); }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE payment SET status='Failed' WHERE id=? AND status='Pending'")->execute([(int)$paymentId]);
            $cancel=$pdo->prepare("UPDATE candidacy SET status='Cancelled' WHERE id=? AND candidate_id=? AND status='Pending'");
            $cancel->execute([$applicationId,$id]);
            $pdo->commit(); flash('success','Application cancelled.');
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error','Could not cancel the application.'); }
        go('/profile');
    }
    $q=$pdo->prepare('UPDATE candidate SET full_name=?,email=?,phone_number=? WHERE id=?');
    $q->execute([trim((string)$_POST['full_name']),trim((string)$_POST['email']),trim((string)$_POST['phone_number'])?:null,$id]);
    flash('success','My Profile updated.');go('/profile');
}
$q=$pdo->prepare('SELECT c.id,c.status,c.position_sought,c.manifesto,c.payment_id,e.title,p.status payment_status FROM candidacy c JOIN election e ON e.id=c.election_id LEFT JOIN payment p ON p.id=c.payment_id WHERE c.candidate_id=? ORDER BY c.registered_at DESC');$q->execute([$id]);$apps=$q->fetchAll(PDO::FETCH_ASSOC);$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Profile</title><link rel="stylesheet" href="/assets/app.css"></head><body><header><strong>VoteTrust</strong><span>Candidate Registration</span><a class="account" href="/profile">My Profile</a><form method="post" action="/logout"><input type="hidden" name="_token" value="<?=e(token())?>"><button>Sign out</button></form></header><main><?php if($flash):?><div class="notice <?=e($flash[0])?>"><?=e($flash[1])?></div><?php endif?><h1>My Profile</h1><section class="card"><h2>Personal details</h2><p>Account username: <strong><?=e((string)$profile['username'])?></strong></p><form method="post"><input type="hidden" name="_token" value="<?=e(token())?>"><label>Full name<input name="full_name" value="<?=e((string)($profile['full_name']??''))?>" required></label><label>Email address<input type="email" name="email" value="<?=e((string)($profile['email']??''))?>" required></label><label>Phone number<input name="phone_number" value="<?=e((string)($profile['phone_number']??''))?>"></label><button class="primary">Update Profile</button></form></section><section class="card"><h2>Election details</h2><?php foreach($apps as $app):?><article><h3><?=e($app['title'])?></h3><p><strong>Position:</strong> <?=e((string)($app['position_sought']?:'Not recorded'))?></p><p><strong>Manifesto:</strong> <?=e((string)($app['manifesto']?:'Not recorded'))?></p><p><strong>Payment:</strong> <?=e((string)($app['payment_status']?:'No payment record'))?></p><p><strong>Application status:</strong> <?=e($app['status'])?></p><?php if(($app['payment_status']??'')==='Pending'):?><a class="primary" href="/payment?payment_id=<?=(int)$app['payment_id']?>">Make Payment</a><?php endif?><?php if($app['status']==='Pending'&&($app['payment_status']??'')==='Pending'):?><form method="post"><input type="hidden" name="_token" value="<?=e(token())?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="application_id" value="<?=(int)$app['id']?>"><button type="submit">Cancel Application</button></form><?php endif?></article><?php endforeach?></section><p><a href="/">Back to application</a></p></main></body></html>
