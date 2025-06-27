<?php
if (!defined('WPINC')) {
    die;
}

/**
 * Upgrade to pro metabox html
 * @since 1.3.3
 */

$custom_tick = '<img src="'. esc_url( WT_SMARTCOUPON_MAIN_URL ) .'admin/images/tick.svg" alt="'. __( "Tick", "wt-smart-coupons-for-woocommerce" ) .'" style="margin-right:9.5px;">';
?>
<style type="text/css">
    
    #wt-sc-upgrade-to-pro .postbox-header{  height:80px; background:url( <?php echo esc_attr( WT_SMARTCOUPON_MAIN_URL . 'admin/images/upgrade_box_icon.svg' ); ?> ) no-repeat 18px 18px #fff; padding-left:65px; margin-bottom:18px; background-size: 45px 45px; }
    #wt-sc-upgrade-to-pro .postbox-header h2{ color:#000; font-size:14px; font-weight: 600;}
    #wt-sc-upgrade-to-pro .postbox-header .handle-actions{ display:none; }
    #wt-sc-upgrade-to-pro .inside{ background:#fff;  padding:0px 20px; padding-bottom:30px; border-radius:15px;}

    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features{ float: left; margin-top:10px; margin-bottom:20px; }
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features li{ padding:3px 0px; font-size:13px; color:#212121; font-weight: 500;}
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features .wt-sc-pro-features-bullet{ padding:3px 50px;}
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features .wt-sc-pro-features-bullet li::before{ content: "\2022"; color: #0263D5; font-weight: bold; display: inline-block; width: 1em; margin-left: -1em; font-size: 18px;}
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features .wt-sc-pro-features-all-features{ display:none;} 
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features .dashicons{ background:#fff; color:#6ABE45; border-radius:20px; margin-right:5px; }
    #wt-sc-upgrade-to-pro .wt-sc-metabox-pro-features .wt-sc-metabox-pro-features-heading{  color:#0263D5; font-weight:700; text-align:left; font-size: 13px; }
    #wt-sc-upgrade-to-pro .wt-sc-metabox-upgrade-to-pro-btn{ color:#fff; display:inline-block; text-decoration:none; text-align:center; font-size:14px; font-weight:500; line-height:47px; width:227px; height:47px; background:#0263D5; border-radius:2px; }
    #wt-sc-upgrade-to-pro .wt-sc-pro-features-view-all{ color:#007FFF; text-decoration-line: underline; text-align:center; cursor: pointer;}
    #wt-sc-upgrade-to-pro .wt-sc-pro-features-view-less{ color:#007FFF; text-decoration-line: underline; text-align:center; display:none; cursor: pointer;}
</style>

<ul class="wt-sc-metabox-pro-features">
    <li class="wt-sc-metabox-pro-features-heading"><?php _e( 'Smart Coupons Premium features:', 'wt-smart-coupons-for-woocommerce' );?></li>
    <li><?php echo $custom_tick; ?></span><?php _e( 'Create advanced BOGO coupons', 'wt-smart-coupons-for-woocommerce' );?></li>
    <li><?php echo $custom_tick; ?></span><?php _e( 'Offer store credits and gift cards', 'wt-smart-coupons-for-woocommerce' );?></li>
    <li><?php echo $custom_tick; ?></span><?php _e( 'Set up smart giveaway campaigns', 'wt-smart-coupons-for-woocommerce' );?></li>
    <div class="wt-sc-pro-features-all-features">
        <li><?php echo $custom_tick; ?></span><?php _e( 'Bulk generate coupons', 'wt-smart-coupons-for-woocommerce' );?></li>
        <li><?php echo $custom_tick; ?></span><?php _e( 'Coupons to boost conversion rate:', 'wt-smart-coupons-for-woocommerce' );?></li>
        <ul class="wt-sc-pro-features-bullet">
            <li><?php _e('Purchase history-based coupons', 'wt-smart-coupons-for-woocommerce');?></li>
            <li><?php _e('Sign up coupons', 'wt-smart-coupons-for-woocommerce');?></li>
            <li><?php _e('Cart abandonment coupons', 'wt-smart-coupons-for-woocommerce');?></li>
        </ul>
        <li><?php echo $custom_tick; ?></span><?php _e( 'Create day-specific deals', 'wt-smart-coupons-for-woocommerce' );?></li>
        <li><?php echo $custom_tick; ?></span><?php _e( 'Display coupon banners and widgets', 'wt-smart-coupons-for-woocommerce');?></li>
        <li><?php echo $custom_tick; ?></span><?php _e( 'Import coupons', 'wt-smart-coupons-for-woocommerce' );?></li>
    </div>
    <p class="wt-sc-pro-features-view-all"><?php _e( 'See more...', 'wt-smart-coupons-for-woocommerce' );?></p>
    <p class="wt-sc-pro-features-view-less"><?php _e( 'View less...', 'wt-smart-coupons-for-woocommerce' );?></p>
</ul>
<div style="text-align: center;">
    <a href="<?php echo esc_attr( 'https://www.webtoffee.com/product/smart-coupons-for-woocommerce/?utm_source=free_plugin_marketing_sidebar&utm_medium=smart_coupons_basic&utm_campaign=smart_coupons' ) ; ?>" class="wt-sc-metabox-upgrade-to-pro-btn" target="_blank">
        <?php _e( 'Check Out Premium', 'wt-smart-coupons-for-woocommerce' );?><span class="dashicons dashicons-arrow-right-alt" style="margin-top:16px;font-size:16px;"></span>
    </a>
</div>