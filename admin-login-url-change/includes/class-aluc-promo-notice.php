<?php
/**
 * Seasonal promo notice (Early Black Friday).
 *
 * - Shown only to admins on the free version (hidden when Pro is active).
 * - Admin notice on the Dashboard screen, with "Remind me later" (snooze)
 *   and "Don't show again" (permanent, per user).
 * - A non-dismissible banner on the plugin's own settings page.
 * - Automatically active only inside the campaign date window.
 *
 * Preview any time (admins only): add ?aluc_promo_preview=1 to an admin URL.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ALUC_Promo_Notice {

    /* ---------- Campaign config: edit these for each campaign ---------- */
    const CAMPAIGN          = 'earlybf2026';
    const START             = '2026-11-01 00:00:00 UTC';
    const END               = '2026-11-14 23:59:59 UTC';
    const LAST_CHANCE_FROM  = '2026-11-12 00:00:00 UTC'; // snoozed users see it once more from here
    const COUPON            = 'EARLYBF40';
    const DISCOUNT          = 40;
    const SNOOZE_DAYS       = 7;
    const MIN_INSTALL_DAYS  = 3;
    /* ------------------------------------------------------------------- */

    const META_KEY    = 'aluc_promo_state';
    const INSTALL_OPT = 'aluc_installed_at';
    const NONCE       = 'aluc-promo-nonce';

    public static function init() {
        $self = new self();

        add_action( 'admin_init',            [ $self, 'maybe_set_install_time' ] );
        add_action( 'admin_notices',         [ $self, 'render_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $self, 'enqueue_assets' ] );
        add_action( 'aluc_settings_after_header', [ $self, 'render_settings_banner' ] );
        add_action( 'wp_ajax_aluc_promo_dismiss', [ $self, 'ajax_dismiss' ] );
    }

    /* ======================= Conditions ======================= */

    private function is_preview() {
        return isset( $_GET['aluc_promo_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
    }

    private function is_pro() {
        if ( defined( 'ALUC_PRO_VERSION' ) || apply_filters( 'aluc_pro_active', false ) ) {
            return true;
        }
        if ( function_exists( 'aluc_fs' ) ) {
            $fs = aluc_fs();
            if ( $fs && method_exists( $fs, 'is_paying' ) && $fs->is_paying() ) {
                return true;
            }
        }
        return false;
    }

    private function in_campaign_window() {
        $now = time();
        return $now >= strtotime( self::START ) && $now <= strtotime( self::END );
    }

    /** Base eligibility shared by the notice and the settings banner. */
    private function campaign_eligible() {
        if ( ! current_user_can( 'manage_options' ) || $this->is_pro() ) {
            return false;
        }
        return $this->is_preview() || $this->in_campaign_window();
    }

    private function should_show_notice() {
        if ( ! $this->campaign_eligible() ) {
            return false;
        }
        if ( $this->is_preview() ) {
            return true;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, [ 'dashboard' ], true ) ) {
            return false;
        }

        $installed = (int) get_option( self::INSTALL_OPT, 0 );
        if ( $installed && ( time() - $installed ) < self::MIN_INSTALL_DAYS * DAY_IN_SECONDS ) {
            return false;
        }

        $state = $this->get_state();
        if ( 'dismissed' === $state['status'] ) {
            return false;
        }
        if ( 'snoozed' === $state['status'] ) {
            $last_chance = strtotime( self::LAST_CHANCE_FROM );
            // Snoozed before the last-chance window opened -> show once more.
            if ( time() >= $last_chance && $state['at'] < $last_chance ) {
                return true;
            }
            return time() >= $state['until'];
        }
        return true;
    }

    private function get_state() {
        $all   = get_user_meta( get_current_user_id(), self::META_KEY, true );
        $state = ( is_array( $all ) && isset( $all[ self::CAMPAIGN ] ) ) ? $all[ self::CAMPAIGN ] : [];
        return wp_parse_args( $state, [ 'status' => '', 'at' => 0, 'until' => 0 ] );
    }

    /* ======================= Hooks ======================= */

    public function maybe_set_install_time() {
        if ( get_option( self::INSTALL_OPT ) ) {
            return;
        }
        // Existing users (slug already configured) count as long-time installs.
        $time = get_option( 'jh_new_login_url' ) ? time() - 30 * DAY_IN_SECONDS : time();
        update_option( self::INSTALL_OPT, $time, false );
    }

    public function enqueue_assets() {
        $screen      = get_current_screen();
        $on_settings = $screen && 'toplevel_page_admin-login-url-change' === $screen->id;

        if ( ! ( $on_settings ? $this->campaign_eligible() : $this->should_show_notice() ) ) {
            return;
        }

        $css_ver = ALUC_Plugin::VERSION . '.' . filemtime( ALUC_PLUGIN_PATH . 'assets/css/promo.css' );
        $js_ver  = ALUC_Plugin::VERSION . '.' . filemtime( ALUC_PLUGIN_PATH . 'assets/js/promo.js' );

        wp_enqueue_style( 'aluc-promo', ALUC_PLUGIN_URL . 'assets/css/promo.css', [], $css_ver );
        wp_enqueue_script( 'aluc-promo', ALUC_PLUGIN_URL . 'assets/js/promo.js', [], $js_ver, true );
        wp_localize_script( 'aluc-promo', 'aluc_promo', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( self::NONCE ),
            'copied'   => __( 'Copied!', 'admin-login-url-change' ),
        ] );
    }

    public function ajax_dismiss() {
        check_ajax_referer( self::NONCE, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        $type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'snooze';
        $user_id = get_current_user_id();
        $all     = get_user_meta( $user_id, self::META_KEY, true );
        $all     = is_array( $all ) ? $all : [];

        $all[ self::CAMPAIGN ] = ( 'dismiss' === $type )
            ? [ 'status' => 'dismissed', 'at' => time(), 'until' => 0 ]
            : [ 'status' => 'snoozed',   'at' => time(), 'until' => time() + self::SNOOZE_DAYS * DAY_IN_SECONDS ];

        update_user_meta( $user_id, self::META_KEY, $all );
        wp_send_json_success();
    }

    /* ======================= Rendering ======================= */

    private function upgrade_url() {
        // Freemius merges $_GET into the pricing/checkout config, so the coupon
        // is forwarded and pre-filled where the checkout supports it.
        return add_query_arg( 'coupon', rawurlencode( self::COUPON ), admin_url( 'admin.php?page=admin-login-url-change-pricing' ) );
    }

    private function render_howto() {
        ?>
        <p class="aluc-promo-howto">
            <?php
            /* translators: %s: coupon code */
            printf( esc_html__( 'How to redeem: copy %s, choose your plan, then apply the code in the coupon field at checkout before paying.', 'admin-login-url-change' ), '<strong>' . esc_html( self::COUPON ) . '</strong>' );
            ?>
        </p>
        <?php
    }

    private function days_left_text() {
        $days = (int) ceil( ( strtotime( self::END ) - time() ) / DAY_IN_SECONDS );
        if ( $days > 1 ) {
            /* translators: %d: number of days */
            return sprintf( __( 'Ends in %d days', 'admin-login-url-change' ), $days );
        }
        return $days === 1 ? __( 'Ends tomorrow', 'admin-login-url-change' ) : __( 'Ends today', 'admin-login-url-change' );
    }

    private function is_last_chance() {
        return time() >= strtotime( self::LAST_CHANCE_FROM );
    }

    private function render_coupon() {
        ?>
        <button type="button" class="aluc-promo-coupon" data-code="<?php echo esc_attr( self::COUPON ); ?>" title="<?php esc_attr_e( 'Click to copy', 'admin-login-url-change' ); ?>">
            <span class="aluc-promo-coupon-code"><?php echo esc_html( self::COUPON ); ?></span>
            <span class="aluc-promo-coupon-copy"><?php esc_html_e( 'Copy', 'admin-login-url-change' ); ?></span>
        </button>
        <?php
    }

    public function render_notice() {
        if ( ! $this->should_show_notice() ) {
            return;
        }
        $eyebrow = $this->is_last_chance()
            ? __( 'Last chance · Early Black Friday', 'admin-login-url-change' )
            : __( 'Early Black Friday Deal', 'admin-login-url-change' );
        ?>
        <div class="notice aluc-promo-notice" id="aluc-promo-notice">
            <button type="button" class="aluc-promo-close" data-type="snooze" aria-label="<?php esc_attr_e( 'Remind me later', 'admin-login-url-change' ); ?>">&times;</button>

            <div class="aluc-promo-badge">
                <span class="aluc-promo-badge-num"><?php echo (int) self::DISCOUNT; ?>%</span>
                <span class="aluc-promo-badge-off"><?php esc_html_e( 'OFF', 'admin-login-url-change' ); ?></span>
            </div>

            <div class="aluc-promo-body">
                <p class="aluc-promo-eyebrow"><?php echo esc_html( $eyebrow ); ?> · <span><?php echo esc_html( $this->days_left_text() ); ?></span></p>
                <h3 class="aluc-promo-title">
                    <?php
                    /* translators: %d: discount percentage */
                    printf( esc_html__( 'Get %d%% off Admin Login URL Change Pro', 'admin-login-url-change' ), (int) self::DISCOUNT );
                    ?>
                </h3>
                <p class="aluc-promo-features"><?php esc_html_e( 'Limit login attempts · Block IPs & countries · 2FA · Temporary login links · Full audit log', 'admin-login-url-change' ); ?></p>

                <div class="aluc-promo-actions">
                    <?php $this->render_coupon(); ?>
                    <a href="<?php echo esc_url( $this->upgrade_url() ); ?>" class="button button-primary aluc-promo-cta">
                        <?php
                        /* translators: %d: discount percentage */
                        printf( esc_html__( 'Claim %d%% Off', 'admin-login-url-change' ), (int) self::DISCOUNT );
                        ?>
                    </a>
                    <button type="button" class="button-link aluc-promo-dismiss" data-type="snooze"><?php esc_html_e( 'Remind me later', 'admin-login-url-change' ); ?></button>
                    <button type="button" class="button-link aluc-promo-dismiss" data-type="dismiss"><?php esc_html_e( "Don't show again", 'admin-login-url-change' ); ?></button>
                </div>
                <?php $this->render_howto(); ?>
            </div>
        </div>
        <?php
    }

    public function render_settings_banner() {
        if ( ! $this->campaign_eligible() ) {
            return;
        }
        ?>
        <div class="aluc-promo-banner">
            <div class="aluc-promo-badge">
                <span class="aluc-promo-badge-num"><?php echo (int) self::DISCOUNT; ?>%</span>
                <span class="aluc-promo-badge-off"><?php esc_html_e( 'OFF', 'admin-login-url-change' ); ?></span>
            </div>
            <div class="aluc-promo-body">
                <p class="aluc-promo-eyebrow"><?php esc_html_e( 'Early Black Friday Deal', 'admin-login-url-change' ); ?> · <span><?php echo esc_html( $this->days_left_text() ); ?></span></p>
                <h3 class="aluc-promo-title"><?php esc_html_e( 'Unlock every Pro security feature before Black Friday', 'admin-login-url-change' ); ?></h3>
                <p class="aluc-promo-features"><?php esc_html_e( 'Limit login attempts, block IPs & countries, add 2FA, share one-click temporary login links, and keep a full audit log.', 'admin-login-url-change' ); ?></p>
                <?php $this->render_howto(); ?>
            </div>
            <div class="aluc-promo-actions">
                <?php $this->render_coupon(); ?>
                <a href="<?php echo esc_url( $this->upgrade_url() ); ?>" class="aluc-promo-cta-alt">
                    <?php
                    /* translators: %d: discount percentage */
                    printf( esc_html__( 'Claim %d%% Off', 'admin-login-url-change' ), (int) self::DISCOUNT );
                    ?>
                </a>
            </div>
        </div>
        <?php
    }
}
