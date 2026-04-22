<?php
/**
 * Admin view: Plugin Monitor page.
 *
 * Variables available:
 * @var array $alerts List of alert arrays.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Plugin Monitor', 'charrua-maintenance-helper' ); ?></h1>
    <p><?php esc_html_e( 'Plugins that were unexpectedly deactivated during an update process will appear here.', 'charrua-maintenance-helper' ); ?></p>

    <?php if ( isset( $_GET['dismissed'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Alert dismissed.', 'charrua-maintenance-helper' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['dismissed_all'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'All alerts dismissed.', 'charrua-maintenance-helper' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty( $alerts ) ) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'No alerts. All plugins remained active after their last updates.', 'charrua-maintenance-helper' ); ?></p>
        </div>
    <?php else : ?>
        <div style="margin-bottom: 15px;">
            <a href="<?php echo esc_url( wp_nonce_url(
                add_query_arg(
                    array(
                        'page'               => 'charrua-mh-monitor',
                        'charrua_mh_action'  => 'dismiss_all',
                    ),
                    admin_url( 'admin.php' )
                ),
                'charrua_mh_dismiss_all'
            ) ); ?>" class="button">
                <?php esc_html_e( 'Dismiss All', 'charrua-maintenance-helper' ); ?>
            </a>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Plugin', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Detected', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'User', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'charrua-maintenance-helper' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $alerts as $alert ) : ?>
                    <?php
                    $user = get_userdata( $alert['user_id'] );
                    $username = $user ? $user->display_name : __( 'System (auto-update)', 'charrua-maintenance-helper' );
                    $detected_at = get_date_from_gmt( $alert['detected_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
                    ?>
                    <tr>
                        <td>
                            <strong style="color: #d63638;">&#9888; <?php echo esc_html( $alert['plugin_name'] ); ?></strong>
                            <br>
                            <code style="font-size: 11px;"><?php echo esc_html( $alert['plugin'] ); ?></code>
                        </td>
                        <td><?php echo esc_html( $detected_at ); ?></td>
                        <td><?php echo esc_html( $username ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'page'               => 'charrua-mh-monitor',
                                        'charrua_mh_action'  => 'dismiss_alert',
                                        'alert_id'           => $alert['id'],
                                    ),
                                    admin_url( 'admin.php' )
                                ),
                                'charrua_mh_dismiss_alert'
                            ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Mark as Reviewed', 'charrua-maintenance-helper' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
