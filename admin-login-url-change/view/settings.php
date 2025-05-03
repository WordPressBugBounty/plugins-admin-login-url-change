<div class="wrap">
   <h2 class="wp-admin-change-title"><?php echo esc_html($this->plugin->displayName); ?> &raquo; <?php esc_html_e( 'Settings','admin-login-url-change'); ?> </h2>
   <div class="jh-admin-setting-box">
      <div class="jh-admin-setting-form">
         <?php
            if ( isset( $this->message ) ) {
         ?>
            <div class="updated fade">
               <p><?php echo esc_html($this->message); ?></p>
            </div>
         <?php
         }
         if ( isset( $this->errorMessage ) ) {
         ?>
         <div class="error fade"><p><?php echo esc_html($this->errorMessage); ?></p></div>
         <?php
         }
         ?>
         <?php
            $hfcm_form_action = admin_url('options-general.php?page=admin-login-url-change');
            
            ?>
         <div class="admin_url_notes">
            <ul>
               <li><code><?php esc_html_e("No need to add your domain url. You just add your new login slug. Example: newadmin/adminlogin .... etc","admin-login-url-change"); ?></code></li>
               
            </ul>
         </div>
         <div class="wp-admin-change-box">
            <form method='post' action='<?php echo esc_html($hfcm_form_action); ?>'>
               <p>
                  <label for="jh-new-login-url"><?php esc_html_e("Add New Login Slug","admin-login-url-change"); ?></label>
                  <input type="text" name="jh_new_login_url" id="jh-new-login-url" placeholder="Example: newadmin/adminlogin .... etc" value="<?php if(!empty($this->admin_login_url_info['jh_new_login_url'])){ echo esc_html($this->admin_login_url_info['jh_new_login_url']); } ?>" <?php echo ( ! current_user_can( 'unfiltered_html' ) ) ? ' disabled="disabled" ' : ''; ?> />
               </p>
            
               <?php wp_nonce_field( 'jh_login_url_nonce_action', 'jh_login_url_nonce' ); ?>
               <p>
                  <input type='submit' name='but_submit' value='<?php esc_attr_e("Submit","admin-login-url-change"); ?>'>
               </p>
            </form>
         </div>
      </div>

      <div class="jh-link-boxs">

         <div class="jh-link-box">
            <a href="http://wpassisthub.com/contact/" target="_blank">
               <img src="<?php echo $this->plugin->url; ?>assets/images/jh-custom-service.png" alt="<?php esc_html_e("Custom Service","admin-login-url-change"); ?>">
               <h3><?php esc_html_e("More Services","admin-login-url-change"); ?></h3>
               <p><?php esc_html_e("We offer custom plugin development, website design, speed optimization, and full site customization—tailored to meet your unique needs and enhance your WordPress site's performance and functionality.","admin-login-url-change"); ?></p>
               <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
            </a>
         </div>
         <div class="jh-link-box">
            <a href="http://wpassisthub.com/contact/" target="_blank">
               <img src="<?php echo $this->plugin->url; ?>assets/images/jh-mail.png" alt="<?php esc_html_e("Mail","admin-login-url-change"); ?>">
               <h3><?php esc_html_e("Mail Support","admin-login-url-change"); ?></h3>
               <p><?php esc_html_e("Get reliable mail support from our team—fast, friendly assistance for your WordPress issues right in your inbox.","admin-login-url-change"); ?></p>
               <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
               
            </a>
         </div>
         <div class="jh-link-box">
            <a href="http://wpassisthub.com/" target="_blank">
               <img src="<?php echo $this->plugin->url; ?>assets/images/jh-comment.png" alt="<?php esc_html_e("Live Chat","admin-login-url-change"); ?>">
               <h3><?php esc_html_e("Live Chat","admin-login-url-change"); ?></h3>
               <p><?php esc_html_e("Connect with us instantly through live chat for quick, real-time support and solutions to your WordPress questions and issues.","admin-login-url-change"); ?></p>
               <span><?php esc_html_e("Contact Us","admin-login-url-change"); ?></span>
            </a>
         </div>

      </div>
   </div>
</div>
