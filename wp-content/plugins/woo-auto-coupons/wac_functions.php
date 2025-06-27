<?php
/*
Plugin Name: Auto Coupons for WooCommerce
Plugin URI: https://richardlerma.com/plugins/
Description: Apply WooCommerce Coupons automatically with a simple, fast and lightweight plugin.
Author: RLDD
Author URI: https://richardlerma.com/contact/
Requires Plugins: woocommerce
Version: 3.0.32
Text Domain: woo-auto-coupons
Copyright: (c) 2019-2025 rldd.net - All Rights Reserved
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 9.0
WC tested up to: 9.9
*/

global $wp_version,$wac_version,$wac_pro_version,$wac_version_type; $wac_version='3.0.32';
$wac_version_type='GPL';
$wac_pro_version=get_option('wac_pro_version');
if(function_exists('wac_pro_activate')) $wac_version_type='PRO';
if(!defined('ABSPATH')) exit;

function wac_error() {file_put_contents(dirname(__file__).'/install_log.txt', ob_get_contents());}
if(defined('WP_DEBUG') && true===WP_DEBUG) add_action('activated_plugin','wac_error');

function wac_activate($upgrade) {
  global $wpdb,$wac_version;
  require_once(ABSPATH.basename(get_admin_url()).'/includes/upgrade.php');
  update_option('wac_db_version',$wac_version,'no');
  if(function_exists('wac_pro_ping'))wac_pro_ping();
}
register_activation_hook(__FILE__,'wac_activate');

function wac_add_action_links($links) {
  $settings_url=get_admin_url(null,'admin.php?page=woo-auto-coupons');
  $support_url='https://richardlerma.com/plugins/';
  $links[]='<a href="'.$support_url.'">Support</a>';
  array_push($links,'<a href="'.$settings_url.'">Settings</a>');
  return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__),'wac_add_action_links');

function wac_uninstall() {
  $uninstall=get_option('wac_uninstall');
  if($uninstall=='delete') {wac_r("DELETE FROM wp_options WHERE option_name LIKE 'wac_%';");}
}
register_uninstall_hook(__FILE__,'wac_uninstall');

// Run Query
function wac_r($q,$t=NULL) {
  global $wp_version;
  include_once(ABSPATH .'wp-includes/pluggable.php'); // If called prior to pluggable loaded natively
  if(version_compare('6.1',$wp_version)>0) require_once(ABSPATH.'wp-includes/wp-db.php');
  else require_once(ABSPATH.'wp-includes/class-wpdb.php');
  
  global $wpdb;
  if(!$wpdb) $wpdb=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
  $prf=$wpdb->prefix;
  $s=str_replace(' wp_',' '.$prf,$q);
  $s=str_replace($prf.str_replace('wp_','',$prf),$prf,$s);
  if(strpos($s,'DELETE')!==false || strpos($s,'INSERT')!==false) $r=$wpdb->query($s); else $r=$wpdb->get_results($s,OBJECT);
  if($t) {echo $wpdb->last_error."<br>";echo $s;}
  if($r) return $r;
}

add_action('before_woocommerce_init',function() {
	if(class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class )) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);
	}
});

// Strict Comparison
function wac_in ($n,$h) {
  if(!is_array($h)) $h=explode(',',$h);
  if(in_array($n,$h,true)!==false) return true;return false;
}

// Loose Comparison (regardless of vars types)
function wac_in_like($n,$h) {
  if(!is_array($h)) $h=explode(',',$h);
  foreach($h as $item) {
    if(!empty($item)) if(stripos($n,$item)!==false) return true;
  }
  return false;
}

function wac_unsr($d) {
  if(!empty($d)) {
    if(is_array($d)) return $d;
    if(strpos($d,':')!== false) return unserialize($d);
    if(strpos($d,',')!==false) return explode(',',$d);
    return array($d);
  }
  else return '';
}

// Path Comparison
function wac_is_path($pages) {
  if($pages=='cart') {
    if(function_exists('is_cart')) if(is_cart()) return true;
    if(function_exists('is_checkout')) if(is_checkout()) return true;
    $check_cart=get_option('wac_cart_page'); if(!empty($check_cart)) $pages=$check_cart;
    else {$check_cart=get_option('wac_checkout_page'); if(!empty($check_cart)) $pages=$check_cart;}
  }
  $page=strtolower($_SERVER['REQUEST_URI']);
  return wac_in_like($page,$pages);
}

