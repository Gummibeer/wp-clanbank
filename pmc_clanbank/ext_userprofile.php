<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'pmc_ext_userprofile' ) ) :
    class pmc_ext_userprofile {
        private $table_name;

        public function __construct($table_name)
        {
            $this->table_name = $table_name;

            add_action( 'show_user_profile', array($this, 'add_user_profile_fields') );
            add_action( 'edit_user_profile', array($this, 'add_user_profile_fields') );

            add_action( 'personal_options_update', array($this, 'save_user_profile_fields') );
            add_action( 'edit_user_profile_update', array($this, 'save_user_profile_fields') );

            add_filter( 'manage_users_columns', array($this, 'add_user_list_column') );

            add_action('manage_users_custom_column', array($this, 'show_user_list_column_content'), 10, 3);
        }

        public function add_user_profile_fields( $user ) {
            $checked = '';
            if(get_user_meta( $user->ID, 'pmc_clanbank_excluded', true ))
                $checked = 'checked="checked"';
            ?>
            <h3>PMC Clanbank</h3>

            <table class="form-table">
                <tr>
                    <th><label for="address">Clanbank ausschluss</label></th>
                    <td>
                        <input type="checkbox" name="pmc_excluded" id="pmc_excluded" value="1" <?php echo $checked; ?> /><br />
                        <span class="description">Ist der Benutzer von der Clanbank ausgeschlossen/befreit?</span>
                    </td>
                </tr>
            </table>
        <?php }

        public function save_user_profile_fields( $user_id )
        {
            if ( is_admin() ):
                $excluded = 0;
                if($_POST['pmc_excluded'])
                    $excluded = 1;

                update_user_meta( $user_id, 'pmc_clanbank_excluded', $excluded );
            endif;
        }

        public function add_user_list_column($columns)
        {
            $columns['clanbank_input'] = 'Clan-Beitrag';
            return $columns;
        }

        public function show_user_list_column_content($value, $column_name, $user_id)
        {
            $excluded = esc_attr( get_user_meta( $user_id, 'pmc_clanbank_excluded', true ) ) ? esc_attr( get_user_meta( $user_id, 'pmc_clanbank_excluded', true ) ) : 0;
            if( 'clanbank_input' == $column_name ):
                if($excluded != 1):
                    global $wpdb;
                    $query = 'SELECT SUM(amount) FROM ' . $this->table_name . ' WHERE userid = ' . $user_id;
                    $pmc_input = $wpdb->get_var( $query ) ? $wpdb->get_var( $query ) : 0;
                    $color = $pmc_input > 0 ? '#D1DC48' : '#EE6456';
                    return '<strong style="color:' . $color . ';">' . number_format($pmc_input, 2, ',', '.') . ' €</strong>';
                else:
                    return 'befreit';
                endif;
            endif;
        }
    }
endif;