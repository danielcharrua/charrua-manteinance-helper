<?php
/**
 * Admin view: Activity Log page.
 *
 * Variables available:
 * @var array  $items        Log entries for the current page.
 * @var int    $total        Total number of entries.
 * @var int    $pages        Total number of pages.
 * @var int    $current_page Current page number.
 * @var string $filter_type  Active object_type filter.
 * @var int    $per_page     Entries per page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$event_labels = array(
    'activated'   => __( 'Activated', 'charrua-maintenance-helper' ),
    'deactivated' => __( 'Deactivated', 'charrua-maintenance-helper' ),
    'installed'   => __( 'Installed', 'charrua-maintenance-helper' ),
    'updated'     => __( 'Updated', 'charrua-maintenance-helper' ),
    'deleted'     => __( 'Deleted', 'charrua-maintenance-helper' ),
    'switched'    => __( 'Switched', 'charrua-maintenance-helper' ),
);

$event_colors = array(
    'activated'   => '#00a32a',
    'deactivated' => '#d63638',
    'installed'   => '#2271b1',
    'updated'     => '#dba617',
    'deleted'     => '#b32d2e',
    'switched'    => '#2271b1',
);
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Activity Log', 'charrua-maintenance-helper' ); ?></h1>
    <p><?php esc_html_e( 'History of plugin and theme events. Entries older than 90 days are automatically removed.', 'charrua-maintenance-helper' ); ?></p>

    <!-- Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="charrua-mh-activity-log" />
                <select name="object_type">
                    <option value=""><?php esc_html_e( 'All types', 'charrua-maintenance-helper' ); ?></option>
                    <option value="plugin" <?php selected( $filter_type, 'plugin' ); ?>><?php esc_html_e( 'Plugins', 'charrua-maintenance-helper' ); ?></option>
                    <option value="theme" <?php selected( $filter_type, 'theme' ); ?>><?php esc_html_e( 'Themes', 'charrua-maintenance-helper' ); ?></option>
                </select>
                <?php submit_button( __( 'Filter', 'charrua-maintenance-helper' ), 'secondary', 'filter_action', false ); ?>
            </form>
        </div>
        <div class="alignright">
            <span class="displaying-num">
                <?php
                printf(
                    /* translators: %s: number of entries */
                    esc_html( _n( '%s entry', '%s entries', $total, 'charrua-maintenance-helper' ) ),
                    esc_html( number_format_i18n( $total ) )
                );
                ?>
            </span>
        </div>
        <br class="clear" />
    </div>

    <?php if ( empty( $items ) ) : ?>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'No activity recorded yet.', 'charrua-maintenance-helper' ); ?></p>
        </div>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Event', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Version', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'User', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'IP', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'charrua-maintenance-helper' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'charrua-maintenance-helper' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $entry ) : ?>
                    <?php
                    $user = get_userdata( $entry->user_id );
                    $event_label = isset( $event_labels[ $entry->event_type ] ) ? $event_labels[ $entry->event_type ] : ucfirst( $entry->event_type );
                    $event_color = isset( $event_colors[ $entry->event_type ] ) ? $event_colors[ $entry->event_type ] : '#50575e';
                    $local_date = get_date_from_gmt( $entry->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

                    // Build version display.
                    $version_display = '';
                    if ( ! empty( $entry->object_version_from ) && ! empty( $entry->object_version ) ) {
                        $version_display = $entry->object_version_from . ' &rarr; ' . $entry->object_version;
                    } elseif ( ! empty( $entry->object_version ) ) {
                        $version_display = $entry->object_version;
                    }

                    // Build user link.
                    $user_display = __( 'System', 'charrua-maintenance-helper' );
                    if ( $user && $user->ID ) {
                        $user_edit_url = get_edit_user_link( $user->ID );
                        $user_display  = '<a href="' . esc_url( $user_edit_url ) . '">' . esc_html( $user->display_name ) . '</a>';
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $local_date ); ?></td>
                        <td>
                            <span style="color: <?php echo esc_attr( $event_color ); ?>; font-weight: 600;">
                                <?php echo esc_html( $event_label ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( ucfirst( $entry->object_type ) ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $entry->object_name ); ?></strong>
                            <?php if ( ! empty( $entry->object_author ) ) : ?>
                                <br><span style="color: #646970; font-size: 12px;">by <?php echo esc_html( $entry->object_author ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo wp_kses( $version_display, array( 'span' => array() ) ); ?></td>
                        <td><?php echo wp_kses( $user_display, array( 'a' => array( 'href' => array() ) ) ); ?></td>
                        <td>
                            <?php if ( ! empty( $entry->user_ip ) ) : ?>
                                <span style="color: #646970; font-size: 12px;"><?php echo esc_html( $entry->user_ip ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $source_labels = array(
                                'admin'    => __( 'Admin', 'charrua-maintenance-helper' ),
                                'wp-cli'   => __( 'WP-CLI', 'charrua-maintenance-helper' ),
                                'cron'     => __( 'Cron (auto-update)', 'charrua-maintenance-helper' ),
                                'rest-api' => __( 'REST API (external)', 'charrua-maintenance-helper' ),
                                'ajax'     => __( 'AJAX', 'charrua-maintenance-helper' ),
                                'unknown'  => __( 'Unknown', 'charrua-maintenance-helper' ),
                            );
                            $source_value = isset( $entry->source ) ? $entry->source : '';
                            $source_label = isset( $source_labels[ $source_value ] ) ? $source_labels[ $source_value ] : ucfirst( $source_value );
                            ?>
                            <span style="font-size: 12px;"><?php echo esc_html( $source_label ); ?></span>
                        </td>
                        <td><?php echo esc_html( $entry->details ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $pages,
                        'current'   => $current_page,
                    ) );

                    if ( $page_links ) {
                        echo wp_kses_post( $page_links );
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