function wac_adminMenu() {
  add_submenu_page('woocommerce','Auto Coupons','Auto Coupons','manage_options','woo-auto-coupons','wac_admin');

  function wac_admin() {
    global $wp_version,$wac_version,$wac_pro_version,$wac_version_type;
    $bulk_apply_future=$bulk_url_future=$bulk_apply_active=$dup_post_type='';
    $dup_post=0;
    $get_version=wac_r("SELECT @@version as version;");
    if($get_version) foreach($get_version as $row):$mysql_version=$row->version;endforeach;

    if(isset($_POST['wac_uninstall']) && check_admin_referer('config_wac','wac_config')) {
      if(isset($_POST['bulk_apply_future'])) $bulk_apply_future=sanitize_text_field($_POST['bulk_apply_future']); update_option('wac_bulk_apply_future',$bulk_apply_future);
      if(isset($_POST['bulk_apply_active'])) $bulk_apply_active=sanitize_text_field($_POST['bulk_apply_active']);
      if(isset($_POST['bulk_url_future'])) $bulk_url_future=sanitize_text_field($_POST['bulk_url_future']); update_option('wac_bulk_url_future',$bulk_url_future);
      if(isset($_POST['bulk_url_active'])) $bulk_url_active=sanitize_text_field($_POST['bulk_url_active']);
      if(isset($_POST['dup_post_type'])) $dup_post_type=sanitize_text_field($_POST['dup_post_type']);
      if(isset($_POST['dup_post'])) $dup_post=intval($_POST['dup_post']);
      $coupon_email=intval($_POST['coupon_email']); update_option('wac_coupon_email',$coupon_email);
      $email_prompt=sanitize_text_field($_POST['email_prompt']); update_option('wac_email_prompt',$email_prompt);
      $email_err=sanitize_text_field($_POST['email_err']); update_option('wac_email_err',$email_err);
      $email_dismiss=sanitize_text_field($_POST['email_dismiss']); update_option('wac_email_dismiss',$email_dismiss);
      $min_qty_ntf=sanitize_text_field($_POST['min_qty_ntf']); update_option('wac_min_qty_ntf',$min_qty_ntf);
      $max_qty_ntf=sanitize_text_field($_POST['max_qty_ntf']); update_option('wac_max_qty_ntf',$max_qty_ntf);
      $uninstall=sanitize_text_field($_POST['wac_uninstall']); update_option('wac_uninstall',$uninstall);
    } else {
      $bulk_apply_future=get_option('wac_bulk_apply_future');
      $bulk_url_future=get_option('wac_bulk_url_future');
      $coupon_email=get_option('wac_coupon_email');
      $email_prompt=get_option('wac_email_prompt');
      $email_err=get_option('wac_email_err');
      $email_dismiss=get_option('wac_email_dismiss');
      $min_qty_ntf=get_option('wac_min_qty_ntf');
      $max_qty_ntf=get_option('wac_max_qty_ntf');
      $uninstall=get_option('wac_uninstall');
    }

    if(function_exists('wac_bulk_apply') && version_compare($wac_pro_version,'1.1','>=')>0) {
      if($bulk_apply_future=='on') wac_bulk_apply(2);
      if($bulk_url_future=='on') wac_bulk_apply(2,'url');
    }
    $install_alert='';
    if(!in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins'))) && !function_exists('wac_get_email'))
      $install_alert.="
      <div class='wac_alert'>
        WooCommerce is required for the Auto Coupons plugin to work. <a href='https://wordpress.org/plugins/woocommerce/' target='_blank'>Download for free</a> or <a href='plugins.php' target='_blank'>Activate</a>.
      </div><br>";

    if(empty($install_alert) && !function_exists('wac_get_email') && !isset($_REQUEST['tab']) && $wac_version!==get_option('wac_dismiss_upgrade')) {
      if(isset($_GET['wac_dismiss_upgrade'])) update_option('wac_dismiss_upgrade',$wac_version,'no');
      else $install_alert.="
      <div style='margin:3em'>&nbsp;</div>
      <div style='position:absolute;margin:-4em 0 0 0.5em;background:#0071a2;font-weight:500;font-size:1.3em;color:#fff;padding:1em;border-radius:5px'>
        New Features Available <a href='?page=woo-auto-coupons&wac_dismiss_upgrade' style='font-size:.8em;color:#ffffff7a;margin-left:1em'>dismiss</a>
        <span class='dashicons dashicons-arrow-down' style='position:absolute;bottom:-0.55em;zoom:2;right:15%;color:#0071a2;'></span>
      </div>";
    }

    $now=current_time('mysql');
    $coupons=wac_r("
      SELECT *
        FROM (
        SELECT p.ID post_id,post_title coupon_code
        ,MAX(CASE WHEN pm.meta_key='individual_use' THEN pm.meta_value END)individual
        ,MAX(CASE WHEN pm.meta_key='coupon_amount' THEN pm.meta_value END)coupon_amount
        ,MAX(CASE WHEN pm.meta_key='customer_email' THEN pm.meta_value END)c_email
        ,MAX(CASE WHEN pm.meta_key='_wc_min_qty' THEN pm.meta_value END)min_qty
        ,MAX(CASE WHEN pm.meta_key='_wc_max_qty' THEN pm.meta_value END)max_qty
        ,MAX(CASE WHEN pm.meta_key='_wc_min_qty_ntf' THEN pm.meta_value END)min_qty_ntf
        ,MAX(CASE WHEN pm.meta_key='_wc_max_qty_ntf' THEN pm.meta_value END)max_qty_ntf
        ,MAX(CASE WHEN pm.meta_key='_wc_auto_apply' AND pm.meta_value='yes' THEN pm.meta_value END)auto_apply
        ,MAX(CASE WHEN pm.meta_key='_wc_url_apply' AND pm.meta_value='yes' THEN pm.meta_value END)url_apply
        ,DATE_FORMAT(FROM_UNIXTIME(x.meta_value),'%m-%d-%Y')exp_date
        ,CASE WHEN FROM_UNIXTIME(x.meta_value)<'$now' THEN 1 ELSE 0 END exp
        FROM wp_posts p
        LEFT JOIN wp_postmeta x ON x.post_id=p.ID AND x.meta_key='date_expires' AND LENGTH(x.meta_value)=10 AND x.meta_value REGEXP '[0-9]'
        LEFT JOIN wp_postmeta pm ON pm.post_id=p.ID
        WHERE post_type='shop_coupon'
        AND post_status='publish'
        GROUP BY p.ID
      )a
      WHERE exp=0
      ORDER BY coupon_code;");

      function wac_ct_coupons($coupons,$type) {
        $ct=0;
        if($coupons) foreach($coupons as $c) {
          if($type=='auto' && $c->auto_apply!=='yes') continue;
          if($type=='url' && $c->url_apply!=='yes') continue;
          $ct++;
        }
        return $ct;
      }

      function wac_list_coupons($coupons,$type) {
        $coupon_codes='';
        if($coupons) foreach($coupons as $c) {
          if($type=='auto' && $c->auto_apply!=='yes') continue;
          if($type=='url' && $c->url_apply!=='yes') continue;
          $coupon_codes.="<a href='post.php?post={$c->post_id}&action=edit' target='_blank' title='{$c->coupon_amount}'><div>".ucwords($c->coupon_code).'</div></a>';
        }
        if(empty($coupon_codes)) return 'none'; else return $coupon_codes;
      }
      if(isset($_POST['wac_reset_cache']) && intval($_POST['wac_reset_cache'])>0) wac_clear_cache();
      ?>
      <div class='wrap'>
        <div style='margin-bottom:1em'>
          <img style='width:4em;filter:brightness(0.94);' src='https://ps.w.org/woo-auto-coupons/assets/icon-256x256.png?n=1'>
          <h2 style='display:inline-block;vertical-align:top;letter-spacing:.2em;font-variant-caps:all-petite-caps;color:#0071b2;'>&nbsp;Auto Coupons for WooCommerce</h2>
        </div>
        <?php echo $install_alert;?>

        <script>
          function wac_getE(e) {return document.getElementById(e);}

          function wac_tab(t) {
            wac_getE('wac_tab').value=t;
            var tre=document.querySelectorAll('#wac_admin tr');
            tre.forEach(tr=>{
              var sel=tr.classList.contains(t);
              if(tr.classList.contains('form_save')) if(t=='pro') sel=false; else sel=true;
              tr.style.display=sel?'table-row':'none';
            });
            var navItems=document.getElementsByClassName('nav-tab');
            for(var i=0; i<navItems.length; i++) {
              var navItem=navItems[i];
              var isT=navItem.onclick.toString().includes("'"+t+"'");
              if(isT) navItem.classList.add('nav_sel'); else navItem.classList.remove('nav_sel');
            }
          }
        </script>

        <div id='wac_tabs' style='display:flex'>
          <a href='#!' class='nav-tab' onclick="wac_tab('status')">Status</a>
          <a href='#!' class='nav-tab' onclick="wac_tab('options')"><?php if(function_exists('wac_get_email')) echo 'Options'; else echo 'PRO Options';?></a>
          <a href='#!' class='nav-tab' onclick="wac_tab('pro')"><?php if(function_exists('wac_get_email')) echo 'PRO Support'; else echo 'Upgrade';?></a>
        </div>
        <form name='wac_admin' id='wac_admin' method='post' action='?page=woo-auto-coupons' onchange="unsaved_changes=true;" onsubmit="unsaved_changes=false;">
          <table id='wac_admin' style='background:#fff;border:1px solid #ddd;padding:1em;width:-webkit-fill-available;max-width:90em'>

            <tr class='status'>
              <td nowrap style='font-size:1.2em;font-weight:500'>Coupon Settings</span></td>
              <td class='items'>
                <div>Auto Coupon settings are managed on the individual coupon level.<br><br>Visit <a href='edit.php?post_type=shop_coupon' style='font-weight:500'>Marketing > Coupons</a>, and choose a coupon.</div></td>
            </tr>
            <tr class='status'>
              <td nowrap>Troubleshoot</span></td>
              <td class='items'>
                <div style='background:#fff'>
                  <input type='hidden' name='wac_reset_cache' value=0>
                  <a class='button' style='display:block;width:fit-content' href='#!' onclick="previousElementSibling.value=1;document.getElementById('wac_admin').submit();">Reset Coupon Cache</a>
                  <br>Reset the coupon cache to reset dismissed notifications or coupons removed from cart.
                </div>
                <div>
                  <a class='button' style='display:block;width:fit-content' href='<?php if(function_exists('wc_get_cart_url')) echo wc_get_cart_url();?>?wac_trb' target='_blank'>Troubleshoot</a>
                  <br>This will display all coupons to admins and the applied status.
                  <br>Add a product to your cart first.
                </div>
              </td>
            </tr>

            <tr class='status'>
              <td nowrap>Active Auto-Apply (<?php echo wac_ct_coupons($coupons,'auto');?>)</span></td>
              <td class='items'><div class='cpn'><?php echo wac_list_coupons($coupons,'auto');?></div></td>
            </tr>

            <tr class='status'>
              <td nowrap>Active Apply via URL (<?php echo wac_ct_coupons($coupons,'url');?>)</span></td>
              <td class='items'><div class='cpn'><?php echo wac_list_coupons($coupons,'url');?></div></td>
            </tr>

            <tr class='status'>
              <td>Uninstall</td>
              <td>
                <select name='wac_uninstall'>
                  <option value='' selected disabled>Uninstall Preference
                  <option value='' <?php if($uninstall=='') echo 'selected';?>>Keep all settings
                  <option value='delete' <?php if($uninstall=='delete') echo 'selected';?>>Delete all settings
                </select>
              </td>
            </tr>

            <tr class='options'>
              <td>Bulk Updates</td>
              <td class='items'>
                <div>
                  <input type='checkbox' <?php if(!empty($bulk_apply_future)) echo 'checked';?> name='bulk_apply_future' onchange="if(this.checked) alert('All coupons created in the future will default to Auto-Apply.');">&nbsp; Default all coupons created in the <b>future</b> to <b>Auto Apply</b>.<br>
                  <input type='checkbox' name='bulk_apply_active' onchange="if(this.checked) alert('ALL active coupons will be updated to Auto-Apply upon saving this form.');">&nbsp; Update all <b>currently active</b> coupons to <b>Auto Apply</b>.<br>
                  <?php if(!empty($bulk_apply_active) && function_exists('wac_bulk_apply')) echo wac_bulk_apply();?>
                </div>
                
                <div>
                  <input type='checkbox' <?php if(!empty($bulk_url_future)) echo 'checked';?> name='bulk_url_future' onchange="if(this.checked) alert('All coupons created in the future will default to Apply via URL.');">&nbsp; Default all coupons created in the <b>future</b> to <b>Apply via URL</b>.<br>
                  <input type='checkbox' name='bulk_url_active' onchange="if(this.checked) alert('ALL active coupons will be updated to Apply via URL upon saving this form.');">&nbsp; Update all <b>currently active</b> coupons to <b>Apply via URL</b>.<br>
                  <?php if(!empty($bulk_url_active) && function_exists('wac_bulk_apply')) echo wac_bulk_apply(0,'url');?>
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td style='vertical-align:top'>Duplicator
                <select multiple name='dup_post_type' id='dup_post_type' onchange="wac_filter_post_type()" style='float:right;padding:0;overflow:hidden'>
                  <option value='Shop Coupon' <?php if(empty($dup_post_type) || $dup_post_type=='Shop Coupon') echo 'selected'; ?>>Coupon
                  <option value='Product' <?php if($dup_post_type=='Product') echo 'selected'; ?>>Product
                  <option value='Page' <?php if($dup_post_type=='Page') echo 'selected'; ?>>Page
                  <option value='Post' <?php if($dup_post_type=='Post') echo 'selected'; ?>>Post
                </select>
                <style>#dup_post_type option{padding:.5em 1em}#dup_post_type option:checked{background:#2271b1;color:#fff}</style>
              </td>
              <td class='items' style='vertical-align:top'>
                <select name='dup_post' id='dup_post' onchange="if(this.value>0) wac_confirm_dup(this.options[this.selectedIndex].text);">
                  <option value=0>
                  <?php if(function_exists('wac_sel_post')) echo wac_sel_post();?>
                </select> &nbsp;
                <input type='submit' class='button' style='' value='Duplicate' onclick="unsaved_changes=false;">
                <?php if($dup_post>0 && function_exists('wac_dup_post')) echo wac_dup_post($dup_post);?>
              </td>
            </tr>

            <tr class='options'>
              <td>Email Restrictions</td>
              <td class='items'>
                <div>
                  <span title='Without this option, WooCommerce will not verify email restrictions on a coupon until the last step of checkout.'><span class="dashicons dashicons-editor-help"></span> Prompt for customer email when an auto-applied coupon with email restrictions is found.</span><br>
                  <select name='coupon_email'>
                    <option value=0 <?php if($coupon_email<1) echo 'selected';?>>Prompt Off
                    <option value=1 <?php if($coupon_email==1) echo 'selected';?>>Prompt On
                  </select><br><br>
                  Customer Email Prompt<br>
                  <input type='text' style='width:100%' name='email_prompt' placeholder='Verify your email address to check for eligible promotions.' value='<?php echo $email_prompt;?>'><br><br>
                  Failed Verification<br>
                  <input type='text' style='width:100%' name='email_err' placeholder='Email is not associated with any promotions. Try again?' value='<?php echo $email_err;?>'><br><br>
                  Dismiss Prompt<br>
                  <input type='text' style='width:100%' name='email_dismiss' placeholder='Are you sure you want to dismiss?' value='<?php echo $email_dismiss;?>'>
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td>Default Notifications</td>
              <td class='items'>
                <div style='background:#fff'>
                  <span title='These are defaults. Notifications are only active when enabled on the coupon level.'><span class="dashicons dashicons-editor-help"></span> When quantity in cart is <b>less</b> than min quantity of coupon.</span>
                  <div style='background:#eee;margin:1em 0;width:fit-content'>
                    &#8226; <b>Variables</b>: {Product} {Min Qty} {Qty Diff}<br>
                    &#8226; <b>Default</b>: Add {Qty Diff} more {Product} to qualify for a discount.
                  </div>
                  <input type='text' style='width:100%' name='min_qty_ntf' placeholder='Add {Qty Diff} more {Product} to qualify for a discount.' value='<?php echo $min_qty_ntf;?>'>
                </div>
                <div>
                  <span title='These are defaults. Notifications are only active when enabled on the coupon level.'><span class="dashicons dashicons-editor-help"></span> When quantity in cart is <b>more</b> than max quantity of coupon.</span>
                  <div style='background:#eee;margin:1em 0;width:fit-content'>
                    &#8226; <b>Variables</b>: {Product} {Max Qty} {Qty Diff}<br>
                    &#8226; <b>Default</b>: Reduce {Product} quantity to {Max Qty} to qualify for a discount.
                  </div>
                  <input type='text' style='width:100%' name='max_qty_ntf' placeholder='Reduce {Product} quantity to {Max Qty} to qualify for a discount.' value='<?php echo $max_qty_ntf;?>'>
                </div>
              </td>
            </tr>

            <tr class='pro admin' style='background:aliceblue;font-size:1.1em'>
              <td nowrap style='color:#2271b1;vertical-align:text-top'><b>Auto Coupons PRO</b>
                <?php if(function_exists('wac_pro_activate')) {?>
                  <div style='margin:1em 0'>
                    <span class="dashicons dashicons-image-rotate" style='color:#2271b1'></span>
                    <a style='font-weight:normal;font-size:.9em' href='<?php echo get_admin_url(null,'admin.php?page=woo-auto-coupons&pro_update=1&tab=pro');?>'>Check for Updates</a>
                  </div>
                <?php } ?>
              </td>
              <td>

                <div style='float:right;margin:-1em -1em -1em 1em;padding:2em;background:#dddbdb4f'>
                  <a href='#!' style='width:100%;text-align:center;margin:.5em 0;' class='button wac_btn' onclick="wac_getE('wac_diag').style.display='block';">Diagnostics</a>
                  <a href='https://richardlerma.com/contact/?imsg=' target='_blank' style='width:100%;text-align:center;margin: 0.5em 0;' class='button wac_btn' onclick="this.href+=append_diag('wac_diag');">Contact Support</a>
                </div>
                <style>#wac_admin .wac_btn{background:#2271b1;color:#fff;background-blend-mode:lighten;background-image:linear-gradient(#00000038 0 0);}#wac_admin .wac_btn:hover{background-blend-mode:darken;}</style>
                <?php if(function_exists('wac_get_email')) { ?>
                  <div style='font-weight:bold;color:green'>Active
                    <br><a class='button wac_btn' style='background-color:green;border:seagreen;margin:1em 0;font-weight:normal;font-size:.9em' href='https://www.paypal.com/myaccount/autopay/' target='_blank'>Manage Subscription</a>
                  </div><?php 
                } ?>

                <div id='wac_diag' style='display:none;background:#fff;padding:1em;font-size:.9em'>
                  <b>Configuration</b><br>
                  Host <?php echo $_SERVER['HTTP_HOST'].'@'.$_SERVER['SERVER_ADDR']; ?><br>
                  Path <?php echo substr(plugin_dir_path( __FILE__ ),-34);?><br>
                  WP <?php echo $wp_version; if(is_multisite()) echo 'multi'; ?><br>
                  PHP <?php echo phpversion();?><br>
                  MYSQL <?php echo $mysql_version; if(!empty($config_mode)) echo $config_mode; ?><br>
                  Theme <?php $pt=wp_get_theme(get_template()); echo $pt->Name.' '.$pt->Version; $ct=wp_get_theme(); if($pt->Name!==$ct->Name) echo ', '.$ct->Name.' '.$ct->Version;?><br>
                  Auto Coupons <?php echo "$wac_version $wac_version_type $wac_pro_version"; ?><br>
                  <hr>
                  <b>Settings</b><br>
                  Auto count: <?php echo wac_ct_coupons($coupons,'auto');?><br>
                  URL count: <?php echo wac_ct_coupons($coupons,'url');?><br>
                  Auto Future: <?php echo $bulk_apply_future;?><br>
                  URL Future: <?php echo $bulk_url_future;?><br>
                  Prompt Type: <?php echo $coupon_email;?><br>
                  Email Prompt: <?php echo $email_prompt;?><br>
                  Email Error: <?php echo $email_err;?><br>
                  Email Dismiss: <?php echo $email_dismiss;?><br>
                  Min Default: <?php echo $min_qty_ntf;?><br>
                  Max Default: <?php echo $max_qty_ntf;?><br>
                  Uninstall: <?php if(empty($uninstall)) echo 'keep'; else echo $uninstall;?><br>
                </div>
      
                <?php if(!function_exists('wac_get_email')) { ?>
                <div style='background:#fff;padding:1em'>
                  <div style='color:#2271b1;font-size:1.1em;opacity:.7;margin-bottom:2em'>Features</div>
                  <ul style='list-style:disc;margin:2em'>
                    <li><b>Bulk Updates</b><br>Set existing or future coupons in bulk to auto-apply automatically.
                    <li><b>Copy Coupons & More</b><br>Easily duplicate coupons, products, posts and pages.
                    <li><b>Email Restrictions</b><br>Prompt for customer email in cart when a coupon with email restrictions is applied.
                    <li><b>Coupon Defaults</b><br>Set default verbiage for new coupons.
                    <li><b>Dedicated Support</b><br>Dedicated support by email within an average 4 hour response time.
                  </ul>
                  <a style='margin:1em 0 0;padding:.5em 10%;' class='button wac_btn' href="https://richardlerma.com/wac-terms" target='_blank'>Learn More</a>
                  <style>tr.options:not(.admin) input,tr.options:not(.admin) select{opacity:.5;pointer-events:none}.pro.admin li{margin-bottom:1em}</style>
                </div>
                <?php } ?>
              </td>
            </tr>

            <tr class='form_save' style='background:#fff'>
              <td colspan='2'>
                <a href='update-core.php' target='_blank' class='page-title-action button' style='margin-top:3em'>Check for Updates</a>
                <?php echo wp_nonce_field('config_wac','wac_config');?>
                <input type='hidden' id='wac_tab' name='tab'>
                <input type='submit' class='page-title-action' style='padding:1em 8em;float:right' value='Save' onclick='unsaved_changes=false;'>
              </td>
            </tr>

          </table>
        </form>
      </div>
      <style>
        .nav-tab{background:#e7e7eb}
        .nav-tab.nav_sel{background:#fff}
        #wac_admin td{padding:.5em 1em}
        #wac_admin i{color:#2271b1;font-size:.8em;font-family:sans-serif}
        .wac_alert{margin-top:1em;background:#fff;border:1px solid #ddd;padding:1em;border-left:5px solid #d82626}
        .dashicons{vertical-align:text-top;color:#207cb0;cursor:pointer}
        .dashicons-image-rotate,.dashicons-remove{color:#d82626}
        .dashicons-warning{color:orange}
        #wac_admin a{display:inline-block;cursor:pointer;text-decoration:none;outline:none;box-shadow:none}
        #wac_admin a:hover .dashicons{transform:scale(.9)}
        #wac_admin td{padding:1em}
        #wac_admin input,#wac_admin select{margin:.5em 0}
        #wac_admin select{vertical-align:inherit}
        #wac_admin input.short{width:100px}
        #wac_admin tr:nth-child(even){background:#f5f5f5}
        #wac_admin td.items div{padding:1em;border:1px solid #ccc;border-radius:5px}
        #wac_admin td.items div:nth-child(even){background:#fff}
        #wac_admin td div{-webkit-transition:all .5s;transition:all .5s}
        #wac_admin td div:not(:last-child){margin-bottom:1em}
        #wac_admin .wac_new{opacity:1}
        #wac_admin .wac_new select{background:#f3f5f6}
        #wac_admin td.items div.cpn div{display:inline-block;margin:.5em;padding:.5em;color:#2271b1;cursor:pointer;box-shadow:inset 0px 0px 2px 0px #ddd;background:#fafafa}
        #wac_admin td.items div.cpn div:hover{border:1px solid #2271b1;color:darkslategray}
        #wac_admin input[type='submit']:hover{background:#fff;color:#0071b2}
        #wac_admin input[type='submit']{color:#fff;background:#0071b2}
      </style>

      <script type='text/javascript'>
        wac_tab('<?php if(isset($_REQUEST['tab'])) echo sanitize_text_field($_REQUEST['tab']); else echo 'status';?>');
        var m_inc=1;
        var unsaved_changes=false;
        var usc_interval=setInterval(function() {
          if(document.readyState==='complete') {
            clearInterval(usc_interval);
            window.onbeforeunload=function(){return unsaved_changes ? 'If you leave this page you will lose unsaved changes.' : null;}
        }},100);

        function append_diag(diag) {
          var d=wac_getE(diag).innerHTML;
          d=d.replace(/  /g,'');
          d=d.replace(/(\r\n|\r|\n)/g,'%0A');
          d=d.replace(/<\/?[^>]+(>|$)/g,'');
          return 'Type your inquiry here%0A%0A%0ADiagnostics follow:%0A-------------%0A'+d;
        }

        function wac_filter_post_type() {
          var t=wac_getE('dup_post_type'); if(!t) return;
          var p=wac_getE('dup_post');
          var og=p.querySelectorAll('optgroup');
          p.options[0].textContent="Select a "+t.value;
          og.forEach(function(g) {
            if(t.value==g.label) g.style.display='block';
            else g.style.display='none';
          });
        }
        wac_filter_post_type();

        function wac_confirm_dup(sel){
          var pvr='';
          var t=wac_getE('dup_post_type');
          if(t.value=='Product') pvr='\n\nProduct variations (if applicable) will be copied, but will not be visible until the \'Product data type\' is toggled to \'Variable product\'.';
          alert(sel+' will be duplicated as a draft.'+pvr);
        }
      </script><?php
  }
}
add_action('admin_menu','wac_adminMenu');


// Apply WC Coupons
function wac_is_coupon_valid($coupon_code,$dsp_err=0) {
  $coupon=new \WC_Coupon($coupon_code);   
  $discounts=new \WC_Discounts(WC()->cart);
  $status=$discounts->is_coupon_valid($coupon);
  if(is_wp_error($status)) {if($dsp_err>0) return $status->get_error_message(); else return false;} else return true;
}

function wac_qty_in_cart($prd_ids='',$exc_prds=array(),$cats=array(),$exc_cats=array()) {
  global $woocommerce;
  $qty=0;
  if(!empty($prd_ids)) $prd_ids=",$prd_ids,";
	foreach($woocommerce->cart->get_cart() as $cart_item_keys=>$cart_item) {
    $prd_match=$cat_match=$cat_exc=0;
    if(empty($prd_ids)) $prd_match=1;
    if(empty($cats)) $cat_match=1;
    if(!empty($exc_prds)) if(in_array($cart_item['product_id'],$exc_prds)) continue;
    if($prd_match<1) if(wac_in_like(",{$cart_item['product_id']},",$prd_ids) || wac_in_like(",{$cart_item['variation_id']},",$prd_ids)) $prd_match=1;
    if(!empty($exc_cats) || !empty($cats)) {
      $prd_cats=wc_get_product_term_ids($cart_item['product_id'],'product_cat');
      if(!empty($exc_cats)) {foreach($exc_cats as $exc_cat) if(in_array($exc_cat,$prd_cats)) $cat_exc=1;} if($cat_exc>0) continue;
      if(!empty($cats)) foreach($cats as $cat) if(in_array($cat,$prd_cats)) $cat_match=1;
    }
    if($prd_match>0 && $cat_match>0) $qty=$qty+$cart_item['quantity'];
  }
  return $qty;
}

function wac_cart_button() {
  if(current_user_can('administrator') && wac_is_path('cart') && !wp_doing_ajax()) { ?>
    <script type='text/javascript'>
      function wac_trb_button() {
        if(!document.getElementById('wp-admin-bar-root-default')) return;
        if(!document.getElementById('wp-admin-bar-edit')) return;
        let admin_bar=document.getElementById('wp-admin-bar-root-default');
        let editElement=document.getElementById('wp-admin-bar-edit');
        if(editElement) {
          let clonedEditElement=editElement.cloneNode(true);
          let clonedAnchorElement=clonedEditElement.querySelector('a');
          if(clonedAnchorElement) {
            clonedAnchorElement.id='wp-admin-bar-wac';
            clonedAnchorElement.className='ab-item';
            clonedAnchorElement.href='?wac_trb';
            clonedAnchorElement.textContent='Auto Coupons Status';
          }
          admin_bar.appendChild(clonedEditElement);
        }
      }
      wac_trb_button();
    </script>
    <style>
      #wpadminbar #wp-admin-bar-wac.ab-item:before{content:'\f163';top:2px;}
      @media screen and (max-width:782px) {#wpadminbar #wp-admin-bar-wac.ab-item:before{display:block;text-indent:0;font:normal 32px/1 dashicons;speak:never;top:7px;width:52px;text-align:center;-webkit-font-smoothing:antialiased;}}
    </style><?php 
  }
}
add_action('wp_footer','wac_cart_button');


function wac_apply_coupons() {
  global $woocommerce,$coupon_codes,$wac_pro_version;
  if(!is_object($woocommerce)) return;
  if(!is_object($woocommerce->cart)) return;
  $cart=$woocommerce->cart;
  if(isset($_POST['wac_reset_cache']) && intval($_POST['wac_reset_cache'])>0) wac_clear_cache();
  $cart_items=$apply_indv=$disp_email_prompt=$email_match=0;
  $req=$req2=$coupon=$cart_email=$wac_email='';
  $meta='_wc_%_apply';
  $cart_qty=$woocommerce->cart->get_cart_contents_count();
  if(is_object($woocommerce->session) && $woocommerce->session->get('customer')) {$c=$woocommerce->session->get('customer'); if(isset($c['email'])) $cart_email=$c['email'];}
  $coupon_email=get_option('wac_coupon_email');
  if($coupon_email>0 && function_exists('wac_get_email')) $wac_email=wac_get_email();
  if(function_exists('wac_bulk_apply') && version_compare($wac_pro_version,'1.1','>=')>0) {
    if(get_option('wac_bulk_apply_future')=='on') $future=wac_bulk_apply(1);
    if(get_option('wac_bulk_url_future')=='on') $future=wac_bulk_apply(1,'url');
  }

  $email_prompt=get_option('wac_email_prompt'); if(empty($email_prompt)) $email_prompt='Verify your email address to check for eligible promotions.';
  $email_err=get_option('wac_email_err'); if(empty($email_err)) $email_err='Email is not associated with any promotions. Try again?';
  $email_dismiss=get_option('wac_email_dismiss'); if(empty($email_dismiss)) $email_dismiss='Are you sure you want to dismiss?';
  $min_qty_ntf=get_option('wac_min_qty_ntf'); if(empty($min_qty_ntf)) $min_qty_ntf="Add {Qty Diff} more {Product} to qualify for a discount.";
  $max_qty_ntf=get_option('wac_max_qty_ntf'); if(empty($max_qty_ntf)) $max_qty_ntf="Reduce {Product} quantity to {Max Qty} to qualify for a discount.";

  ob_start();
  if(isset($_GET['wac_trb']) && current_user_can('administrator') && !wp_doing_ajax()) $trb=1; else $trb=0;

  $coupon=wac_cache_coupon();
  if(!empty($coupon)) $req.=" AND coupon_code='$coupon'";
  $now=current_time('mysql');

  if($trb<1) {
    $req2.=" AND(individual='yes' OR apply IS NOT NULL OR c_email IS NOT NULL)";
    $req.=" AND exp=0";
  }

  $coupons=wac_r("
    SELECT *
    -- ,(SELECT GROUP_CONCAT(DISTINCT post_title) FROM wp_posts WHERE FIND_IN_SET(ID,product_ids)>0)product
      FROM (
      SELECT p.ID post_id,post_title coupon_code
      ,MAX(CASE WHEN pm.meta_key='product_ids' THEN pm.meta_value END)product_ids
      ,MAX(CASE WHEN pm.meta_key='exclude_product_ids' THEN pm.meta_value END)exc_prds
      ,MAX(CASE WHEN pm.meta_key='product_categories' THEN pm.meta_value END)cats
      ,MAX(CASE WHEN pm.meta_key='exclude_product_categories' THEN pm.meta_value END)exc_cats
      ,MAX(CASE WHEN pm.meta_key='individual_use' THEN pm.meta_value END)individual
      ,MAX(CASE WHEN pm.meta_key='coupon_amount' THEN pm.meta_value END)coupon_amount
      ,MAX(CASE WHEN pm.meta_key='customer_email' THEN pm.meta_value END)c_email
      ,MAX(CASE WHEN pm.meta_key='_wc_min_qty' THEN pm.meta_value END)min_qty
      ,MAX(CASE WHEN pm.meta_key='_wc_max_qty' THEN pm.meta_value END)max_qty
      ,MAX(CASE WHEN pm.meta_key='_wc_qty_ntf' THEN pm.meta_value END)qty_ntf
      ,MAX(CASE WHEN pm.meta_key='_wc_min_qty_ntf' THEN pm.meta_value END)min_qty_ntf
      ,MAX(CASE WHEN pm.meta_key='_wc_max_qty_ntf' THEN pm.meta_value END)max_qty_ntf
      ,MIN(CASE WHEN pm.meta_key LIKE '$meta' AND pm.meta_value='yes' THEN pm.meta_key END)apply
      ,DATE_FORMAT(FROM_UNIXTIME(x.meta_value),'%m-%d-%Y')exp_date
      ,CASE WHEN FROM_UNIXTIME(x.meta_value)<'$now' THEN 1 ELSE 0 END exp
      FROM wp_posts p
      LEFT JOIN wp_postmeta x ON x.post_id=p.ID AND x.meta_key='date_expires' AND LENGTH(x.meta_value)=10 AND x.meta_value REGEXP '[0-9]'
      LEFT JOIN wp_postmeta pm ON pm.post_id=p.ID
      WHERE post_type='shop_coupon'
      AND post_status='publish'
      GROUP BY p.ID
    )a
    WHERE 1=1
    $req2
    $req
    ORDER BY individual DESC,exp,CAST(coupon_amount AS SIGNED) DESC;");
  if(!$coupons)return;


  $user_removed_coupon=wac_get_removed_coupon();
  foreach($cart->cart_contents as $cart_item_key=>$cart_item) $cart_items++;

  $coupon_codes[]='';
  $coupon_count=count($coupons);
  foreach($coupons as $c) {
    $valid=1;
    $applied=0;
    $coupon_code=ucwords($c->coupon_code);
    $reason=$individual_use=$apply_type='';
    if($c->product_ids>0 || !empty($c->cats) || !empty($c->exc_cats)) $qty_in_cart=wac_qty_in_cart($c->product_ids,wac_unsr($c->exc_prds),wac_unsr($c->cats),wac_unsr($c->exc_cats)); else $qty_in_cart=$cart_qty;

    $wc_qty_ntf=wac_unsr($c->qty_ntf);
    if(empty($wc_qty_ntf)) $wc_qty_ntf=array(1,-1);
    $wc_min_ntf=$wc_qty_ntf[0];
    $wc_max_ntf=$wc_qty_ntf[1];
    
    if($c->individual=='yes') $individual_use='[Individual Use]';
    if($user_removed_coupon==strtolower($coupon_code)) $valid=wac_is_coupon_valid($coupon_code);

    if(!empty($apply_indv)) {$valid=0; $reason.=" Individual use coupon [$apply_indv] has already been applied."; if($trb<1) continue;}
    if($c->exp>0) {$valid=0; $reason.=" Expired {$c->exp_date}."; if($trb<1) continue;}
    if(!empty($c->product_ids) && $qty_in_cart<1 && empty($coupon)) {$valid=0; $reason.=' No qualifying cart items.'; if($trb<1) continue;}
    if(wac_in(strtolower($coupon_code),$cart->applied_coupons) || $woocommerce->cart->has_discount($c->coupon_code)) $applied=1;
    if($cart_items==0 && empty($coupon)) {$valid=0; $reason.=' No Items in Cart.'; if($trb<1) continue;}

    if($valid>0 && ($c->min_qty>0 || $c->max_qty>0)) { // Check Qty
      $item='';

      if(!empty($c->product_ids)) { // Prd Restrict
        $prd_ids=explode(",",$c->product_ids);
        $prd_ct=count($prd_ids);
        $i=1;
        foreach($prd_ids as $prd) {
          if(!empty($item)) if($i==$prd_ct) $item.=' or '; else  $item.=', ';
          $item.=get_the_title($prd);
          $i++;
        }
      }
      
      if(empty($item) && !empty($c->cats)) { // Cat Restrict
        $p_cat=wac_unsr($c->cats);
        $cat_ct=count($p_cat);
        $i=1;
        foreach($p_cat as $cat) {
          if(!empty($item)) if($i==$cat_ct) $item.=' or '; else  $item.=', ';
          $item.=get_term($cat)->name;
          $i++;
        }
      }

      if(empty($item)) $item='item';

      if($c->min_qty>0 && $qty_in_cart>0 && $qty_in_cart<$c->min_qty) {
        $valid=0; $reason.=" Min $item quantity: {$c->min_qty}. Quantity in cart: $qty_in_cart.";
        if($wc_min_ntf>0 && wac_is_path('cart')!==false) {
          if(empty($c->min_qty_ntf)) $ntf=$min_qty_ntf; else $ntf=$c->min_qty_ntf;
          $ntf=str_ireplace('{Product}',$item,$ntf);
          $ntf=str_ireplace('{Min Qty}',$c->min_qty,$ntf);
          $ntf=str_ireplace('{Qty Diff}',floatval($c->min_qty-$qty_in_cart),$ntf);
          echo "<div class='woocommerce-message wac'><i class='fas fa-sort-amount-up'></i> $ntf</div>";
        }
      }

      if($c->max_qty>0 && $qty_in_cart>$c->max_qty) {
        $valid=0; $reason.=" Max $item quantity: {$c->max_qty}. Quantity in cart: $qty_in_cart.";
        if($wc_max_ntf>0 && wac_is_path('cart')!==false) {
          if(empty($c->max_qty_ntf)) $ntf=$max_qty_ntf; else $ntf=$c->max_qty_ntf;
          $ntf=str_ireplace('{Product}',$item,$ntf);
          $ntf=str_ireplace('{Max Qty}',$c->max_qty,$ntf);
          $ntf=str_ireplace('{Qty Diff}',floatval($qty_in_cart-$c->max_qty),$ntf);
          echo "<div class='woocommerce-message wac'><i class='fas fa-sort-amount-down'></i> $ntf</div>";
        }
      }
    }

    if($user_removed_coupon==strtolower($coupon_code) && $c->apply=='_wc_auto_apply') {
      if($valid<1) wac_removed_coupon(); // uncache coupon if invalid
      if($valid>0) {
        $valid=0;
        $reason.=" Cannot re-auto-apply.
        <div style='font-size:.8em'>Previously removed from the cart manually. Reset cache to auto apply.
          <form method='post' style='display:inline'>
            <input type='hidden' name='wac_reset_cache' value=1>
            <input type='button' class='button' style='outline:none' onclick=this.form.submit(); value='Reset Cache'>
          </form>
        </div>";
        if($trb<1) continue;
      }
    }

    if($valid>0) $valid=wac_is_coupon_valid($coupon_code);

    if(function_exists('wac_email_prompt') && !empty($c->c_email) && $valid>0 && ($coupon_email>1 || ($coupon_email==1 && $c->apply=='_wc_auto_apply'))) {
      if((!empty($cart_email) && stripos($c->c_email,"\"$cart_email\"")!==false) || (!empty($wac_email) && stripos($c->c_email,"\"$wac_email\"")!==false)) {
        $email_match=1;
        $reason.=" Email $cart_email $wac_email matches restrictions.";
      }
      else {
        if(wac_is_path('cart,checkout')) $disp_email_prompt=1;
        $valid=0; $reason.=' Email restriction not verified.';
      }
    }

    if($valid>0 && ($applied>0 || !empty($coupon) || $c->apply=='_wc_auto_apply')) {
      if($applied<1) $woocommerce->cart->add_discount($coupon_code); // Apply To Cart
      if($cart_qty==0) wac_cache_coupon($coupon);
      $apply_type='';
      if(!empty($individual_use)) $apply_indv=$coupon_code; 
      if($c->apply=='_wc_auto_apply') {
        $apply_type=' Auto-';
        array_push($coupon_codes,$coupon_code); // Style line item
      }
      if($trb>0) $reason.="{$apply_type}Applied successfully $individual_use";
    } elseif($valid>0 && $trb>0) {
      $apply_type=' via manual entry';
      if(stripos($c->apply,'url')!==false) $apply_type.=' or URL';
      if($trb>0) $reason.="Eligible $apply_type. $individual_use";
    }

    if($valid<1 && $applied>0 && stripos($reason,'Manual')===false) $woocommerce->cart->remove_coupon($coupon_code); // Remove From Cart

    if($trb>0) {
      if(empty($reason)) $reason=wac_is_coupon_valid($coupon_code,1);
      echo "<div class='woocommerce-message wac trb'><i class='fas fa-exclamation-triangle'></i> $coupon_code -$reason</div>";
    }

  }
  if(function_exists('wac_email_prompt') && $disp_email_prompt>0) {
    if(!empty($wac_email) && $email_match<1) $prompt=$email_err; else $prompt=$email_prompt;
    echo wac_email_prompt($prompt,$email_dismiss,$wac_email);
    if(wac_is_path('checkout') && !empty($wac_email) && empty($cart_email)) if(function_exists('wac_prefill_email')) add_action('wp_footer',function()use($wac_email){wac_prefill_email($wac_email);});
  }
  if(!wp_doing_ajax() && !did_action('woocommerce_add_to_cart')) {
    add_action('wp_footer','wac_style_coupons');
    $output=ob_get_clean();
    add_action('the_content',function($content) use ($output) {return $output.$content;});
  }
}

if(isset($_GET['coupon'])) {
  add_action('init','wac_cache_coupon');
  add_action('template_redirect','wac_apply_coupons');
}
elseif(wac_is_path('cart')) add_action('wp_loaded','wac_apply_coupons'); // Cart
else {
  add_action('woocommerce_add_to_cart','wac_apply_coupons'); // Product
  add_action('woocommerce_before_checkout_form','wac_apply_coupons'); // Checkout
  add_action('woocommerce_before_cart','wac_apply_coupons'); // Cart
  add_action('woocommerce_cart_loaded_from_session', 'wac_apply_coupons'); // Ajax Cart Load
  add_action('woocommerce_after_cart_item_quantity_update', 'wac_apply_coupons'); // Ajax Quantity Update
  add_action('woocommerce_cart_item_removed', 'wac_apply_coupons'); // Ajax Item Removed
  add_action('woocommerce_cart_item_restored', 'wac_apply_coupons'); // Ajax Item Restored
}

function wac_refresh_on_quantity_change(){if(function_exists('is_cart')) if(is_cart()||is_checkout()){echo "<script>jQuery(document.body).on('updated_cart_totals',function(){if(jQuery('.quantity input').length){location.reload();}});</script>";}}
add_action('wp_footer','wac_refresh_on_quantity_change');


function wac_cart_coupon() {
  if(!isset($_POST['coupon_code'])) return;
  $coupon=sanitize_text_field($_POST['coupon_code']);
  $user_removed_coupon=wac_get_removed_coupon();
  if($user_removed_coupon==strtolower($coupon)) wac_removed_coupon();
}
if(isset($_POST['coupon_code'])) wac_cart_coupon();  // WC Apply


function wac_sess() {
  if(isset($_SERVER['HTTP_COOKIE'])) {
    $cookies=explode(';',$_SERVER['HTTP_COOKIE']);
    if(isset($cookies[1])) return sanitize_text_field($cookies[1]);
  }
  return sanitize_text_field($_SERVER['REMOTE_ADDR']);
}

function wac_cache_coupon($coupon='') {
  $sess=wac_sess();
  if(empty($sess)) return;
  if(isset($_GET['coupon'])) $coupon=sanitize_text_field($_GET['coupon']);
  if(!empty($coupon)) {set_transient("wac_ac_$sess",strtolower($coupon),86400); return $coupon;}

  $coupon=get_transient("wac_ac_$sess"); 
  if(!empty($coupon)) {
    delete_transient("wac_ac_$sess");
    return $coupon;
  }
}

function wac_removed_coupon($coupon='') {
  $sess=wac_sess();
  if(empty($sess)) return;
  if(empty($coupon)) delete_transient("wac_rc_$sess");
  else set_transient("wac_rc_$sess",strtolower($coupon),86400);
}
add_action('woocommerce_removed_coupon','wac_removed_coupon',10,1); // WC Remove

function wac_get_removed_coupon() {
  $sess=wac_sess();
  if(!empty($sess)) return get_transient("wac_rc_$sess");
}

function wac_clear_cache() {
  if(current_user_can('manage_woocommerce')) wac_r("DELETE FROM wp_options WHERE option_name LIKE '_transient_wac_%';");
  else wac_removed_coupon();
  $msg='Auto Coupons Cache Cleared.';
  if(is_admin()) echo "<div class='wac_alert'>$msg</div><br>";
  else echo "<div class='woocommerce-message wac trb'><i class='fas fa-exclamation-triangle'></i> $msg</div>";
}

function wac_style_coupons() {
  global $coupon_codes;
  if(empty($coupon_codes)) return;
  foreach($coupon_codes as $coupon_code) {
    $p=wac_r("
      SELECT post_title coupon_code, p.meta_value prefix
      ,(SELECT 1 FROM wp_postmeta WHERE post_id=c.ID AND meta_key='_wc_auto_apply' AND meta_value='yes' LIMIT 1)auto_apply
      FROM wp_posts c
      JOIN wp_postmeta p ON p.post_id=c.ID AND p.meta_key='_wc_prefix'
      WHERE post_type='shop_coupon'
      AND post_status='publish'
      AND post_title='$coupon_code'
      LIMIT 1;");

    $coupon_code=strtolower(str_replace(' ','-',$coupon_code));
    if($p) {
      $prefix=$p[0]->prefix;
      if($prefix!=='Coupon:') { ?><script type='text/javascript'>
        setTimeout(function(){wac_style_cpn(<?php echo "'$prefix','$coupon_code'";?>);},1000);
        setTimeout(function(){
          var instance=0;
          var interval=setInterval(function(){
            if(instance>20) window.clearInterval(interval);
            wac_style_cpn(<?php echo "'$prefix','$coupon_code'";?>);
            instance++;
          },5000);
        },3000);
      </script><?php }
    }
  } ?>
  <style>
    .woocommerce .wac .fas{zoom:1.3}
    .woocommerce .wac .fa-sort-amount-up{color:#77c777}
    .woocommerce .wac .fa-sort-amount-down{color:darkorange}
    .woocommerce-message.wac.trb:before{display:none}
    .woocommerce .wac.trb{background:rgba(220,220,220,.5);color:#777}
    .woocommerce .wac.trb .fa-exclamation-triangle{color:#ffa2a2}
    .woocommerce .wac.trb .fa-check-circle{color:#77c777}
    .woocommerce-message.wac.trb{margin-bottom:1em;padding:1em}
    .woocommerce .cart-discount td[data-title],.woocommerce .cart-discount .amount{color:green}
    .woocommerce .cart-discount td:after,.woocommerce .cart-discount td[data-title]:after{content:"\00a0 \f14a";display:inline-block;font-family:'Font Awesome 5 Free';position:absolute;margin-top:-.5em;opacity:0;-webkit-transition:all 1s;transition:all 1s}
    .woocommerce .cart-discount td[data-title]:before{color:#6d6d6d;font-weight:normal;white-space:pre-wrap}
    .woocommerce .cart-discount.anm td:after{opacity:1;margin-left:-3em;color:green;font-size:2em}
  </style>
  <script type='text/javascript'>
    function wac_style_cpn(prefix,coupon_code) {
      var coupon_class='coupon-'+coupon_code;
      if(!document.getElementsByClassName(coupon_class)) return;
      cpn=document.getElementsByClassName(coupon_class);
      for(i=0; i<cpn.length; i++) {
        if(cpn[i].innerHTML.indexOf(coupon_code)>0) {
          txt=cpn[i].innerHTML;
          oprefix=txt.substring(0,txt.indexOf(': '+coupon_code)+1);
          oprefix=oprefix.substring(oprefix.lastIndexOf('>')+1,99);
          if(oprefix.length>1) cpn[i].innerHTML=cpn[i].innerHTML.replace(new RegExp(oprefix,'g'),prefix);
        }
      }
    }
  </script><?php 
}


// WC Admin Coupon Quantity
function wac_add_coupon_fields() {
  wp_nonce_field('wac_coupon_nonce','wac_coupon_nonce_field');
  $cart_page=end(array_filter(explode('/',wc_get_cart_url())));
  update_option('wac_cart_page',$cart_page,'no'); // update cart page cache
  
  $checkout_page=end(array_filter(explode('/',wc_get_checkout_url())));
  update_option('wac_checkout_page',$checkout_page,'no'); // update checkout page cache

  if(isset($_GET['post']))$post_id=intval($_GET['post']); else $post_id=0;

  $min_qty_ntf=$max_qty_ntf=$bulk_apply_future=$bulk_url_future='';
  if(function_exists('wac_email_prompt')) {
    $bulk_apply_future=get_option('wac_bulk_apply_future');
    $bulk_url_future=get_option('wac_bulk_url_future');
    $min_qty_ntf=get_option('wac_min_qty_ntf');
    $max_qty_ntf=get_option('wac_max_qty_ntf');
  }
  if(empty($min_qty_ntf)) $min_qty_ntf='Add {Qty Diff} more {Product} to qualify for a discount.';
  if(empty($max_qty_ntf)) $max_qty_ntf='Reduce {Product} quantity to {Max Qty} to qualify for a discount.';
  $wc_qty_ntf=get_post_meta($post_id,'_wc_qty_ntf');
  if(empty($wc_qty_ntf)) $wc_qty_ntf=array(array(-1,-1,''));
  $wc_min_ntf=$wc_qty_ntf[0][0];
  $wc_max_ntf=$wc_qty_ntf[0][1];

  $wc_min_ntf_check=$wc_max_ntf_check='';
  if($wc_min_ntf>0) $wc_min_ntf_check='checked';
  if($wc_max_ntf>0) $wc_max_ntf_check='checked';
  
  $alert='';
  if(!function_exists('wac_get_email')) {
    $auto_apply=get_post_meta($post_id,'_wc_auto_apply',true);
    $customer_email=get_post_meta($post_id,'customer_email',true);
    if($auto_apply=='yes' && !empty($customer_email)) $alert="<div style='padding:2em 2em 4em;font-size:1.1em;'><span style='font-weight:500'><span class='dashicons dashicons-warning' style='color:red'></span> Setting this coupon to Auto-Apply with email restrictions is not recommended. WooCommerce does not validate emails against a coupon until payment, therefore this coupon will initially auto-apply to all carts.</span><br><br>Upgrade to <a href='admin.php?page=woo-auto-coupons&tab=pro'>Auto Coupons PRO</a> for improved handling of auto-apply coupons with email restrictions.</div>";
  }
  echo "<div class='options_group'>
    <style>.coupon-link{display:block;margin:1em;padding:1em;border-radius:5px;white-space:nowrap;overflow-x:auto;background:#fff}</style>
    <p class='form-field' style='background:#0073aa;margin:1em 0 0'><label style='color:#fff;font-variant-caps:all-small-caps;font-size:1.3em'><span class='dashicons dashicons-admin-settings' style='vertical-align:text-top'></span> Auto Coupons</label></p>";
    echo $alert;
    woocommerce_wp_checkbox(array('id'=>'_wc_url_apply','label'=> __('Apply via URL','wc-url-apply'),'placeholder'=>'','description'=> __('Allow coupons to be applied with a link.<span class=\'coupon-link\'><b>Add a coupon to cart</b>: /cart/?coupon=<span class=\'cname\'>COUPON_NAME</span><br><b>Add a product & coupon</b>: /cart/?add-to-cart=<i>{Product ID or Variation ID}</i>&quantity=1&coupon=<span class=\'cname\'>COUPON_NAME</span><br><span class=\'wac_restr\'>&#9888; <i>If usage restrictions are present, coupon won\'t apply until restrictions are met.</i></span></span>','wc-url-apply')));
    woocommerce_wp_checkbox(array('id'=>'_wc_auto_apply','label'=> __("Auto Apply",'wc-auto-apply'),'placeholder'=>'','desc_tip'=>'true','description'=> __('This setting will apply the coupon to ALL qualifying carts on the cart and checkout page.','wc-auto-apply')));
    woocommerce_wp_text_input(array('id'=>'_wc_prefix','label'=> __('Line Item Name','wc-prefix'),'placeholder'=>'Coupon:','desc_tip'=>'true','description'=> __('Enter a line item name to be shown before the coupon name in cart. e.g. Coupon: ','wc-prefix')));

// Min Quantity
  echo "<div class='_wc_qty'>";
    woocommerce_wp_text_input(array('id'=>'_wc_min_qty','class'=>'qty_input','label'=> __('Min Cart Quantity','wc-min-qty'),'placeholder'=>'Optional','desc_tip'=>'true','description'=> __('Set a minimum product quantity for this coupon. Enter a number, 1 or greater.','wc-min-qty')));
  echo "<p class='form-field ntf_group_field'><input type='checkbox' $wc_min_ntf_check onclick=\"wac_ntf('min',this.checked);\"> Display a notification when quantity in cart is less than min quantity of coupon.</p><div id='min_qty_ntf'>";
    woocommerce_wp_text_input(array('id'=>'_wc_min_qty_ntf','class'=>'txt_input','label'=> __('','wc-min-qty-ntf'),'placeholder'=>"$min_qty_ntf",'desc_tip'=>'true','description'=> __('Notification when quantity limit not satisfied. If blank will use the default notification.','wc-min-qty-ntf')));
  echo "<p class='form-field min_qty_ntf_group_field'>&#8226; <b>Variables</b>: {Product} {Min Qty} {Qty Diff}<br>&#8226; <b>Default</b>: $min_qty_ntf</p></div><div class='max_group'>";

//Max Quantity
    woocommerce_wp_text_input(array('id'=>'_wc_max_qty','class'=>'qty_input','label'=> __('Max Cart Quantity','wc-max-qty'),'placeholder'=>'Optional','desc_tip'=>'true','description'=> __('Set a maximum quantity limit allowed per coupon. Enter a number, 1 or greater.','wc-max-qty')));
  echo "<p class='form-field ntf_group_field'><input type='checkbox' $wc_max_ntf_check onclick=\"wac_ntf('max',this.checked);\"> Display a notification when quantity in cart is more than max quantity of coupon.</p><div id='max_qty_ntf'>";
    woocommerce_wp_text_input(array('id'=>'_wc_max_qty_ntf','class'=>'txt_input','label'=> __('','wc-max-qty-ntf'),'placeholder'=>"$max_qty_ntf",'desc_tip'=>'true','description'=> __('Notification when quantity limit exceeded. If blank will use the default notification.','wc-max-qty-ntf')));
  echo "<p class='form-field max_qty_ntf_group_field'>&#8226; <b>Variables</b>: {Product} {Max Qty} {Qty Diff}<br>&#8226; <b>Default</b>: $max_qty_ntf</p>
          <input type='hidden' name='_wc_qty_ntf[]' id='wc_min_ntf' value='$wc_min_ntf'>
          <input type='hidden' name='_wc_qty_ntf[]' id='wc_max_ntf' value='$wc_max_ntf'>
        </div>
      </div>
    </div>
  </div>
  <datalist id='wc_prefix'>
    <option>Coupon:</option>
    <option>Discount:</option>
    <option>Promo:</option>
    <option>Promotion:</option>
    <option>Sale:</option>
  </datalist>
  <style>
    .woocommerce_options_panel p._wc_url_apply_field,.woocommerce_options_panel p._wc_prefix_field,.woocommerce_options_panel p._wc_max_qty_field,div.max_group{background:#f8f8f8;padding-top:1em!important;padding-bottom:1em!important;margin-top:0}
    .woocommerce_options_panel p._wc_min_qty_field,.woocommerce_options_panel p._wc_max_qty_field{padding-bottom:0!important}
    .woocommerce_options_panel p.ntf_group_field{padding-top:0!important}
    #_wc_prefix{float:left;width:50%}
    .wac_restr{color:#0073ab;font-size:.9em}
    .woocommerce_options_panel p.form-field .qty_input{width:5em}
    .woocommerce_options_panel p.form-field .txt_input{width:85%}
    .woocommerce_options_panel p._wc_min_qty_ntf_field,.woocommerce_options_panel p._wc_max_qty_ntf_field{margin-bottom:0}
    .woocommerce_options_panel p.min_qty_ntf_group_field,.woocommerce_options_panel p.max_qty_ntf_group_field{margin-top:0}
  </style>
  <script type='text/javascript'>
    function wac_getE(e) {return document.getElementById(e);}
    function wac_ntf(field,checked) {
      var i=wac_getE('wc_'+field+'_ntf');
      var o=wac_getE(field+'_qty_ntf');
      if(checked>0) {
        i.value=1;
        o.style.display='block';
      } else {
        i.value=-1;
        o.style.display='none';
      }
    }
    
    function wac_cname() {
      var ctitle=wac_getE('title').value;
      var cname=document.getElementsByClassName('cname');
      if(ctitle.length>0) {for(var i=0; i<cname.length; i++) cname[i].innerHTML=ctitle;}
    }
    wac_cname();
    
    function wac_bulk_apply_future() {
      var ctitle=wac_getE('title').value;
      if(ctitle.length==0) {
        var baf='$bulk_apply_future';
        var buf='$bulk_url_future';
        if(baf.length>0) wac_getE('_wc_auto_apply').checked=true;
        if(buf.length>0) wac_getE('_wc_url_apply').checked=true;
      }
    }
    wac_bulk_apply_future();

    function wac_dsp_options() {
      wcua=wac_getE('_wc_url_apply');
      wcaa=wac_getE('_wc_auto_apply');
      wcmn=wac_getE('_wc_min_qty');
      wcmx=wac_getE('_wc_max_qty');
      mnn=wac_getE('wc_min_ntf');
      mxn=wac_getE('wc_max_ntf');
      mnq=wac_getE('min_qty_ntf');
      mxq=wac_getE('max_qty_ntf');
      if(mnn.value<1) mnq.style.display='none';
      if(mxn.value<1) mxq.style.display='none';
      if(wcmn.value>0) tmn=wcmn.value;
      if(wcmx.value>0) tmx=wcmx.value;
      if(wcaa.checked) {
        document.getElementsByClassName('_wc_qty')[0].style.visibility='visible';
        if(typeof tmn!=='undefined') wcmn.value=tmn;
        if(typeof tmx!=='undefined') wcmx.value=tmx;
        if(wcmn.value>0 && wcmx.value && parseInt(wcmn.value)>parseInt(wcmx.value)) alert('Min quantity must be less than max quantity.');
      } else {
        wcmn.value=wcmx.value='';
        document.getElementsByClassName('_wc_qty')[0].style.visibility='hidden';
      }
      if(wcua.checked || wcaa.checked) {
        document.getElementsByClassName('_wc_prefix_field')[0].style.visibility='visible';
        wac_getE('_wc_prefix').setAttribute('list','wc_prefix');
        wac_getE('_wc_prefix').setAttribute('type','search');
      }
      else document.getElementsByClassName('_wc_prefix_field')[0].style.visibility='hidden';
    }
    wac_dsp_options();
    
    function wac_val_qty() {
      wcmn=wac_getE('_wc_min_qty');
      wcmx=wac_getE('_wc_max_qty');
      if(wcmn.value>0 && wcmx.value && parseInt(wcmn.value)>parseInt(wcmx.value)) {
        alert('Min quantity must be less than max quantity.');
        wcmn.style.borderColor='red';
      } else wcmn.style.borderColor='';
    }

    function wac_activate(wac_id) {
      cpn=wac_getE('title').value;
      expiry=wac_getE('expiry_date');
      var todayDate=new Date().toISOString().slice(0,10);
      if(expiry.value.length>0 && expiry.value<=todayDate){alert('This coupon expired on '+expiry.value+' and it cannot be auto-applied.');expiry.focus();return false;}
      if(wac_id=='_wc_auto_apply')alert('This setting will apply the '+cpn+' coupon to ALL qualifying carts on the cart and checkout page.');
      if(wac_id=='_wc_url_apply')alert('This setting will apply the coupon to qualifying carts upon visiting:\\n/?coupon='+cpn+'.');
      return true;
    }

    wac_getE('title').onchange=function(){wac_cname();};
    wac_getE('titlewrap').onclick=function(){wac_cname();};
    wac_getE('_wc_min_qty').setAttribute('pattern','[0-9 ]{0,9}');
    wac_getE('_wc_max_qty').setAttribute('pattern','[0-9 ]{0,9}');
    wac_getE('_wc_min_qty').setAttribute('title','Min quantity (number only)');
    wac_getE('_wc_max_qty').setAttribute('title','Max quantity (number only)');
    wac_getE('_wc_min_qty').onchange=function(){wac_val_qty();};
    wac_getE('_wc_max_qty').onchange=function(){wac_val_qty();};
    wac_getE('_wc_auto_apply').onclick=function(){if(this.checked)if(!wac_activate(this.id))this.checked=false;wac_dsp_options();}
    wac_getE('_wc_url_apply').onclick=function() {if(this.checked)if(!wac_activate(this.id))this.checked=false;wac_dsp_options();}
    $alert
  </script>
  ";
}
add_action('woocommerce_coupon_options','wac_add_coupon_fields');


// WC Save Admin Coupon Fields
function wac_save_coupon($post_id,$coupon){
  if(!isset($_POST['wac_coupon_nonce_field']) || !wp_verify_nonce($_POST['wac_coupon_nonce_field'],'wac_coupon_nonce')) return;

  $val_url=trim(get_post_meta($post_id,'_wc_url_apply',true));
  if(isset($_POST['_wc_url_apply'])) $new_url=sanitize_text_field($_POST['_wc_url_apply']); else $new_url='';
  if($val_url!=$new_url)update_post_meta($post_id,'_wc_url_apply',$new_url);

  $val_auto=trim(get_post_meta($post_id,'_wc_auto_apply',true));
  if(isset($_POST['_wc_auto_apply'])) $new_auto=sanitize_text_field($_POST['_wc_auto_apply']); else $new_auto='';
  if($val_auto!=$new_auto)update_post_meta($post_id,'_wc_auto_apply',$new_auto);

  $val_prefix=trim(get_post_meta($post_id,'_wc_prefix',true));
  $new_prefix=sanitize_text_field($_POST['_wc_prefix']);
  if($val_prefix!=$new_prefix)update_post_meta($post_id,'_wc_prefix',$new_prefix);

  $val_min=trim(get_post_meta($post_id,'_wc_min_qty',true));
  $new_min=intval($_POST['_wc_min_qty']);
  if($new_min==0) $new_min='';
  if($val_min!=$new_min)update_post_meta($post_id,'_wc_min_qty',$new_min);

  $val_max=trim(get_post_meta($post_id,'_wc_max_qty',true));
  $new_max=intval($_POST['_wc_max_qty']);
  if($new_max==0) $new_max='';
  if($val_max!=$new_max)update_post_meta($post_id,'_wc_max_qty',$new_max);

  $val_min_qty_ntf=trim(get_post_meta($post_id,'_wc_min_qty_ntf',true));
  $new_min_qty_ntf=sanitize_text_field($_POST['_wc_min_qty_ntf']);
  if($new_min_qty_ntf==0) $new_min_qty_ntf='';
  if($val_min_qty_ntf!=$new_min_qty_ntf)update_post_meta($post_id,'_wc_min_qty_ntf',$new_min_qty_ntf);

  $val_max_qty_ntf=trim(get_post_meta($post_id,'_wc_max_qty_ntf',true));
  $new_max_qty_ntf=sanitize_text_field($_POST['_wc_max_qty_ntf']);
  if($new_max_qty_ntf==0) $new_max_qty_ntf='';
  if($val_max_qty_ntf!=$new_max_qty_ntf)update_post_meta($post_id,'_wc_max_qty_ntf',$new_max_qty_ntf);

  $val_ntf=get_post_meta($post_id,'_wc_qty_ntf',true);
  $new_ntf=array_map('sanitize_text_field',$_POST['_wc_qty_ntf']);
  if($val_ntf!=$new_ntf)update_post_meta($post_id,'_wc_qty_ntf',$new_ntf);
}
add_action('woocommerce_coupon_options_save','wac_save_coupon',10,2);
