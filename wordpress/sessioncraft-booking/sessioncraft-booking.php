<?php
// SPDX-License-Identifier: MIT
// Copyright (c) 2026 Ismail Habib
/**
 * Plugin Name: SessionCraft Booking
 * Description: Capacity-controlled workshops, waitlists and self-service cancellation.
 * Version: 1.0.0
 * Author: Ismail Habib
 * License: MIT
 * License URI: https://opensource.org/license/mit/
 */
if (!defined('ABSPATH')) exit;
function sc_tables(){global $wpdb;return [$wpdb->prefix.'sc_sessions',$wpdb->prefix.'sc_bookings'];}
function sc_install(){
 global $wpdb;[$s,$b]=sc_tables();$c=$wpdb->get_charset_collate();require_once ABSPATH.'wp-admin/includes/upgrade.php';
 dbDelta("CREATE TABLE $s (id bigint unsigned NOT NULL AUTO_INCREMENT, title varchar(180) NOT NULL, starts_at datetime NOT NULL, duration int NOT NULL DEFAULT 90, capacity int NOT NULL DEFAULT 8, category varchar(60) NOT NULL, description text NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB $c;");
 dbDelta("CREATE TABLE $b (id bigint unsigned NOT NULL AUTO_INCREMENT, session_id bigint unsigned NOT NULL, name varchar(120) NOT NULL, email varchar(190) NOT NULL, status varchar(20) NOT NULL, token_hash varchar(64) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY session_email (session_id,email), KEY queue (session_id,status,id)) ENGINE=InnoDB $c;");
}
register_activation_hook(__FILE__,'sc_install');
function sc_status($text){return '<span class="status '.esc_attr($text).'">'.esc_html(ucfirst($text)).'</span>';}
function sc_fail($text,$status=400){wp_die(esc_html($text),'SessionCraft',['response'=>$status,'back_link'=>true]);}
function sc_session($id){global $wpdb;[$s]=sc_tables();return $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d",$id));}
function sc_redirect($args=[]){wp_safe_redirect(add_query_arg($args,home_url('/')));exit;}
function sc_nonce($action){if(!isset($_POST['_wpnonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),$action))sc_fail('This form expired. Refresh the page and try again.',403);}
function sc_book(){
 sc_nonce('sc_book');global $wpdb;[$s,$b]=sc_tables();$id=absint($_POST['session_id']??0);$name=sanitize_text_field(wp_unslash($_POST['name']??''));$email=sanitize_email(wp_unslash($_POST['email']??''));
 if(strlen($name)<2||strlen($name)>120||!is_email($email)||strlen($email)>190||empty($_POST['consent']))sc_fail('Enter your name, a valid email address and agree to local reservation storage.');
 $wpdb->query('START TRANSACTION');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d FOR UPDATE",$id));
 if(!$row||strtotime($row->starts_at.' UTC')<=time()){ $wpdb->query('ROLLBACK');sc_fail('This session is no longer open for reservations.'); }
 $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE session_id=%d AND email=%s",$id,$email));
 if($existing&&$existing->status!=='cancelled'){$wpdb->query('ROLLBACK');sc_fail('This email already has a reservation for the session. Use the cancellation link from your original confirmation.',409);}
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE session_id=%d AND status='confirmed'",$id));$status=$count<$row->capacity?'confirmed':'waitlisted';$token=bin2hex(random_bytes(24));
 $data=['session_id'=>$id,'name'=>$name,'email'=>$email,'status'=>$status,'token_hash'=>hash('sha256',$token),'created_at'=>current_time('mysql',true)];
 // Delete a cancelled record so a new reservation joins the end of the queue.
 if($existing)$wpdb->delete($b,['id'=>$existing->id]);
 $ok=$wpdb->insert($b,$data);$booking=$wpdb->insert_id;
 if(!$ok){$wpdb->query('ROLLBACK');sc_fail('The reservation could not be saved. Please try again.',500);}
 $wpdb->query('COMMIT');sc_redirect(['reservation'=>$booking,'key'=>$token]);
}
add_action('admin_post_sc_book','sc_book');add_action('admin_post_nopriv_sc_book','sc_book');
function sc_cancel(){
 sc_nonce('sc_cancel');global $wpdb;[$s,$b]=sc_tables();$id=absint($_POST['booking_id']??0);$key=sanitize_text_field(wp_unslash($_POST['key']??''));
 $initial=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d",$id));if(!$initial||!hash_equals($initial->token_hash,hash('sha256',$key)))sc_fail('Invalid reservation link.',403);
 $wpdb->query('START TRANSACTION');$wpdb->get_row($wpdb->prepare("SELECT id FROM $s WHERE id=%d FOR UPDATE",$initial->session_id));$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d FOR UPDATE",$id));
 if($row->status!=='cancelled'){
  $wpdb->update($b,['status'=>'cancelled'],['id'=>$id]);
  if($row->status==='confirmed'){$next=$wpdb->get_var($wpdb->prepare("SELECT id FROM $b WHERE session_id=%d AND status='waitlisted' ORDER BY id LIMIT 1",$row->session_id));if($next)$wpdb->update($b,['status'=>'confirmed'],['id'=>$next]);}
 }
 $wpdb->query('COMMIT');sc_redirect(['reservation'=>$id,'key'=>$key]);
}
add_action('admin_post_sc_cancel','sc_cancel');add_action('admin_post_nopriv_sc_cancel','sc_cancel');
function sc_schedule(){
 if(!current_user_can('manage_options'))sc_fail('Staff access required.',403);sc_nonce('sc_schedule');global $wpdb;[$s,$b]=sc_tables();
 $id=absint($_POST['session_id']??0);$capacity=absint($_POST['capacity']??0);$duration=absint($_POST['duration']??0);$title=sanitize_text_field(wp_unslash($_POST['title']??''));$date=sanitize_text_field($_POST['starts_at']??'');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new DateTimeZone('UTC'));
 if(!$parsed||$parsed->format('Y-m-d\TH:i')!==$date||strlen($title)<3||strlen($title)>180||$capacity<1||$capacity>100||$duration<15||$duration>480)sc_fail('Check the title, UTC date, capacity (1–100) and duration (15–480 minutes).');
 $wpdb->query('START TRANSACTION');
 if($id){$found=$wpdb->get_row($wpdb->prepare("SELECT id FROM $s WHERE id=%d FOR UPDATE",$id));if(!$found){$wpdb->query('ROLLBACK');sc_fail('Unknown session.',404);}}
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE session_id=%d AND status='confirmed'",$id));if($count>$capacity){$wpdb->query('ROLLBACK');sc_fail('Capacity cannot be below the number of confirmed reservations.',409);}
 $data=['title'=>$title,'starts_at'=>$parsed->format('Y-m-d H:i:s'),'duration'=>$duration,'capacity'=>$capacity,'category'=>sanitize_text_field(wp_unslash($_POST['category']??'Workshop')),'description'=>sanitize_textarea_field(wp_unslash($_POST['description']??''))];
 $ok=$id?$wpdb->update($s,$data,['id'=>$id]):$wpdb->insert($s,$data);if($ok===false){$wpdb->query('ROLLBACK');sc_fail('Save failed.',500);}
 if($id){$space=$capacity-$count;$queue=$wpdb->get_col($wpdb->prepare("SELECT id FROM $b WHERE session_id=%d AND status='waitlisted' ORDER BY id LIMIT %d",$id,$space));foreach($queue as $next)$wpdb->update($b,['status'=>'confirmed'],['id'=>$next]);}
 $wpdb->query('COMMIT');wp_safe_redirect(admin_url('admin.php?page=sessioncraft&saved=1'));exit;
}
add_action('admin_post_sc_schedule','sc_schedule');
add_action('admin_menu',function(){add_menu_page('SessionCraft','SessionCraft','manage_options','sessioncraft','sc_admin','dashicons-calendar-alt',25);});
function sc_admin(){
 if(!current_user_can('manage_options'))sc_fail('Staff access required.',403);global $wpdb;[$s,$b]=sc_tables();$rows=$wpdb->get_results("SELECT * FROM $s ORDER BY starts_at");
 echo '<div class="wrap"><h1>SessionCraft · Schedule & reservations</h1><p>Times are stored and displayed in UTC. Reservations are local only; no payment or email is sent.</p>';
 foreach(array_merge($rows,[null]) as $row){echo '<div style="background:#fff;padding:20px;margin:18px 0;max-width:1000px"><h2>'.esc_html($row?$row->title:'Create a workshop').'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sc_schedule"><input type="hidden" name="session_id" value="'.esc_attr($row->id??0).'">';wp_nonce_field('sc_schedule');
 foreach(['title'=>'Title','starts_at'=>'Starts (UTC)','duration'=>'Minutes','capacity'=>'Capacity','category'=>'Category'] as $field=>$label){$type=$field==='starts_at'?'datetime-local':(in_array($field,['duration','capacity'])?'number':'text');$value=$row->{$field}??($field==='duration'?90:($field==='capacity'?8:''));if($field==='starts_at'&&$row)$value=str_replace(' ','T',substr($value,0,16));echo '<label style="display:inline-block;margin:8px">'.esc_html($label).'<br><input required type="'.esc_attr($type).'" name="'.esc_attr($field).'" value="'.esc_attr($value).'"></label>';}
 echo '<p><label>Description<br><textarea name="description" rows="3" style="width:95%">'.esc_textarea($row->description??'').'</textarea></label></p><button class="button button-primary">Save workshop</button></form>';
 if($row){$bookings=$wpdb->get_results($wpdb->prepare("SELECT * FROM $b WHERE session_id=%d ORDER BY id",$row->id));echo '<h3>Reservations</h3><table class="widefat"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Created (UTC)</th></tr></thead><tbody>';foreach($bookings as $booking)echo '<tr><td>'.esc_html($booking->name).'</td><td>'.esc_html($booking->email).'</td><td>'.esc_html($booking->status).'</td><td>'.esc_html($booking->created_at).'</td></tr>';if(!$bookings)echo '<tr><td colspan="4">No reservations yet.</td></tr>';echo '</tbody></table>';}
 if($row){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:16px"><input type="hidden" name="action" value="sc_delete"><input type="hidden" name="session_id" value="'.esc_attr($row->id).'">';wp_nonce_field('sc_delete');echo '<button class="button">Delete workshop and reservation data</button></form>';}echo '</div>';}
 echo '</div>';
}
function sc_render(){
 global $wpdb;[$s,$b]=sc_tables();ob_start();
 if(isset($_GET['reservation'],$_GET['key'])){
 $row=$wpdb->get_row($wpdb->prepare("SELECT b.*,s.title,s.starts_at FROM $b b JOIN $s s ON b.session_id=s.id WHERE b.id=%d",absint($_GET['reservation'])));$key=sanitize_text_field(wp_unslash($_GET['key']));
 if(!$row||!hash_equals($row->token_hash,hash('sha256',$key))){echo '<div class="notice">This reservation link is invalid.</div>';}
 else{echo '<section class="confirmation" id="reservation"><div class="eyebrow">YOUR RESERVATION</div><h2>'.($row->status==='confirmed'?'Your seat is saved.':($row->status==='waitlisted'?"You’re on the waiting list.":'Your reservation is cancelled.')).'</h2><p>'.esc_html($row->title).' · '.esc_html(gmdate('j F Y · H:i',strtotime($row->starts_at))).' UTC</p>'.sc_status($row->status).'<p>Keep this private page link to check your status or cancel. No confirmation email is sent. Waiting-list places are promoted automatically in order when a seat opens.</p>';
 if($row->status!=='cancelled'){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sc_cancel"><input type="hidden" name="booking_id" value="'.esc_attr($row->id).'"><input type="hidden" name="key" value="'.esc_attr($key).'">';wp_nonce_field('sc_cancel');echo '<button class="text-button">Cancel reservation</button></form>';}
 echo '</section>';}
 }
 $rows=$wpdb->get_results($wpdb->prepare("SELECT s.*, (SELECT COUNT(*) FROM $b b WHERE b.session_id=s.id AND status='confirmed') AS booked FROM $s s WHERE starts_at>%s ORDER BY starts_at",gmdate('Y-m-d H:i:s')));
 echo '<div class="section-heading" id="sessions"><div><div class="eyebrow">THE UPCOMING SERIES</div><h2>Make room for better work.</h2></div><p>Small groups. Practical sessions.<br>One useful outcome to take away.</p></div><div class="workshops">';
 foreach($rows as $i=>$row){$left=max(0,$row->capacity-$row->booked);echo '<article class="workshop"><div class="workshop-top"><span class="category">'.esc_html($row->category).'</span><span class="number">0'.($i+1).'</span></div><div class="date">'.esc_html(gmdate('D, j M',strtotime($row->starts_at))).'<span>'.esc_html(gmdate('H:i',strtotime($row->starts_at))).' UTC · '.esc_html($row->duration).' MIN</span></div><h3>'.esc_html($row->title).'</h3><p>'.esc_html($row->description).'</p><div class="availability">'.($left?'<i></i> '.$left.' of '.$row->capacity.' seats available':'<i class="full"></i> Fully booked · waiting list open').'</div><details><summary>'.($left?'Reserve your seat':'Join the waiting list').' <span>↗</span></summary><form class="booking-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sc_book"><input type="hidden" name="session_id" value="'.esc_attr($row->id).'">';wp_nonce_field('sc_book');echo '<label>Your name<input required name="name" maxlength="120" autocomplete="name"></label><label>Email address<input required type="email" name="email" maxlength="190" autocomplete="email"></label><label class="checkbox"><input required type="checkbox" name="consent" value="yes"> Store my reservation details locally for this session.</label><button>'.($left?'Confirm reservation':'Join waiting list').'</button><small>No payment. No email. Save the private confirmation link.</small></form></details></article>';}
 if(!$rows)echo '<p>New workshops will be announced here.</p>';echo '</div>';return ob_get_clean();
}
add_shortcode('sessioncraft','sc_render');
add_action('send_headers',function(){if(isset($_GET['key'])){header('Referrer-Policy: no-referrer');header('Cache-Control: no-store, private');}});
function sc_delete(){
 if(!current_user_can('manage_options'))sc_fail('Staff access required.',403);sc_nonce('sc_delete');global $wpdb;[$s,$b]=sc_tables();$id=absint($_POST['session_id']??0);$wpdb->query('START TRANSACTION');$wpdb->get_row($wpdb->prepare("SELECT id FROM $s WHERE id=%d FOR UPDATE",$id));$wpdb->delete($b,['session_id'=>$id]);$wpdb->delete($s,['id'=>$id]);$wpdb->query('COMMIT');wp_safe_redirect(admin_url('admin.php?page=sessioncraft'));exit;
}
add_action('admin_post_sc_delete','sc_delete');add_action('admin_post_nopriv_sc_delete','sc_delete');
