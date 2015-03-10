<div id="mailcwp_toolbar" class="ui-widget-header ui-corner-all">
  <button id="mailcwp_compose">{$labels['compose']}</button>
  <button id="mailcwp_refresh">{$labels['refresh']}</button>
  <button id="mailcwp_options">{$labels['options']}</button>
  {if ($user_change_theme) }
    <button id="mailcwp_themes">{$labels['themes']}</button>
    <div id="themeswitcher"></div>
  {/if}
  {$set1_extensions}
  &nbsp;&nbsp;&nbsp;
  <button id="mailcwp_read">{$labels['mark_read']}</button>
  <button id="mailcwp_unread">{$labels['mark_unread']}</button>
  <button id="mailcwp_delete">{$labels['delete']}</button>
  {$set2_extensions}
  &nbsp;&nbsp;&nbsp;
  <input type="text" id="mailcwp_search_term" class="ui-widget toolbar-input" placeholder="{$labels['search']}"/>
  <button id="mailcwp_search">{$labels['search']}</button>
  {$set_search_extensions}
  <div id="progressbar"><img src="{$progress_bar_src}"></div><div id="mailcwp_last_update">Last updated</div>
</div>
