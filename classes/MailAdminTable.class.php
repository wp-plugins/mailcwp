<?php 
class MailAdminTable extends WP_List_Table {
  var $m_user_id = null;

  function __construct($user_id) {
    global $m_user_id;

    $m_user_id = $user_id;

    parent::__construct(array(
      'singular' => 'mailcwp_account',
      'plural' => 'mailcwp_accounts',
      'ajax' => true
    ));
  }

  function get_columns() {
    return $columns = array(
      'cb' => '<input type="checkbox"/>',
      'id' => __('ID'),
      'name' => __('Name'),
      'host' => __('Host'),
      'email' => __('Email Address'),
    );
  }

  function get_sortable_columns() {
    return $sortable = array(
      'id' => 'id',
      'name' => 'name',
      'host' => 'host',
      'email' => 'email',
    );
  }

  function no_items() {
    _e("No accounts found");
  }

  function prepare_items() {
    global $m_user_id;

    $screen = get_current_screen();

    //delete_user_meta($m_user_id, "mailcwp_accounts");
    $user_mail_accounts = get_user_meta($m_user_id, "mailcwp_accounts", true);
    if (!is_array($user_mail_accounts)) {
      $user_mail_accounts = array();
    }
    $totalitems = count($user_mail_accounts);//return the total number of affected rows
    $perpage = 5;
    $paged = !empty($_GET["paged"]) ? mysql_real_escape_string($_GET["paged"]) : '';
    if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ 
      $paged=1; 
    }

    $totalpages = ceil($totalitems/$perpage);
    if(!empty($paged) && !empty($perpage)){
      $offset=($paged-1)*$perpage;
      //$query.=' LIMIT '.(int)$offset.','.(int)$perpage;
    }

    $this->set_pagination_args( array(
       "total_items" => $totalitems,
       "total_pages" => $totalpages,
       "per_page" => $perpage,
    ));

    $columns = $this->get_columns();
    $sortable_columns = $this->get_sortable_columns();
    //$_wp_column_headers[$screen->id]=$columns;
    $this->_column_headers = array($columns, array("id"), $sortable_columns);
    $this->items = array_slice($user_mail_accounts, (int)$offset, (int)$perpage);
  }

  function column_name($item) {
    global $m_user_id;

    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
    $actions = array(
        
		'edit'    => "<a href=\"javascript:void(0);\" onclick='editAccount(null, \"$item[id]\", $m_user_id, false);'>Edit</a>",
		'delete'    => "<a href=\"javascript:void(0);\" onclick='jQuery.ajax ({type: \"post\", url: ajaxurl, data: { action: \"mailcwp_delete_account\", user_id: $m_user_id, account_id: \"$item[id]\" }, error: function(aObject, aTextStatus, aErrorThrown) { alert(\"Unable to delete account: \" + aTextStatus + \"/\" + aErrorThrown); }, success: function (aHtml) { alert(\"Account deleted\"); document.location.reload();}});'>Delete</a>",
	    );

    return sprintf('%1$s %2$s', $item["name"], $this->row_actions($actions) );
  
  }

  function column_default($item, $column_name) {
    return $item[$column_name];
  }

  function get_bulk_actions() {
    $actions = array(
      'delete' => 'Delete'
    );
    return $actions;
  }

  function column_cb($item) {
    return sprintf(
            '<input type="checkbox" name="account[]" value="%s" />', $item["id"]
        );
  }

  function display_tablenav( $which ) {
	if ( 'top' == $which )
                        //wp_nonce_field( 'bulk-' . $this->_args['plural'] );
?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
  
                <div class="alignleft actions bulkactions">
                        <?php $this->bulk_actions(); ?>
                </div>
<?php 
                $this->extra_tablenav( $which );
                $this->pagination( $which );
?>

                <br class="clear" />
        </div>
<?php       
  }
}
?>
