<?php
define('WP_INSTALLING',true);
$_SERVER['HTTP_HOST']='127.0.0.1:8195';
$_SERVER['REQUEST_METHOD']='GET';
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
add_filter('pre_wp_mail',function(){return false;});
if(!is_blog_installed())wp_install('Sessioncraft','portfolio_admin','admin@example.invalid',false,'',getenv('PORTFOLIO_ADMIN_PASSWORD'));
update_option('siteurl','http://127.0.0.1:8195');update_option('home','http://127.0.0.1:8195');
update_option('permalink_structure','/%postname%/');update_option('blog_public',0);
$result=activate_plugin('sessioncraft-booking/sessioncraft-booking.php');if(is_wp_error($result)){fwrite(STDERR,$result->get_error_message());exit(1);}
switch_theme('sessioncraft');flush_rewrite_rules(true);
if(!get_option('sc_seeded')){
 global $wpdb;[$s,$b]=sc_tables();
 $sessions=[['Map your workflow, find your focus','Operations','Turn one tangled process into a clear map, with practical steps to remove the busiest bottleneck.',8,14,10],['Build your first useful automation','Automation','Connect the steps of a recurring task and leave with a simple, testable automation plan.',6,21,14],['Make your website easier to use','Web experience','Walk through the visitor journey and identify the changes that make the next step feel obvious.',6,28,10]];
 foreach($sessions as $item){$wpdb->insert($s,['title'=>$item[0],'category'=>$item[1],'description'=>$item[2],'capacity'=>$item[3],'starts_at'=>gmdate('Y-m-d',$item[4]*86400+time()).' '.str_pad($item[5],2,'0',STR_PAD_LEFT).':00:00','duration'=>90]);}
 update_option('sc_seeded',1);
}
echo 'WordPress '.get_bloginfo('version').' initialized: SessionCraft theme + booking plugin.'.PHP_EOL;
