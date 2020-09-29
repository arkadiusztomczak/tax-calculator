<?php
/**
 * Plugin Name: Tax Calculator
 * Description: Simple tax calculator. Indeon/WPserver recruitment task by Arkadiusz Tomczak.
 * Version: 1.0
 * Author: Arkadiusz Tomczak
 */

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

function inicialize(){
    createTable();
}

function removeTable(){
    global $wpdb;
    $sql = "DROP TABLE ".$wpdb->prefix."taxcalc";
    $wpdb->query($sql);
}

function createTable(){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."taxcalc(
  id int(7) NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  ip varchar(15) NOT NULL,
  prName text NOT NULL,
  netto double(30,2),
  currency varchar(3),
  vcalc double(30,2),
  vat varchar(2),
  cp double(30,2),
  kp double(30,2),
  PRIMARY KEY(id)
) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

function add_to_log()
{
    $result = 0;
    if (isset($_POST['prname'])) {
        define('SHORTINIT', true);
        include($_SERVER['HTTP_HOST'] . substr($_SERVER[REQUEST_URI], 0, strpos($_SERVER[REQUEST_URI], 'wp-content')) . 'wp-load.php');

        global $wpdb;
        $prname = $wpdb->_escape( ($_POST['prname']));
        $netto = $wpdb->_escape( ($_POST['netto']));
        $vcalc = $wpdb->_escape( ($_POST['vcalc']));
        $vat = $wpdb->_escape( ($_POST['vat']));
        $cp = $wpdb->_escape( ($_POST['cp']));
        $kp = $wpdb->_escape( ($_POST['kp']));
        $curr = $wpdb->_escape( ($_POST['curr']));

        if (
            (strlen($prname) > 0) &&
            (strlen($prname) <= 255) &&
            (is_numeric($netto)) &&
            ($netto >= 0) &&
            (strlen($netto) <= 30) &&
            (is_numeric($cp)) &&
            ($cp >= 0) &&
            (strlen($cp) <= 30) &&
            (is_numeric($kp)) &&
            ($kp >= 0) &&
            (strlen($kp) <= 30) &&
            (in_array($vat, ['23', '22', '8', '7', '5', '3', '0', 'zw', 'np', 'oo']))
        ) {
            createTable();
            $ip = $_SERVER['REMOTE_ADDR'];
            $tab = $wpdb->prefix . "taxcalc";

            //Funkcja wstawiająca rekord z ograniczeniem pozwalającym konkretnemu adresowi IP wstawić co najwyżej jeden rekord na sekundę.
            $sql  = $wpdb->prepare("
            Insert into $tab (ip,prName,netto,vcalc,cp,kp,currency, vat) 
             select '$ip', '$prname', $netto, '$vcalc', $cp, $kp,'$curr','$vat'
             from dual where 
             (select now()-(select time from wp_taxcalc where ip = '$ip' order by id desc limit 1)>=1) or 
             (select (select ip from wp_taxcalc where ip = '$ip') is null)
            ");
            if($wpdb->query($sql)) $result=1;
            else echo $sql;
        }
    }
    echo $result;
    wp_die();
}

function taxcalcShow() {
    wp_register_style('tc_css', plugins_url('css/style.css',__FILE__ ));
    wp_enqueue_script( 'jquery-ui-widget' );
    wp_enqueue_style('tc_css');

    wp_enqueue_script( 'tc_script', plugins_url( '/js/script.js', __FILE__ ), array('jquery') );
    wp_localize_script( 'tc_script', 'ajax_object',
        array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

    $returnForm = '
<div class="taxcalcWidget">
<div class="container">
<form>

<div class="tc_inputBox">
<label for="tc_prName">Nazwa produktu</label>
<input type="text" placeholder="Nazwa produktu" id="tc_prName">
</div>

<div class="tc_inputBox">
<label for="tc_netto">Kwota netto</label>
<input type="number" placeholder="Kwota netto" id="tc_netto">
</div>

<div class="tc_inputBox">
<label for="tc_currency">Waluta</label>
<input type="text" placeholder="Waluta" value="PLN" id="tc_currency" disabled>
</div>

<div class="tc_inputBox">
<label for="tc_vat">Stawka VAT</label>
<select id="tc_vat">
<option value="" disabled selected>Stawka VAT</option>
<option value="23">23%</option>
<option value="22">22%</option>
<option value="8">8%</option>
<option value="7">7%</option>
<option value="5">5%</option>
<option value="3">3%</option>
<option value="0">0%</option>
<option value="zw">zw.</option>
<option value="np">np.</option>
<option value="oo">o.o.</option>
</select>
</div>

</form>
<button id="tc_submit">Oblicz</button>

<div id="tc_result"></div>
</div>
</div>
';
    return $returnForm;
}

function taxcalc_admin(){
    add_menu_page( 'Tax Calculator Page', 'Kalkulator podatkowy', 'manage_options', 'taxcalc', 'log_init' );
}

class logList extends WP_List_Table {

    function __construct() {
        parent::__construct( array(
            'singular'=> 'taxcalc result',
            'plural' => 'taxcalc results',
            'ajax'   => false
        ) );
    }

    function get_columns() {
        return $columns= array(
            'ip'=>__('Adres IP'),
            'time'=>__('Data zapytania'),
            'prname'=>__('Nazwa produktu'),
            'netto'=>__('Wprowadzona kwota netto'),
            'currency'=>__('Wybrana waluta'),
            'vat'=>__('Wybrany podatek'),
            'kp'=>__('Obliczona kwota podatku'),
            'cp'=>__('Obliczona wartość brutto')
        );
    }

    function prepare_items(){
        global $wpdb;

        $per_page = $this->get_items_per_page('url_per_page', 15);
        $columns = $this->get_columns();
        $hidden = array();

        $this->_column_headers = array($columns, $hidden);
        $this->process_bulk_action();

        $data = $this->get_name_url();

        $current_page = $this->get_pagenum();
        $total_items = count($data);
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'  => $per_page,
        ) );
        $this->items = $data;
    }

    public static function get_name_url() {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}taxcalc order by id desc";

        $result = $wpdb->get_results( $sql, 'ARRAY_A' );
        return $result;
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'ip':  return $item['ip'];
            case 'time':   return $item['time'];
            case 'prname':   return $item['prName'];
            case 'netto':   return $item['netto'];
            case 'currency':   return $item['currency'];
            case 'vat':   return $item['vat']." (".$item['vcalc']."%)";
            case 'kp':   return $item['kp'];
            case 'cp':   return $item['cp'];
            default:      return print_r( $item, true );
        }
    }
}

function uninstall(){
    removeTable();
}

function log_init(){
    $log = new logList();
    $log->prepare_items();

    $log->display();
}


add_shortcode('taxcalc', 'taxcalcShow');
register_activation_hook(__FILE__,'inicialize');
register_deactivation_hook(__FILE__,'uninstall');
register_uninstall_hook(__FILE__,'uninstall');

add_action('wp_ajax_add_to_log', 'add_to_log');
add_action('wp_ajax_nopriv_add_to_log', 'add_to_log');

add_action('admin_menu', 'taxcalc_admin');