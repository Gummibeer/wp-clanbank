<?php
/**
 * Plugin Name: Clankasse
 * Description: Clankassen Verwaltung für den Panda-Multigaming-Clan.
 * Author:      Gummibeer
 * Author URI:  https://github.com/Gummibeer
 * Version:     0.1
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'pmc_clanbank' ) ) :
    class pmc_clanbank {

        private $plugin_slug;
        private $table_name;
        private $table_create;
        private $ext_userprofile;

        public function __construct()
        {
            global $wpdb;

            $this->plugin_slug = 'pmc_clanbank';
            $this->table_name = $wpdb->prefix . $this->plugin_slug . '_log';
            $this->table_create = "CREATE TABLE $this->table_name (
                                    id mediumint(11) NOT NULL AUTO_INCREMENT,
                                    bookerid mediumint(11) NOT NULL,
                                    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    userid mediumint(11) NOT NULL,
                                    day date NOT NULL,
                                    amount float(11, 2) NOT NULL,
                                    description VARCHAR(255) NOT NULL,
                                    PRIMARY KEY (id)
                                );";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            register_activation_hook( __FILE__, array( $this, 'initial_install' ) );

            require_once( ABSPATH . 'wp-includes/pluggable.php' );
            if( is_admin() ):
                add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            endif;

            require_once('ext_userprofile.php');
            $this->ext_userprofile = new pmc_ext_userprofile($this->table_name);
        }

        public function initial_install()
        {
            dbDelta( $this->table_create );
        }

        public function add_plugin_page()
        {
            add_options_page(
                'Panda-Multigaming-Clan Clanbank',
                'PMC Clanbank',
                'manage_options',
                $this->plugin_slug,
                array( $this, 'create_admin_page' )
            );
        }

        public function create_admin_page()
        {
            global $wpdb;

            if(!empty($_POST['submit']) && current_user_can('manage_options')):
                $userid = esc_attr( $_POST['userid'] * 1 );
                $day = esc_attr( $_POST['date'] );
                $amount = esc_attr( str_replace( ',', '.', $_POST['amount'] ) * 1 );
                $description = esc_attr( trim( $_POST['description'] ) );

                $wpdb->insert(
                    $this->table_name,
                    array(
                        'bookerid' => get_current_user_id(),
                        'userid' => $userid,
                        'day' => $day,
                        'amount' => $amount,
                        'description' => $description,
                    )
                );
            endif;

            $rows = $wpdb->get_results( 'SELECT amount FROM ' . $this->table_name . ' ORDER BY day DESC');
            $amounts = array();
            foreach ( $rows as $row ) {
                array_push($amounts, $row->amount);
            }
            ?>
            <div class="wrap">
                <h2>Panda-Multigaming-Clan Clanbank (EUR <?php echo number_format(array_sum($amounts), 2, ',', '.'); ?>)</h2>
                <form method="post" action="">
                    <p>
                        <label for="userid">Clan-Mitglied</label>
                        <select name="userid" id="userid">
                            <?php
                            $users = get_users( 'orderby=nicename' );
                            foreach ( $users as $user ) {
                                $excluded = esc_attr( get_user_meta( $user->ID, 'pmc_clanbank_excluded', true ) ) ? esc_attr( get_user_meta( $user->ID, 'pmc_clanbank_excluded', true ) ) : 0;
                                if($excluded != 1):
                                    echo '<option value="' . esc_html( $user->ID ) . '">' . esc_html( $user->display_name ) . ' | ' . esc_html( $user->user_firstname ) . ' ' . esc_html( $user->user_lastname ) . '</option>';
                                endif;
                            }
                            ?>
                        </select>
                    </p>

                    <p>
                        <label for="date">Datum</label>
                        <input type="text" name="date" id="date" placeholder="2015-12-31" />
                    </p>

                    <p>
                        <label for="amount">Betrag</label>
                        <input type="text" name="amount" id="amount" placeholder="3,00" />
                    </p>

                    <p>
                        <label for="description">Betreff</label>
                        <input type="text" name="description" id="description" placeholder="Panda-Clan Mitgliedsbeitrag 02/15 bis 04/15 von Tom Witkowski" size="150" />
                    </p>
                    <?php submit_button(); ?>
                </form>


                <?php
                $urls['base'] = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
                $urls['sortid'] = add_query_arg( array('sort' => 'id'), $urls['base'] );
                $urls['sortday'] = add_query_arg( array('sort' => 'day'), $urls['base'] );
                ?>
                <table class="wp-list-table widefat">
                    <thead>
                    <tr>
                        <th class="manage-column"><a href="<?php echo $urls['sortid']; ?>">ID</a></th>
                        <th class="manage-column">eingetragen</th>
                        <th class="manage-column">Mitglied</th>
                        <th class="manage-column"><a href="<?php echo $urls['sortday']; ?>">Buchungsdatum</a></th>
                        <th class="manage-column">Buchungsbetrag</th>
                        <th class="manage-column">Betreff</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php
                    $where['bookerid'] = esc_attr( $_GET['booker'] );
                    $where['userid'] = esc_attr( $_GET['user'] );
                    if( !empty( $where['bookerid'] ) ) {
                        $str_where = ' WHERE bookerid = ' . $where['bookerid'] . ' ';
                    } elseif( !empty( $where['userid'] ) ) {
                        $str_where = ' WHERE userid = ' . $where['userid'] . ' ';
                    } else {
                        $str_where = '';
                    }

                    $sort = !empty( $_GET['sort'] ) ? esc_attr( $_GET['sort'] ) : 'id';

                    $rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table_name . $str_where . ' ORDER BY ' . $sort . ' DESC' );
                    foreach ( $rows as $row ) {
                        $booker = get_userdata($row->bookerid);
                        $user = get_userdata($row->userid);
                        $color = $row->amount > 0 ? '#D1DC48' : '#EE6456';
                        $urls['booker'] = add_query_arg( array('booker' => $row->bookerid, 'user' => null), $urls['base'] );
                        $urls['user'] = add_query_arg( array('booker' => null, 'user' => $row->userid), $urls['base'] );
                        echo '<tr>';
                        echo '<td>#' . $row->id . '</td>';
                        echo '<td><a href="' . $urls['booker'] . '">' . esc_html( $booker->user_firstname ) . ' ' . esc_html( $booker->user_lastname ) . ' - ' . date( 'd.m.Y H:i', strtotime( $row->timestamp ) ) . '</a></td>';
                        echo '<td><a href="' . $urls['user'] . '">' . esc_html( $user->display_name ) . ' | ' . esc_html( $user->user_firstname ) . ' ' . esc_html( $user->user_lastname ) . '</a></td>';
                        echo '<td>' . date( 'd.m.Y', strtotime( $row->day ) ) . '</td>';
                        echo '<td style="color:' . $color . ';font-weight:bold;">' . number_format($row->amount, 2, ',', '.') . ' €</td>';
                        echo '<td>' . $row->description . '</td>';
                        echo '</tr>';
                    }
                    ?>
                    </tbody>

                    <tfoot>
                    <tr>
                        <th class="manage-column"><a href="<?php echo $urls['sortid']; ?>">ID</a></th>
                        <th class="manage-column">eingetragen</th>
                        <th class="manage-column">Mitglied</th>
                        <th class="manage-column"><a href="<?php echo $urls['sortday']; ?>">Buchungsdatum</a></th>
                        <th class="manage-column">Buchungsbetrag</th>
                        <th class="manage-column">Betreff</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php
        }

        public function getPmcBank() {
            global $wpdb;
            $rows = $wpdb->get_results( 'SELECT amount FROM ' . $this->table_name . ' ORDER BY day DESC');
            $amounts = array();
            foreach ( $rows as $row ) {
                array_push($amounts, $row->amount);
            }
            return number_format(array_sum($amounts), 2, ',', '.');
        }

    }

    $pmc_clanbank = new pmc_clanbank();
endif;