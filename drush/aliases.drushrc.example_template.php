<?php
// Tables of which the data will not be synced/dumped.
$structure_tables_list = 'cache,cache_apachesolr,cache_block,cache_bootstrap,cache_commerce_shipping_rates,cache_entity_comment,cache_entity_file,cache_entity_node,cache_entity_registration,cache_entity_registration_state,cache_entity_registration_type,cache_entity_taxonomy_term,cache_entity_taxonomy_vocabulary,cache_entity_user,cache_field,cache_filter,cache_image,cache_form,cache_filter,cache_menu,cache_page,cache_panels,cache_path,cache_path_breadcrumbs,cache_rules,cache_token,cache_update,cache_variable,cache_views,cache_views_data,history,sessions,watchdog';

$aliases['test'] = array(
  'root' => '/var/www/{{ site_name }}/docroot',
  'uri' => 'http://{{ site_name_uri }}-test.triquanta.nl',
  'remote-host' => '{{ site_name_uri }}-test.triquanta.nl',
  'remote-user' => 'deploy',
  //'ssh-options' => '-o "ProxyCommand ssh admin@79.170.90.48 nc %h %p 2> /dev/null"',
  'command-specific' => array (
    'sql-sync' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
    'sql-dump' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
  ),
);

$aliases['acc'] = array(
  'root' => '/var/www/{{ site_name }}/docroot',
  'uri' => 'http://{{ site_name_uri }}-acc.triquanta.nl',
  'remote-host' => '{{ site_name_uri }}-acc.triquanta.nl',
  'remote-user' => 'deploy',
  //'ssh-options' => '-o "ProxyCommand ssh admin@79.170.90.48 nc %h %p 2> /dev/null"',
  'command-specific' => array (
    'sql-sync' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
    'sql-dump' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
  ),
);

$aliases['prod'] = array(
  'root' => '/var/www/{{ site_name }}/docroot',
  'uri' => 'https://{{ site_name_uri }}-prod.triquanta.nl',
  'remote-host' => '{{ site_name_uri }}-prod.triquanta.nl',
  'remote-user' => 'deploy',
  //'ssh-options' => '-o "ProxyCommand ssh admin@79.170.90.48 nc %h %p 2> /dev/null"',
  'command-specific' => array (
    'sql-sync' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
    'sql-dump' => array (
      'structure-tables-list' => $structure_tables_list,
    ),
  ),
);
