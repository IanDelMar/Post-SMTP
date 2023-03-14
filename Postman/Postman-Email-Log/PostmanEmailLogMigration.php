<?php

if( !class_exists( 'PostmanEmailLogsMigration' ) ):
class PostmanEmailLogsMigration {
    
    private $new_logging = false;
    private $migrating = false;
    private $have_old_logs = false;

    /**
     *  Constructor PostmanEmailLogsMigration
     * 
     * @since 2.4.0
     * @version 1.0.0
     */
    public function __construct() {

        $this->new_logging = get_option( 'postman_db_version' );
        $this->migrating = get_option( 'ps_migrate_logs' );
        $this->have_old_logs = $this->have_old_logs();
        
        //Show DB Update Notice
        if( $this->have_old_logs  ) {

            add_action( 'admin_notices', array( $this, 'notice' ) );

        }

        if( isset( $_GET['action'] ) && $_GET['action'] == 'ps-migrate-logs' ) {

            $this->update_database();

        }

        if( isset( $_GET['action'] ) && $_GET['action'] == 'ps-delete-old-logs' ) {

            $this->trash_all_old_logs();

        }
        
        //Add Hook of Migration, Schedule Migration
        if( $this->migrating && ( isset( $_GET['page'] ) && $_GET['page'] == 'postman_email_log' ) ) {

            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );

        }

        //Switch back to old system
        if( isset( $_GET['action'] ) && $_GET['action'] == 'ps-switch-back' ) {

            $this->switch_back();

        }

        //Switch to new system
        if( isset( $_GET['action'] ) && $_GET['action'] == 'ps-switch-to-new' ) {

            $this->switch_to_new();

        }

