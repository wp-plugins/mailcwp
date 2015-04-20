<?php /* Smarty version Smarty-3.1.17, created on 2014-06-11 02:58:59
         compiled from "/home/cadrewor/public_html/cadreworks_staging/wp-content/plugins/mailcwp/templates/toolbar.tpl" */ ?>
<?php /*%%SmartyHeaderCode:884887715397c5f3594d93-54774501%%*/if(!defined('SMARTY_DIR')) exit('no direct access allowed');
$_valid = $_smarty_tpl->decodeProperties(array (
  'file_dependency' => 
  array (
    '19d3ffe4a41582318b01a3fbd6764d090002bba6' => 
    array (
      0 => '/home/cadrewor/public_html/cadreworks_staging/wp-content/plugins/mailcwp/templates/toolbar.tpl',
      1 => 1402455531,
      2 => 'file',
    ),
  ),
  'nocache_hash' => '884887715397c5f3594d93-54774501',
  'function' => 
  array (
  ),
  'variables' => 
  array (
    'labels' => 0,
    'user_change_theme' => 0,
    'set1_extensions' => 0,
    'set2_extensions' => 0,
    'set_search_extensions' => 0,
    'progress_bar_src' => 0,
  ),
  'has_nocache_code' => false,
  'version' => 'Smarty-3.1.17',
  'unifunc' => 'content_5397c5f36edc38_12387115',
),false); /*/%%SmartyHeaderCode%%*/?>
<?php if ($_valid && !is_callable('content_5397c5f36edc38_12387115')) {function content_5397c5f36edc38_12387115($_smarty_tpl) {?><div id="mailcwp_toolbar" class="ui-widget-header ui-corner-all">
  <button id="mailcwp_compose"><?php echo $_smarty_tpl->tpl_vars['labels']->value['compose'];?>
</button>
  <button id="mailcwp_refresh"><?php echo $_smarty_tpl->tpl_vars['labels']->value['refresh'];?>
</button>
  <button id="mailcwp_options"><?php echo $_smarty_tpl->tpl_vars['labels']->value['options'];?>
</button>
  <?php if (($_smarty_tpl->tpl_vars['user_change_theme']->value)) {?>
    <button id="mailcwp_themes"><?php echo $_smarty_tpl->tpl_vars['labels']->value['themes'];?>
</button>
    <div id="themeswitcher"></div>
  <?php }?>
  <?php echo $_smarty_tpl->tpl_vars['set1_extensions']->value;?>

  &nbsp;&nbsp;&nbsp;
  <button id="mailcwp_read"><?php echo $_smarty_tpl->tpl_vars['labels']->value['mark_read'];?>
</button>
  <button id="mailcwp_unread"><?php echo $_smarty_tpl->tpl_vars['labels']->value['mark_unread'];?>
</button>
  <button id="mailcwp_delete"><?php echo $_smarty_tpl->tpl_vars['labels']->value['delete'];?>
</button>
  <?php echo $_smarty_tpl->tpl_vars['set2_extensions']->value;?>

  &nbsp;&nbsp;&nbsp;
  <input type="text" id="mailcwp_search_term" class="ui-widget toolbar-input" placeholder="<?php echo $_smarty_tpl->tpl_vars['labels']->value['search'];?>
"/>
  <button id="mailcwp_search"><?php echo $_smarty_tpl->tpl_vars['labels']->value['search'];?>
</button>
  <?php echo $_smarty_tpl->tpl_vars['set_search_extensions']->value;?>

  <div id="progressbar"><img src="<?php echo $_smarty_tpl->tpl_vars['progress_bar_src']->value;?>
"></div><div id="mailcwp_last_update">Last updated</div>
</div>
<?php }} ?>