        add_action( 'wp_ajax_ps-migrate-logs', array( $this, 'migrate_logs' ) );

    }


    /**
     * Checks if have logs in old system
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function have_old_logs() {

        global $wpdb;

        $data = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'postman_sent_mail' LIMIT 1;"
        );
        
        if( !empty( $data ) ) {

            return true;
            
        }

        return false;

    }
    

    /**
     *  Shows DB Update Notice | Action call-back
     * 
     * @since 2.4.0
     * @version 1.0.0
     */
    public function notice() {

        $security = wp_create_nonce( 'ps-migrate-logs' );
        $migration_url = admin_url( 'admin.php?page=postman' ) . '&security=' . $security . '&action=ps-migrate-logs';
        $delete_url = admin_url( 'admin.php?page=postman_email_log' ) . '&security=' . $security . '&action=ps-delete-old-logs';
        $switch_back = admin_url( 'admin.php?page=postman_email_log' ) . '&security=' . $security . '&action=ps-switch-back';
        $switch_to_new = admin_url( 'admin.php?page=postman_email_log' ) . '&security=' . $security . '&action=ps-switch-to-new';
        $status_url = admin_url( 'admin.php?page=postman_email_log' );
        $total_old_logs = wp_count_posts( 'postman_sent_mail' );
        $this->migrating = get_option( 'ps_migrate_logs' );
        $migrated_logs = $this->get_migrated_count();
        $current_page = isset( $_GET['page'] ) ?  $_GET['page'] : '';
        $new_logging = get_option( 'postman_db_version' );

        ?>
        <div class="notice ps-db-update-notice is-dismissible" style="border: 1px solid #2271b1; border-left-width: 4px;">
            <input type="hidden" value="<?php echo esc_attr( $security ); ?>" class="ps-security">
            <p><b><?php _e( 'Post SMTP database update required', 'post-smtp' ); ?></b></p>
            <?php if( $this->have_old_logs && !$this->migrating && !$this->is_migrated() ): ?>
                <p><?php echo _e( 'Post SMTP has been updated! To keep things running smoothly, we have to update your database to the newest version, migrate email logs to new system. The database update process runs in the background and may take a little while, so please be patient.', 'post-smtp' ); ?></p>
                <a href="<?php echo esc_url( $migration_url ) ?>" class="button button-primary">Update and Migrate Logs</a>
            <?php endif; ?>
            <?php if(  
                ( $this->is_migrated() && $current_page !== 'postman_email_log' )
                ||
                ( $this->migrating )
                &&
                ( $current_page !== 'postman_email_log' )
            ): ?>
                <p><?php echo _e( 'Post SMTP is migrating logs to new system.', 'post-smtp' ); ?></p>
                <a href="<?php echo esc_url( $status_url ); ?>" class="button button-secondary">View Progress →</a>
            <?php endif; ?>
            <?php
            if(  
                $this->have_old_logs()
                &&
                $this->is_migrated()
                &&
                ( $current_page == 'postman_email_log' )
                &&
                $new_logging
            ): ?>
                <p><?php echo _e( 'Great! Logs successfully migrated, please verify and Delete logs from old system by clicking <b>Delete old Logs</b>, to keep system smooth.', 'post-smtp' ); ?></p>
                <a href="<?php echo esc_url( $switch_back ); ?>" class="button button-primary">Switch to old System</a>
                <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-primary">Delete old Logs</a>
            <?php endif; ?>
            <?php if( 
                $this->migrating 
                && 
                ( isset( $_GET['page'] ) && $_GET['page'] == 'postman_email_log' ) 
            ): ?>
                <p><?php _e( 'Post SMTP is migrating logs to new system.', 'post-smtp' ); ?></p>
            <?php endif; ?>
            <?php if( 
                !$new_logging
                &&
                $this->is_migrated()
                && 
                $this->have_old_logs() 
                &&
                $current_page == 'postman_email_log' 
            ): ?>
                <a href="<?php echo esc_url( $switch_to_new ); ?>" class="button button-primary">Switch to new System</a>
            <?php endif; ?>
            <a href="" target="__blank" class="button button-secondary">Learn about migration</a>
            <div style="margin: 10px 0;"></div>
            <?php
            if( 
                $this->migrating 
                && 
                ( isset( $_GET['page'] ) && $_GET['page'] == 'postman_email_log' ) 
            ) {
                ?>
                <div class="ps-migration-box" style="text-align: center; width: 100%; margin: 10px 0;">
                    <progress style="width: 100%;" id="ps-migration-progress"  value="<?php echo esc_attr( $migrated_logs ); ?>" max="<?php echo esc_attr( $total_old_logs->private ); ?>"></progress>
                    <h5 id="ps-progress"><?php echo esc_attr( "{$migrated_logs}/ {$total_old_logs->private}" ); ?></h5>
                </div>
                <?php

            }
            ?>
        </div>
        <?php
        if(
            $this->have_old_logs 
            && 
            $this->migrating 
            &&
            !$this->is_migrated() 
            &&
            ( isset( $_GET['page'] ) && $_GET['page'] == 'postman_email_log' )
        ) {
            ?>
            <?php
        }

    }


    /**
     * Updates Database 
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function update_database() {

        wp_verify_nonce( $_GET['security'], 'ps-migrate-logs' );

        //Let's start migration 

        $email_logs = new PostmanEmailLogs;
        $email_logs->install_table();

        if( $this->have_old_logs && !$this->migrating ) {

            update_option( 'ps_migrate_logs', 1 );

        }
        
    }

    
    /**
     * Gets last old log, to be migrated
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function get_last_old_log() {

        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id = ( SELECT ID FROM {$wpdb->posts} ORDER BY ID DESC LIMIT 1 )"
        );

    }


    /**
     * Migrate Logs | AJAX call-back
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function migrate_logs() {

        if( wp_verify_nonce( $_POST['security'], 'ps-migrate-logs' ) ) {

            if( isset( $_POST['action'] ) && $_POST['action'] == 'ps-migrate-logs' ) {
    
                if( $this->have_old_logs ) {
    
                    $old_logs = $this->get_old_logs();
        
                    if( $old_logs && !empty( $old_logs )) {
    
                        //Migrating Logs
                        foreach( $old_logs as $ID => $log ) {
                
                            $result = PostmanEmailLogs::get_instance()->save( $log );
            
                            if( $result ) {
    
                                $result = wp_update_post( 
                                    array( 
                                        'ID'        =>  $ID,
                                        'pinged'    =>  1 
                                    )
                                );
    
                            }
    
                        }

                        //If all migrated
                        if( $this->get_migrated_count() ==  wp_count_posts( 'postman_sent_mail' )->private ) {

                            delete_option( 'ps_migrate_logs' );
    
                            wp_send_json_success( 
                                array(
                                    'migrated'  =>  $this->get_migrated_count(),
                                    'total'     =>  wp_count_posts( 'postman_sent_mail' )->private
                                ), 
                                200 
                            );  

                        }
    
                        wp_send_json_success( 
                            array( 
                                'migrated'  =>   $this->get_migrated_count()
                            ), 
                            200 
                        );
    
                    }
        
        
                }
    
            }

        }

    }


    /**
     * Enqueue Scripts | Action call-back
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function enqueue_script() {

        wp_enqueue_script( 'ps-migrate', POST_SMTP_URL . '/script/logs-migration.js', array( 'jquery' ), array(), true );

    }

    
    /**
     * Get old logs
     * 
     * @param $limit String
     * @since 2.5.0
     * @version 1.0.0
     */
    public function get_old_logs( $limit = 500 ) {

        global $wpdb;

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_date FROM {$wpdb->posts} AS p WHERE p.post_type = 'postman_sent_mail' && p.pinged != 1 LIMIT %d;",
                $limit
            ),
            OBJECT_K
        );
        $log_ids = array_keys( $logs );
        $log_ids = implode( ',', $log_ids );

        if( $log_ids ) {

            $logs_meta = $wpdb->get_results(
                "SELECT post_id as ID, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$log_ids});"
            );
            
            if( $logs_meta ) {
    
                //Preparing logs
                foreach( $logs_meta as $log_meta ) {
    
                    if( isset( $prepared_logs[$log_meta->ID] ) ) {
    
                        $prepared_logs[$log_meta->ID][$log_meta->meta_key] = $log_meta->meta_value;
    
                    }
                    else {
    
                        $prepared_logs[$log_meta->ID] = array(
                            $log_meta->meta_key =>  $log_meta->meta_value,
                            'time'              =>  strtotime( $logs[$log_meta->ID]->post_date )
                        );
    
                    }
    
                }

                return $prepared_logs;

            }

            return false;

        }

        return false;

    }


    /**
     * Gets migrated logs Count 
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function get_migrated_count() {

        global $wpdb;

        $response =  $wpdb->get_results(
            "SELECT 
            count(*) AS count
            FROM 
            {$wpdb->posts}
            WHERE 
            post_type = 'postman_sent_mail'
            &&
            pinged = 1"
        );
        
        return empty( $response ) ? false : (int)$response[0]->count;

    }


    /**
     * Checks if logs migrated or not
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function is_migrated() {

        $total_old_logs = wp_count_posts( 'postman_sent_mail' )->private;   

        if( $this->get_migrated_count() == (int)$total_old_logs ) {

            return true;

        }

        return  false;

    }

    
    /**
     * Trash all old logs
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function trash_all_old_logs() {

        if( wp_verify_nonce( $_GET['security'], 'ps-migrate-logs' ) ) {

            global $wpdb;

            $result = $wpdb->get_results(
                "SELECT ID 
                FROM {$wpdb->posts} 
                WHERE post_type = 'postman_sent_mail';",
                OBJECT_K
            );
            $log_ids = array_keys( $result );
            $log_ids = implode( ',', $log_ids );

            $result = $wpdb->get_results(
                "DELETE p.*, pm.*
                FROM {$wpdb->posts} AS p
                INNER JOIN 
                {$wpdb->postmeta} AS pm
                ON p.ID = pm.post_id
                WHERE p.post_type = 'postman_sent_mail' && p.ID IN ({$log_ids});"
            );

            wp_redirect( admin_url( 'admin.php?page=postman_email_log' ) );

        }

    }


    /**
     * Switch back to old logs
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function switch_back() {

        if( wp_verify_nonce( $_GET['security'], 'ps-migrate-logs' ) ) {

            delete_option( 'postman_db_version' );

            wp_redirect( admin_url( 'admin.php?page=postman_email_log' ) );

        } 

    }


    /**
     * Switch to new system
     * 
     * @since 2.5.0
     * @version 1.0.0
     */
    public function switch_to_new() {

        if( wp_verify_nonce( $_GET['security'], 'ps-migrate-logs' ) ) {

            update_option( 'postman_db_version', POST_SMTP_DB_VERSION );

            wp_redirect( admin_url( 'admin.php?page=postman_email_log' ) );

        } 

    }

}

new PostmanEmailLogsMigration;

endif;